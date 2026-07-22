<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Config;
use App\Models\Node;
use App\Models\NodeReportReceipt;
use App\Models\NodeRuntime;
use App\Models\NodeToken;
use App\Models\OnlineLog;
use App\Services\AdminPermissionService;
use App\Services\FixedNodeTrafficRatePolicy;
use App\Services\I18n;
use App\Services\NodeEnrollmentService;
use App\Services\NodeProbeService;
use App\Services\NodeProfileService;
use App\Services\Notification;
use App\Services\XNodeNodePolicy;
use App\Utils\Tools;
use GuzzleHttp\Exception\GuzzleException;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Smarty\Exception as SmartyException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use function date;
use function explode;
use function htmlspecialchars;
use function implode;
use function in_array;
use function is_string;
use function round;
use function rtrim;
use function str_replace;
use function strtolower;
use function time;
use function trim;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

final class NodeController extends BaseController
{
    private const XNODE_INSTALLER_URL = 'https://raw.githubusercontent.com/makeausername/xnode-agent/9f9cef203f0a37ed4c1301f6d96254824b40adc5/scripts/install.sh';
    private const XNODE_INSTALL_VERSION = 'v0.1.7';

    private static array $details = [
        'field' => [
            'op' => '操作',
            'id' => '节点ID',
            'name' => '名称',
            'server' => '地址',
            'type' => '状态',
            'sort' => '类型',
            'xnode_status' => 'XNode',
            'xnode_last_seen' => '最近心跳',
            'xnode_agent' => 'Agent',
            'xnode_audit' => '审计规则',
            'xnode_error' => '错误',
            'probe_status' => '可达性',
            'probe_checked_at' => '检测时间',
            'traffic_rate' => '倍率',
            'node_class' => '等级',
            'node_group' => '组别',
            'node_bandwidth_limit' => '流量限制/GB',
            'node_bandwidth' => '已用流量/GB',
            'bandwidthlimit_resetday' => '重置日',
        ],
    ];

    private static array $update_field = [
        'name',
        'server',
        'traffic_rate',
        'node_group',
        'node_speedlimit',
        'sort',
        'node_class',
        'node_bandwidth_limit',
        'bandwidthlimit_resetday',
    ];

    /**
     * 后台节点页面
     *
     * @throws SmartyException
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/node/index.tpl')
        );
    }

    /**
     * 后台创建节点页面
     *
     * @throws SmartyException
     */
    public function create(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('update_field', self::$update_field)
                ->fetch('admin/node/create.tpl')
        );
    }

    /**
     * 后台添加节点
     */
    public function add(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $node = new Node();

        $node->name = $request->getParam('name');
        $node->node_group = $request->getParam('node_group');
        $node->server = trim($request->getParam('server'));
        $node->traffic_rate = $request->getParam('traffic_rate') ?? 1;
        FixedNodeTrafficRatePolicy::apply($node);

        $custom_config = $request->getParam('custom_config') ?? '{}';

        if ($custom_config !== '') {
            $node->custom_config = $custom_config;
        } else {
            $node->custom_config = '{}';
        }

        $node->node_speedlimit = $request->getParam('node_speedlimit');
        $node->type = $request->getParam('type') === 'true' ? 1 : 0;
        $node->sort = (int) $request->getParam('sort');
        $node->node_class = $request->getParam('node_class');
        $node->node_bandwidth_limit = Tools::gbToB($request->getParam('node_bandwidth_limit'));
        $node->bandwidthlimit_resetday = $request->getParam('bandwidthlimit_resetday');
        $node->password = Tools::genRandomChar(32);

        if (XNodeNodePolicy::appliesTo($node->sort)) {
            XNodeNodePolicy::apply($node);
        }

        try {
            (new NodeProfileService())->validateCustomConfig((string) $node->custom_config, (int) $node->sort);
        } catch (InvalidArgumentException | JsonException $e) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '节点配置无效：' . $e->getMessage(),
            ]);
        }

        if (! $node->save()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '添加失败',
            ]);
        }

        try {
            (new NodeProfileService())->syncFromNode($node);
        } catch (InvalidArgumentException | JsonException $e) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '节点已创建，但 XNode 配置保存失败：' . $e->getMessage(),
            ]);
        }

        if (Config::obtain('im_bot_group_notify_add_node')) {
            try {
                Notification::notifyUserGroup(
                    str_replace(
                        '%node_name%',
                        $request->getParam('name'),
                        I18n::trans('bot.node_added', $_ENV['locale'])
                    )
                );
            } catch (TelegramSDKException | GuzzleException) {
                return $response->withJson([
                    'ret' => 1,
                    'msg' => '添加成功，但 IM Bot 通知失败',
                    'node_id' => $node->id,
                ]);
            }
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '添加成功',
            'node_id' => $node->id,
        ]);
    }

    /**
     * 后台编辑指定节点页面
     *
     * @throws SmartyException
     */
    public function edit(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $node = (new Node())->find($args['id']);

        if ($node === null) {
            return $response->withRedirect('/admin/node');
        }

        $runtime = (new NodeRuntime())->where('node_id', (int) $args['id'])->first();
        $nodeBandwidth = (int) $node->node_bandwidth;

        $node->sort = (int) $node->sort;

        $node->node_bandwidth = Tools::autoBytes($node->node_bandwidth);
        $node->node_bandwidth_limit = Tools::bToGB($node->node_bandwidth_limit);

        return $response->write(
            $this->view()
                ->assign('node', $node)
                ->assign(
                    'node_secret',
                    AdminPermissionService::role($this->user) === 'read_only'
                        ? Config::SECRET_MASK
                        : (string) $node->password
                )
                ->assign('xnode_summary', $this->buildXNodeEditSummary($runtime, (int) $node->id, $nodeBandwidth))
                ->assign('xnode_probe_summary', NodeProbeService::summarizeNode((int) $node->id))
                ->assign('update_field', self::$update_field)
                ->fetch('admin/node/edit.tpl')
        );
    }

    /**
     * 后台更新指定节点内容
     */
    public function update(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $node = (new Node())->find($args['id']);

        if ($node === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '节点不存在',
            ]);
        }

        $node->name = $request->getParam('name');
        $node->node_group = $request->getParam('node_group') ?? 0;
        $node->server = trim($request->getParam('server'));
        $node->traffic_rate = $request->getParam('traffic_rate') ?? 1;
        FixedNodeTrafficRatePolicy::apply($node);

        $custom_config = $request->getParam('custom_config') ?? '{}';

        if ($custom_config !== '') {
            $node->custom_config = $custom_config;
        } else {
            $node->custom_config = '{}';
        }

        $node->node_speedlimit = $request->getParam('node_speedlimit');
        $node->type = $request->getParam('type') === 'true' ? 1 : 0;
        $node->sort = (int) $request->getParam('sort');
        $node->node_class = $request->getParam('node_class');
        $node->node_bandwidth_limit = Tools::gbToB($request->getParam('node_bandwidth_limit'));
        $node->bandwidthlimit_resetday = $request->getParam('bandwidthlimit_resetday');

        if (XNodeNodePolicy::appliesTo($node->sort)) {
            XNodeNodePolicy::apply($node);
        }

        try {
            (new NodeProfileService())->validateCustomConfig((string) $node->custom_config, (int) $node->sort);
        } catch (InvalidArgumentException | JsonException $e) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '节点配置无效：' . $e->getMessage(),
            ]);
        }

        if (! $node->save()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '修改失败',
            ]);
        }

        try {
            (new NodeProfileService())->syncFromNode($node);
        } catch (InvalidArgumentException | JsonException $e) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '节点已更新，但 XNode 配置保存失败：' . $e->getMessage(),
            ]);
        }

        if (Config::obtain('im_bot_group_notify_update_node')) {
            try {
                Notification::notifyUserGroup(
                    str_replace(
                        '%node_name%',
                        $request->getParam('name'),
                        I18n::trans('bot.node_updated', $_ENV['locale'])
                    )
                );
            } catch (TelegramSDKException | GuzzleException) {
                return $response->withJson([
                    'ret' => 1,
                    'msg' => '修改成功，但 IM Bot 通知失败',
                ]);
            }
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '修改成功',
        ]);
    }

    public function resetPassword(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $node = (new Node())->find($args['id']);

        if ($node === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '节点不存在',
            ]);
        }

        $node->password = Tools::genRandomChar(32);
        $node->save();

        return $response->withJson([
            'ret' => 1,
            'msg' => '重置节点通讯密钥成功',
        ]);
    }

    public function resetBandwidth(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $node = (new Node())->find($args['id']);

        if ($node === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '节点不存在',
            ]);
        }

        $node->node_bandwidth = 0;
        $node->save();

        return $response->withJson([
            'ret' => 1,
            'msg' => '重置节点流量成功',
        ]);
    }

    public function generateXNodeInstallCommand(
        ServerRequest $request,
        Response $response,
        array $args
    ): ResponseInterface {
        $nodeId = (int) $args['id'];
        $node = (new Node())->where('id', $nodeId)->first();

        if ($node === null) {
            return $response->withStatus(404)->withJson([
                'ret' => 0,
                'msg' => 'Node not found',
            ]);
        }

        $nodeDomain = trim((string) $node->server);

        if ($nodeDomain === '') {
            return $response->withJson([
                'ret' => 0,
                'msg' => '请先填写节点连接地址',
            ]);
        }

        $ttlSeconds = 600;
        $token = NodeEnrollmentService::createEnrollTokenForNode($nodeId, $ttlSeconds);
        $enrollmentService = new NodeEnrollmentService();
        $tokenRecord = (new NodeToken())
            ->where('token_hash', $enrollmentService->hashToken($token))
            ->where('token_type', 'enroll')
            ->where('node_id', $nodeId)
            ->first();

        if ($tokenRecord === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => 'Enroll token was created, but the saved token record could not be verified.',
            ]);
        }

        $panelUrl = $this->resolvePanelUrl($request);
        $expiresAt = (int) ($tokenRecord->expires_at ?? time() + $ttlSeconds);
        $command = $this->buildXNodeOneClickInstallCommand($panelUrl, $nodeId, $nodeDomain, $token);

        return $response->withJson([
            'ret' => 1,
            'msg' => 'XNode one-click install command created',
            'token' => $token,
            'expires_in' => $ttlSeconds,
            'expires_at' => $expiresAt,
            'expires_at_text' => date('Y-m-d H:i:s', $expiresAt),
            'install_command' => $command,
            'command' => $command,
        ]);
    }

    /**
     * 后台删除指定节点
     */
    public function delete(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $node = (new Node())->find($args['id']);

        if ($node === null) {
            return $response->withStatus(404)->withJson([
                'ret' => 0,
                'msg' => 'Node not found',
            ]);
        }

        (new NodeToken())
            ->where('node_id', (int) $node->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => time()]);

        if (! $node->delete()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '删除失败',
            ]);
        }

        if (Config::obtain('im_bot_group_notify_delete_node')) {
            try {
                Notification::notifyUserGroup(
                    str_replace(
                        '%node_name%',
                        $node->name,
                        I18n::trans('bot.node_deleted', $_ENV['locale'])
                    )
                );
            } catch (TelegramSDKException | GuzzleException) {
                return $response->withJson([
                    'ret' => 1,
                    'msg' => '删除成功，但 IM Bot 通知失败',
                ]);
            }
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '删除成功',
        ]);
    }

    public function copy($request, $response, $args)
    {
        $old_node = (new Node())->find($args['id']);

        if ($old_node === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '节点不存在',
            ]);
        }

        $new_node = $old_node->replicate([
            'node_bandwidth',
        ]);
        $new_node->name .= ' (副本)';
        $new_node->node_bandwidth = 0;
        $new_node->password = Tools::genRandomChar(32);

        if (! $new_node->save()) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '复制失败',
            ]);
        }

        try {
            (new NodeProfileService())->syncFromNode($new_node);
        } catch (InvalidArgumentException | JsonException $e) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '节点已复制，但 XNode 配置保存失败：' . $e->getMessage(),
            ]);
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '复制成功',
        ]);
    }

    /**
     * 后台节点页面 AJAX
     */
    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $nodes = (new Node())->orderBy('id', 'desc')->get();
        $runtimeByNodeId = [];
        $nodeIds = $nodes->pluck('id')->toArray();
        $probeSummaries = NodeProbeService::summarizeNodes($nodeIds);

        if ($nodeIds !== []) {
            foreach ((new NodeRuntime())->whereIn('node_id', $nodeIds)->get() as $runtime) {
                $runtimeByNodeId[(int) $runtime->node_id] = $runtime;
            }
        }

        foreach ($nodes as $node) {
            $node->op = '<button class="btn btn-red" id="delete-node-' . $node->id . '"
            onclick="deleteNode(' . $node->id . ')">删除</button>
            <button class="btn btn-orange" id="copy-node-' . $node->id . '"
            onclick="copyNode(' . $node->id . ')">复制</button>
            <a class="btn btn-primary" href="/admin/node/' . $node->id . '/edit">编辑</a>';
            $xnodeFields = $this->buildXNodeRuntimeListFields($runtimeByNodeId[(int) $node->id] ?? null);
            $node->type = $node->type();
            $node->sort = $node->sort();
            $node->xnode_status = $xnodeFields['xnode_status'];
            $node->xnode_last_seen = $xnodeFields['xnode_last_seen'];
            $node->xnode_agent = $xnodeFields['xnode_agent'];
            $node->xnode_audit = $xnodeFields['xnode_audit'];
            $node->xnode_error = $xnodeFields['xnode_error'];
            $probeSummary = $probeSummaries[(int) $node->id] ?? NodeProbeService::summarizeNode((int) $node->id);
            $node->probe_status = $this->buildProbeStatusBadge(
                (string) $probeSummary['badge_class'],
                (string) $probeSummary['label']
            );
            $node->probe_checked_at = $this->formatXNodeTextValue($probeSummary['latest_checked_at'] ?? '-');
            $node->node_bandwidth = round(Tools::bToGB($node->node_bandwidth), 2);
            $node->node_bandwidth_limit = Tools::bToGB($node->node_bandwidth_limit);
        }

        return $response->withJson([
            'nodes' => $nodes,
        ]);
    }

    private function resolvePanelUrl(ServerRequest $request): string
    {
        $panelUrl = $_ENV['baseUrl'] ?? '';

        if (is_string($panelUrl) && trim($panelUrl) !== '') {
            return rtrim(trim($panelUrl), '/');
        }

        $uri = $request->getUri();
        $scheme = trim($request->getHeaderLine('X-Forwarded-Proto'));
        $scheme = $scheme === '' ? $uri->getScheme() : trim(explode(',', $scheme)[0]);
        $scheme = $scheme === '' ? 'https' : $scheme;
        $authority = trim($request->getHeaderLine('X-Forwarded-Host'));
        $authority = $authority === '' ? $uri->getAuthority() : trim(explode(',', $authority)[0]);
        $authority = $authority === '' ? trim($request->getHeaderLine('Host')) : $authority;

        if ($authority === '') {
            return 'https://panel.example.com';
        }

        return rtrim($scheme . '://' . $authority, '/');
    }

    private function buildXNodeOneClickInstallCommand(
        string $panelUrl,
        int $nodeId,
        string $nodeDomain,
        string $token
    ): string {
        return implode("\n", [
            'curl -fsSL ' . $this->quoteShellValue(self::XNODE_INSTALLER_URL) . ' | bash -s -- \\',
            '  --panel-url ' . $this->quoteShellValue($panelUrl) . ' \\',
            '  --node-id ' . $this->quoteShellValue((string) $nodeId) . ' \\',
            '  --node-domain ' . $this->quoteShellValue($nodeDomain) . ' \\',
            '  --enroll-token ' . $this->quoteShellValue($token) . ' \\',
            '  --version ' . $this->quoteShellValue(self::XNODE_INSTALL_VERSION),
        ]);
    }

    private function quoteShellValue(string $value): string
    {
        return "'" . str_replace("'", "'\"'\"'", $value) . "'";
    }

    private function buildXNodeEditSummary(?NodeRuntime $runtime, int $nodeId, int $nodeBandwidth): array
    {
        $status = $this->buildXNodeEditStatus($runtime);
        $lastError = trim((string) ($runtime->last_error ?? ''));

        return [
            'status_text' => $status['text'],
            'status_class' => $status['class'],
            'last_seen' => $this->formatXNodeLastSeen((int) ($runtime->last_seen ?? 0)),
            'agent_version' => $this->formatXNodeSummaryValue($runtime->agent_version ?? null),
            'core_version' => $this->formatXNodeSummaryValue($runtime->core_version ?? null),
            'audit_status' => $this->formatXNodeSummaryValue($runtime->audit_status ?? null),
            'audit_revision' => $this->formatXNodeSummaryValue($runtime->audit_revision ?? null),
            'audit_hash' => $this->formatXNodeSummaryValue($runtime->audit_hash ?? null),
            'audit_applied_at' => $this->formatXNodeTimestamp((int) ($runtime->audit_applied_at ?? 0)),
            'audit_error' => trim((string) ($runtime->audit_error ?? '')),
            'online_count' => (int) (new OnlineLog())
                ->where('node_id', $nodeId)
                ->where('last_time', '>', time() - 90)
                ->count(),
            'node_bandwidth' => Tools::autoBytes($nodeBandwidth),
            'latest_traffic_report' => $this->latestXNodeReportTime($nodeId, 'traffic'),
            'latest_online_report' => $this->latestXNodeReportTime($nodeId, 'online'),
            'latest_detect_log_report' => $this->latestXNodeReportTime($nodeId, 'detect-log'),
            'report_counts' => [
                'traffic' => $this->countXNodeReports($nodeId, 'traffic'),
                'online' => $this->countXNodeReports($nodeId, 'online'),
                'detect-log' => $this->countXNodeReports($nodeId, 'detect-log'),
            ],
            'last_error' => $lastError,
        ];
    }

    private function buildXNodeEditStatus(?NodeRuntime $runtime): array
    {
        if ($runtime === null) {
            return [
                'text' => '离线',
                'class' => 'bg-red text-red-fg',
            ];
        }

        $state = strtolower(trim((string) ($runtime->state ?? '')));
        $lastSeen = (int) ($runtime->last_seen ?? 0);
        $failedStates = ['failed', 'stopped', 'error'];
        $isOnline = $lastSeen > time() - 90 && ! in_array($state, $failedStates, true);

        if ($isOnline) {
            return [
                'text' => '在线',
                'class' => 'bg-green text-green-fg',
            ];
        }

        if (in_array($state, ['running', 'configured'], true)) {
            return [
                'text' => '心跳超时',
                'class' => 'bg-yellow text-yellow-fg',
            ];
        }

        return [
            'text' => '离线',
            'class' => 'bg-red text-red-fg',
        ];
    }

    private function latestXNodeReportTime(int $nodeId, string $reportType): string
    {
        $receipt = (new NodeReportReceipt())
            ->where('node_id', $nodeId)
            ->where('report_type', $reportType)
            ->orderBy('created_at', 'desc')
            ->first();

        return $this->formatXNodeTimestamp((int) ($receipt->created_at ?? 0));
    }

    private function countXNodeReports(int $nodeId, string $reportType): int
    {
        return (int) (new NodeReportReceipt())
            ->where('node_id', $nodeId)
            ->where('report_type', $reportType)
            ->count();
    }

    private function formatXNodeTimestamp(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '-';
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function formatXNodeSummaryValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '-';
        }

        return $value;
    }

    private function buildXNodeRuntimeListFields(?NodeRuntime $runtime): array
    {
        if ($runtime === null) {
            return [
                'xnode_status' => '-',
                'xnode_last_seen' => '-',
                'xnode_agent' => '-',
                'xnode_audit' => '-',
                'xnode_error' => '-',
            ];
        }

        $state = strtolower(trim((string) ($runtime->state ?? '')));
        $lastSeen = (int) ($runtime->last_seen ?? 0);
        $failedStates = ['failed', 'stopped', 'error'];
        $isOnline = $lastSeen > time() - 90 && ! in_array($state, $failedStates, true);

        if ($isOnline) {
            $status = $this->buildXNodeStatusBadge('bg-green text-green-fg', '在线');
        } elseif (in_array($state, ['running', 'configured'], true)) {
            $status = $this->buildXNodeStatusBadge('bg-yellow text-yellow-fg', '心跳超时');
        } else {
            $status = $this->buildXNodeStatusBadge('bg-red text-red-fg', '离线');
        }

        return [
            'xnode_status' => $status,
            'xnode_last_seen' => $this->formatXNodeLastSeen($lastSeen),
            'xnode_agent' => $this->formatXNodeTextValue($runtime->agent_version ?? null),
            'xnode_audit' => $this->formatXNodeTextValue($runtime->audit_status ?? null),
            'xnode_error' => $this->formatXNodeTextValue($runtime->last_error ?? null),
        ];
    }

    private function buildXNodeStatusBadge(string $className, string $text): string
    {
        return '<span class="badge ' . $className . '">' . $text . '</span>';
    }

    private function formatXNodeLastSeen(int $lastSeen): string
    {
        if ($lastSeen <= 0) {
            return '-';
        }

        $secondsAgo = time() - $lastSeen;

        if ($secondsAgo < 0) {
            $secondsAgo = 0;
        }

        return date('Y-m-d H:i:s', $lastSeen) . ' (' . $secondsAgo . '秒前)';
    }

    private function formatXNodeTextValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return '-';
        }

        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function buildProbeStatusBadge(string $className, string $text): string
    {
        return '<span class="badge '
            . htmlspecialchars($className, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">'
            . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</span>';
    }
}
