<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\XNodeAuditRule;
use App\Models\XNodeAuditRulePattern;
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

        DB::connection()->transaction(function () use ($rule): void {
            (new XNodeAuditRulePattern())->where('rule_id', (int) $rule->id)->delete();
            $rule->delete();
        });

        return $response->withJson(['ret' => 1, 'msg' => '删除成功。']);
    }

    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $rules = (new XNodeAuditRule())->orderBy('priority')->orderBy('id')->get();

        foreach ($rules as $rule) {
            $toggleLabel = (int) $rule->enabled === 1 ? '停用' : '启用';
            $toggleIcon = (int) $rule->enabled === 1 ? 'ti-player-pause' : 'ti-player-play';
            $toggleClass = (int) $rule->enabled === 1 ? 'btn-outline-secondary' : 'btn-primary';
            $rule->op = '<div class="audit-rule-actions">'
                . '<button type="button" class="btn btn-sm ' . $toggleClass . '" title="' . $toggleLabel . '规则" '
                . 'onclick="toggleRule(' . (int) $rule->id . ')">'
                . '<i class="icon ti ' . $toggleIcon . '"></i><span class="d-none d-xl-inline">' . $toggleLabel . '</span></button>';
            if ((int) $rule->managed !== 1) {
                $rule->op .= '<button type="button" class="btn btn-sm btn-outline-danger" title="删除规则" '
                    . 'onclick="deleteRule(' . (int) $rule->id . ')">'
                    . '<i class="icon ti ti-trash"></i><span class="d-none d-xl-inline">删除</span></button>';
            }
            $rule->op .= '</div>';

            $patterns = (new XNodeAuditRulePattern())
                ->where('rule_id', (int) $rule->id)
                ->orderBy('pattern')
                ->pluck('pattern')
                ->toArray();
            $rule->patterns = $this->renderPatterns($patterns);

            $sourceLabel = match ((string) $rule->source) {
                'system' => '系统默认',
                'user_complaint' => '用户投诉清单',
                default => '管理员',
            };
            $sourceClass = match ((string) $rule->source) {
                'system' => 'bg-blue-lt text-blue',
                'user_complaint' => 'bg-red-lt text-red',
                default => 'bg-secondary-lt text-secondary',
            };
            $rule->name = '<div class="audit-rule-name">' . htmlspecialchars((string) $rule->name) . '</div>'
                . '<div class="audit-rule-meta"><span class="badge ' . $sourceClass . '">' . $sourceLabel . '</span>'
                . '<span>#' . (int) $rule->id . '</span><span>优先级 ' . (int) $rule->priority . '</span></div>';

            $description = trim((string) $rule->description);
            $rule->description = '<div class="audit-rule-description">'
                . ($description === '' ? '—' : htmlspecialchars($description)) . '</div>';

            $matchTypeLabel = match ((string) $rule->match_type) {
                'domain_suffix' => '域名后缀',
                'domain_regex' => '域名正则',
                'protocol' => '协议识别',
                'ip_cidr' => 'IP / CIDR',
                'port' => '端口',
                default => htmlspecialchars((string) $rule->match_type),
            };
            $networkLabel = match ((string) $rule->network) {
                'tcp' => 'TCP',
                'udp' => 'UDP',
                default => '全部网络',
            };
            $severityLabel = match ((string) $rule->severity) {
                'critical' => '严重',
                'high' => '高风险',
                'low' => '低风险',
                default => '中风险',
            };
            $severityClass = match ((string) $rule->severity) {
                'critical' => 'bg-red text-red-fg',
                'high' => 'bg-orange-lt text-orange',
                'low' => 'bg-green-lt text-green',
                default => 'bg-yellow-lt text-yellow',
            };
            $rule->match_summary = '<div class="audit-policy-stack">'
                . '<span class="badge bg-azure-lt text-azure">' . $matchTypeLabel . '</span>'
                . '<span class="badge ' . $severityClass . '">' . $severityLabel . '</span>'
                . '<span class="text-secondary small">' . $networkLabel . '</span></div>';

            $rule->action_label = (string) $rule->action === 'block'
                ? '<span class="badge bg-red-lt text-red"><i class="icon ti ti-ban me-1"></i>阻止并记录</span>'
                : '<span class="badge bg-blue-lt text-blue"><i class="icon ti ti-file-description me-1"></i>仅记录</span>';

            $scopeLabel = (string) $rule->scope_type === 'all'
                ? '所有节点'
                : ((string) $rule->scope_type === 'node' ? '节点 ' : '节点组 ') . (int) $rule->scope_value;
            $rule->scope = '<span class="badge bg-secondary-lt text-secondary">' . $scopeLabel . '</span>';

            $enabled = (int) $rule->enabled === 1;
            $rule->enabled_label = '<span class="audit-status ' . ($enabled ? 'text-green' : 'text-secondary') . '">'
                . '<span class="audit-status-dot ' . ($enabled ? 'bg-green' : 'bg-secondary') . '"></span>'
                . ($enabled ? '已启用' : '已停用') . '</span>';
        }

        return $response->withJson(['rules' => $rules]);
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

        $preview = array_slice($patterns, 0, $count > 3 ? 2 : $count);
        $previewHtml = implode('', array_map(
            static fn (string $pattern): string => '<code class="audit-pattern-chip">' . $pattern . '</code>',
            $preview
        ));

        if ($count <= 3) {
            return '<div class="audit-pattern-preview">' . $previewHtml . '</div>';
        }

        $allPatterns = implode('', array_map(
            static fn (string $pattern): string => '<code class="audit-pattern-chip">' . $pattern . '</code>',
            $patterns
        ));

        return '<div class="audit-patterns"><div class="audit-pattern-preview">' . $previewHtml . '</div>'
            . '<details><summary><i class="icon ti ti-chevron-down me-1"></i>查看全部 ' . $count . ' 项</summary>'
            . '<div class="audit-pattern-list">' . $allPatterns . '</div></details></div>';
    }
}
