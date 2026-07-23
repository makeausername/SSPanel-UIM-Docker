<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\XNodeAuditRule;
use App\Models\XNodeAuditRulePattern;
use App\Services\AdminPermissionService;
use App\Services\DB;
use App\Services\XNodeAuditService;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function array_map;
use function array_slice;
use function count;
use function implode;
use function time;

final class DetectRuleController extends BaseController
{
    private static array $details = [
        'field' => [
            'name' => '规则',
            'description' => '说明',
            'patterns' => '匹配内容',
            'match_summary' => '匹配方式',
            'action_label' => '处置',
            'scope' => '应用范围',
            'enabled_label' => '状态',
            'op' => '',
        ],
        'add_dialog' => [
            [
                'id' => 'name',
                'info' => '规则名称',
                'type' => 'input',
                'placeholder' => '例如：阻止投诉域名',
            ],
            [
                'id' => 'description',
                'info' => '规则说明',
                'type' => 'input',
                'placeholder' => '说明规则用途和来源',
            ],
            [
                'id' => 'patterns',
                'info' => '匹配项',
                'type' => 'textarea',
                'rows' => 7,
                'placeholder' => '每行一个域名、协议、CIDR 或端口',
            ],
            [
                'id' => 'match_type',
                'info' => '匹配类型',
                'type' => 'select',
                'select' => [
                    'domain_suffix' => '域名后缀',
                    'protocol' => '协议',
                    'ip_cidr' => 'IP/CIDR',
                    'port' => '端口',
                    'domain_regex' => '域名正则',
                ],
            ],
            [
                'id' => 'network',
                'info' => '网络',
                'type' => 'select',
                'select' => ['any' => '全部', 'tcp' => 'TCP', 'udp' => 'UDP'],
            ],
            [
                'id' => 'action',
                'info' => '动作',
                'type' => 'select',
                'select' => ['block' => '阻止并记录', 'log_only' => '仅记录'],
            ],
            [
                'id' => 'severity',
                'info' => '风险级别',
                'type' => 'select',
                'select' => ['high' => '高', 'critical' => '严重', 'medium' => '中', 'low' => '低'],
            ],
            [
                'id' => 'scope_type',
                'info' => '应用范围',
                'type' => 'select',
                'select' => ['all' => '所有节点', 'node' => '指定节点 ID', 'group' => '指定节点组 ID'],
            ],
            [
                'id' => 'scope_value',
                'info' => '范围 ID',
                'type' => 'input',
                'placeholder' => '所有节点时留空',
            ],
            [
                'id' => 'priority',
                'info' => '优先级',
                'type' => 'input',
                'placeholder' => '数字越小越优先，默认 100',
            ],
        ],
    ];

    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/detect.tpl')
        );
    }

    public function add(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        try {
            (new XNodeAuditService())->createRule((array) $request->getParsedBody());
        } catch (InvalidArgumentException $e) {
            return $response->withJson(['ret' => 0, 'msg' => $e->getMessage()]);
        }

        return $response->withJson(['ret' => 1, 'msg' => '添加成功，节点将在下一次同步时应用。']);
    }

    public function toggle(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $rule = (new XNodeAuditRule())->find((int) $args['id']);
        if ($rule === null) {
            return $response->withJson(['ret' => 0, 'msg' => '规则不存在。']);
        }

        $rule->enabled = (int) $rule->enabled === 1 ? 0 : 1;
        $rule->revision = (int) $rule->revision + 1;
        $rule->updated_at = time();
        $rule->save();

        return $response->withJson(['ret' => 1, 'msg' => (int) $rule->enabled === 1 ? '规则已启用。' : '规则已停用。']);
    }

    public function delete(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $rule = (new XNodeAuditRule())->find((int) $args['id']);
        if ($rule === null) {
            return $response->withJson(['ret' => 0, 'msg' => '规则不存在。']);
        }
        if ((int) $rule->managed === 1) {
            return $response->withJson(['ret' => 0, 'msg' => '系统托管规则不能删除，可以停用。']);
        }

        DB::connection()->transaction(static function () use ($rule): void {
            (new XNodeAuditRulePattern())->where('rule_id', (int) $rule->id)->delete();
            $rule->delete();
        });

        return $response->withJson(['ret' => 1, 'msg' => '删除成功。']);
    }

    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $rules = (new XNodeAuditRule())->orderBy('priority')->orderBy('id')->get();
        $canMutate = AdminPermissionService::allows($this->user, 'PUT', '/admin/detect/1/toggle');

        foreach ($rules as $rule) {
            $patterns = (new XNodeAuditRulePattern())
                ->where('rule_id', (int) $rule->id)
                ->orderBy('pattern')
                ->pluck('pattern')
                ->toArray();

            $rule->op = $canMutate ? $this->renderActions($rule) : '';
            $rule->patterns = $this->renderPatterns($patterns);
            $rule->name = $this->renderRuleName($rule);
            $rule->description = $this->renderDescription($rule);
            $rule->match_summary = $this->renderMatchSummary($rule);
            $rule->action_label = $this->renderAction($rule);
            $rule->scope = $this->renderScope($rule);
            $rule->enabled_label = $this->renderStatus($rule);
        }

        return $response->withJson(['rules' => $rules]);
    }

    private function renderActions(XNodeAuditRule $rule): string
    {
        $enabled = (int) $rule->enabled;
        $toggleLabels = [0 => '启用', 1 => '停用'];
        $toggleIcons = [0 => 'ti-player-play', 1 => 'ti-player-pause'];
        $toggleClasses = [0 => 'btn-primary', 1 => 'btn-outline-secondary'];
        $deleteButtons = [
            0 => sprintf(
                '<button type="button" class="btn btn-sm btn-outline-danger" title="删除规则" onclick="deleteRule(%d)"><i class="icon ti ti-trash"></i><span class="d-none d-xl-inline">删除</span></button>',
                (int) $rule->id
            ),
            1 => '',
        ];

        return sprintf(
            '<div class="audit-rule-actions"><button type="button" class="btn btn-sm %s" title="%s规则" onclick="toggleRule(%d)"><i class="icon ti %s"></i><span class="d-none d-xl-inline">%s</span></button>%s</div>',
            $toggleClasses[$enabled],
            $toggleLabels[$enabled],
            (int) $rule->id,
            $toggleIcons[$enabled],
            $toggleLabels[$enabled],
            $deleteButtons[(int) $rule->managed]
        );
    }

    private function renderRuleName(XNodeAuditRule $rule): string
    {
        $source = (string) $rule->source;
        $sourceLabels = [
            'system' => '系统默认',
            'user_complaint' => '用户投诉清单',
            'admin' => '管理员',
        ];
        $sourceClasses = [
            'system' => 'bg-blue-lt text-blue',
            'user_complaint' => 'bg-red-lt text-red',
            'admin' => 'bg-secondary-lt text-secondary',
        ];

        return sprintf(
            '<div class="audit-rule-name">%s</div><div class="audit-rule-meta"><span class="badge %s">%s</span><span>#%d</span><span>优先级 %d</span></div>',
            htmlspecialchars((string) $rule->name),
            $sourceClasses[$source] ?? $sourceClasses['admin'],
            $sourceLabels[$source] ?? $sourceLabels['admin'],
            (int) $rule->id,
            (int) $rule->priority
        );
    }

    private function renderDescription(XNodeAuditRule $rule): string
    {
        return sprintf(
            '<div class="audit-rule-description">%s</div>',
            htmlspecialchars(trim((string) $rule->description))
        );
    }

    private function renderMatchSummary(XNodeAuditRule $rule): string
    {
        $matchTypes = [
            'domain_suffix' => '域名后缀',
            'domain_regex' => '域名正则',
            'protocol' => '协议识别',
            'ip_cidr' => 'IP / CIDR',
            'port' => '端口',
        ];
        $networks = ['any' => '全部网络', 'tcp' => 'TCP', 'udp' => 'UDP'];
        $severityLabels = ['critical' => '严重', 'high' => '高风险', 'medium' => '中风险', 'low' => '低风险'];
        $severityClasses = [
            'critical' => 'bg-red text-red-fg',
            'high' => 'bg-orange-lt text-orange',
            'medium' => 'bg-yellow-lt text-yellow',
            'low' => 'bg-green-lt text-green',
        ];
        $matchType = (string) $rule->match_type;
        $network = (string) $rule->network;
        $severity = (string) $rule->severity;

        return sprintf(
            '<div class="audit-policy-stack"><span class="badge bg-azure-lt text-azure">%s</span><span class="badge %s">%s</span><span class="text-secondary small">%s</span></div>',
            $matchTypes[$matchType] ?? htmlspecialchars($matchType),
            $severityClasses[$severity] ?? $severityClasses['medium'],
            $severityLabels[$severity] ?? $severityLabels['medium'],
            $networks[$network] ?? $networks['any']
        );
    }

    private function renderAction(XNodeAuditRule $rule): string
    {
        $actions = [
            'block' => '<span class="badge bg-red-lt text-red"><i class="icon ti ti-ban me-1"></i>阻止并记录</span>',
            'log_only' => '<span class="badge bg-blue-lt text-blue"><i class="icon ti ti-file-description me-1"></i>仅记录</span>',
        ];

        return $actions[(string) $rule->action] ?? $actions['log_only'];
    }

    private function renderScope(XNodeAuditRule $rule): string
    {
        $scopeType = (string) $rule->scope_type;
        $scopeLabels = [
            'all' => '所有节点',
            'node' => '节点 ' . (int) $rule->scope_value,
            'group' => '节点组 ' . (int) $rule->scope_value,
        ];

        return sprintf(
            '<span class="badge bg-secondary-lt text-secondary">%s</span>',
            $scopeLabels[$scopeType] ?? $scopeLabels['all']
        );
    }

    private function renderStatus(XNodeAuditRule $rule): string
    {
        $enabled = (int) $rule->enabled;
        $textClasses = [0 => 'text-secondary', 1 => 'text-green'];
        $dotClasses = [0 => 'bg-secondary', 1 => 'bg-green'];
        $labels = [0 => '已停用', 1 => '已启用'];

        return sprintf(
            '<span class="audit-status %s"><span class="audit-status-dot %s"></span>%s</span>',
            $textClasses[$enabled],
            $dotClasses[$enabled],
            $labels[$enabled]
        );
    }

    private function renderPatterns(array $patterns): string
    {
        $patterns = array_map(
            static fn (mixed $pattern): string => htmlspecialchars((string) $pattern),
            $patterns
        );
        $count = count($patterns);

        if ($count === 0) {
            return '<span class="text-secondary">—</span>';
        }

        $preview = array_slice($patterns, 0, 2);
        $previewHtml = implode('', array_map(
            static fn (string $pattern): string => sprintf('<code class="audit-pattern-chip">%s</code>', $pattern),
            $preview
        ));

        if ($count <= 2) {
            return sprintf('<div class="audit-pattern-preview">%s</div>', $previewHtml);
        }

        $allPatterns = implode('', array_map(
            static fn (string $pattern): string => sprintf('<code class="audit-pattern-chip">%s</code>', $pattern),
            $patterns
        ));

        return sprintf(
            '<div class="audit-patterns"><div class="audit-pattern-preview">%s</div><details><summary><i class="icon ti ti-chevron-down me-1"></i>查看全部 %d 项</summary><div class="audit-pattern-list">%s</div></details></div>',
            $previewHtml,
            $count,
            $allPatterns
        );
    }
}
