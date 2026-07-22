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
use function implode;
use function time;

final class DetectRuleController extends BaseController
{
    private static array $details = [
        'field' => [
            'op' => '操作',
            'id' => 'ID',
            'name' => '名称',
            'description' => '说明',
            'patterns' => '匹配项',
            'match_type' => '匹配类型',
            'network' => '网络',
            'action' => '动作',
            'scope' => '范围',
            'enabled_label' => '状态',
            'source_label' => '来源',
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
            $rule->op = '<button class="btn btn-sm btn-primary me-1" onclick="toggleRule(' . (int) $rule->id . ')">' . $toggleLabel . '</button>';
            if ((int) $rule->managed !== 1) {
                $rule->op .= '<button class="btn btn-sm btn-red" onclick="deleteRule(' . (int) $rule->id . ')">删除</button>';
            }
            $patterns = (new XNodeAuditRulePattern())
                ->where('rule_id', (int) $rule->id)
                ->orderBy('pattern')
                ->pluck('pattern')
                ->toArray();
            $rule->patterns = implode('<br>', array_map('htmlspecialchars', $patterns));
            $rule->name = htmlspecialchars((string) $rule->name);
            $rule->description = htmlspecialchars((string) $rule->description);
            $rule->scope = (string) $rule->scope_type === 'all'
                ? '所有节点'
                : ((string) $rule->scope_type === 'node' ? '节点 ' : '节点组 ') . (int) $rule->scope_value;
            $rule->enabled_label = (int) $rule->enabled === 1 ? '已启用' : '已停用';
            $rule->source_label = match ((string) $rule->source) {
                'system' => '系统默认',
                'user_complaint' => '用户投诉清单',
                default => '管理员',
            };
        }

        return $response->withJson(['rules' => $rules]);
    }
}
