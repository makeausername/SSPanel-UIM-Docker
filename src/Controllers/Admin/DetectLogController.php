<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Node;
use App\Models\XNodeAuditEvent;
use App\Models\XNodeAuditRule;
use App\Utils\Tools;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class DetectLogController extends BaseController
{
    private static array $details = [
        'field' => [
            'id' => '事件 ID',
            'user_id' => '用户 ID',
            'node_id' => '节点 ID',
            'node_name' => '节点名称',
            'rule_name' => '规则',
            'source_ip' => '来源 IP',
            'target' => '目标',
            'protocol' => '协议',
            'action' => '动作',
            'observed_at_label' => '时间',
        ],
    ];

    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()->assign('details', self::$details)->fetch('admin/log/detect.tpl')
        );
    }

    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $length = max(1, min(100, (int) $request->getParam('length', 10)));
        $page = (int) floor((int) $request->getParam('start', 0) / $length) + 1;
        $draw = (int) $request->getParam('draw', 0);
        $events = XNodeAuditEvent::query();
        $search = trim((string) ($request->getParam('search')['value'] ?? ''));

        if ($search !== '') {
            $events->where(static function ($query) use ($search): void {
                $query->where('user_id', $search)
                    ->orWhere('rule_id', $search)
                    ->orWhere('node_id', $search)
                    ->orWhere('target_host', 'like', '%' . $search . '%');
            });
        }

        $filtered = $events->count();
        $total = XNodeAuditEvent::query()->count();
        $logs = $events->orderBy('id', 'desc')->paginate($length, '*', '', $page);

        foreach ($logs as $log) {
            $node = (new Node())->find((int) $log->node_id);
            $rule = (new XNodeAuditRule())->find((int) $log->rule_id);
            $log->node_name = htmlspecialchars((string) ($node?->name ?? '-'));
            $log->rule_name = htmlspecialchars((string) ($rule?->name ?? ('规则 ' . (int) $log->rule_id)));
            $host = htmlspecialchars((string) ($log->target_host ?? '-'));
            $log->target = $log->target_port === null ? $host : $host . ':' . (int) $log->target_port;
            $log->source_ip = htmlspecialchars((string) ($log->source_ip ?? '-'));
            $log->protocol = htmlspecialchars((string) ($log->protocol ?? '-'));
            $log->action = (string) $log->action === 'block' ? '已阻止' : '仅记录';
            $log->observed_at_label = Tools::toDateTime((int) $log->observed_at);
        }

        return $response->withJson([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'logs' => $logs,
        ]);
    }
}
