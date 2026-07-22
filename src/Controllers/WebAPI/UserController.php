<?php

declare(strict_types=1);

namespace App\Controllers\WebAPI;

use App\Controllers\BaseController;
use App\Models\Node;
use App\Models\OnlineLog;
use App\Models\User;
use App\Services\NodeRuntimeService;
use App\Services\UserAccessPolicy;
use App\Utils\ResponseHelper;
use App\Utils\Tools;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function count;
use function date;
use function is_array;
use function json_decode;
use function time;

final class UserController extends BaseController
{
    private const MAX_BATCH_ITEMS = 1000;

    /**
     * GET /mod_mu/users
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $nodeId = $this->authenticatedNodeId($request);
        $node = $nodeId === null ? null : (new Node())->find($nodeId);

        if ($node === null) {
            return ResponseHelper::error($response, 'Node not found.', 404);
        }

        if ((int) $node->type === 0) {
            return ResponseHelper::error($response, 'Node is not enabled.', 403);
        }

        $node->update(['node_heartbeat' => time()]);

        if ($node->node_bandwidth_limit !== 0 && $node->node_bandwidth_limit <= $node->node_bandwidth) {
            return ResponseHelper::error($response, 'Node out of bandwidth.', 403);
        }

        $usersRaw = (new User())->where(static function ($query) use ($node): void {
            $query->where('is_admin', 1)
                ->orWhere(static function ($query) use ($node): void {
                    $query->where('is_banned', 0)
                        ->whereNull('unpaid_delete_at')
                        ->where('class', '>', 0)
                        ->where('class', '>=', $node->node_class)
                        ->where('class_expire', '>', date('Y-m-d H:i:s'))
                        ->where(static function ($query) use ($node): void {
                            if ($node->node_group !== 0) {
                                $query->where('node_group', $node->node_group);
                            }
                        });
                });
        })->get([
            'id',
            'is_admin',
            'is_banned',
            'class',
            'class_expire',
            'unpaid_delete_at',
            'u',
            'd',
            'transfer_enable',
            'node_speedlimit',
            'node_iplimit',
            'method',
            'port',
            'passwd',
            'uuid',
        ]);

        $keysUnset = match ($node->sort) {
            14, 11 => ['u', 'd', 'transfer_enable', 'method', 'port', 'passwd', 'node_iplimit'],
            2 => ['u', 'd', 'transfer_enable', 'method', 'port', 'node_iplimit'],
            1 => ['u', 'd', 'transfer_enable', 'method', 'port', 'uuid', 'node_iplimit'],
            default => ['u', 'd', 'transfer_enable', 'uuid', 'node_iplimit']
        };

        $users = [];

        foreach ($usersRaw as $userRaw) {
            if (! UserAccessPolicy::hasActivePlan($userRaw)) {
                continue;
            }

            if ($userRaw->transfer_enable <= $userRaw->u + $userRaw->d) {
                if ($_ENV['keep_connect']) {
                    $userRaw->node_speedlimit = 1;
                } else {
                    continue;
                }
            }

            if ($userRaw->node_iplimit !== 0
                && $userRaw->node_iplimit < (new OnlineLog())
                    ->where('user_id', $userRaw->id)
                    ->where('last_time', '>', time() - 90)
                    ->count()) {
                continue;
            }

            if ($node->sort === 1) {
                $method = json_decode($node->custom_config)->method ?? '2022-blake3-aes-128-gcm';
                $userPk = Tools::genSs2022UserPk($userRaw->passwd, $method);

                if (! $userPk) {
                    continue;
                }

                $userRaw->passwd = $userPk;
            }

            foreach ($keysUnset as $key) {
                unset($userRaw->{$key});
            }

            foreach (['is_admin', 'is_banned', 'class', 'class_expire', 'unpaid_delete_at'] as $key) {
                unset($userRaw->{$key});
            }

            $users[] = $userRaw;
        }

        return ResponseHelper::successWithDataEtag($request, $response, $users);
    }

    /**
     * POST /mod_mu/users/traffic
     */
    public function addTraffic(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $this->acceptReport($request, $response, 'traffic');
    }

    /**
     * POST /mod_mu/users/aliveip
     */
    public function addAliveIp(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $this->acceptReport($request, $response, 'online');
    }

    /**
     * POST /mod_mu/users/detectlog
     */
    public function addDetectLog(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $this->acceptReport($request, $response, 'detect-log');
    }

    private function acceptReport(ServerRequest $request, Response $response, string $type): ResponseInterface
    {
        $payload = json_decode((string) $request->getBody(), true);

        if (! is_array($payload) || ! is_array($payload['data'] ?? null)) {
            return ResponseHelper::error($response, 'Invalid data.', 400);
        }

        if (count($payload['data']) > self::MAX_BATCH_ITEMS) {
            return ResponseHelper::error($response, 'Report batch is too large.', 413);
        }

        if (! isset($payload['report_id'])) {
            $payload['report_id'] = $request->getHeaderLine('X-Report-Id');
        }

        $nodeId = $this->authenticatedNodeId($request);
        $service = new NodeRuntimeService();
        $result = match ($type) {
            'traffic' => $service->acceptTraffic($payload, $nodeId),
            'online' => $service->acceptOnline($payload, $nodeId),
            default => $service->acceptDetectLog($payload, $nodeId),
        };

        if (! ($result['accepted'] ?? false)) {
            return ResponseHelper::errorWithData(
                $response,
                'Report rejected.',
                $result,
                422
            );
        }

        return ResponseHelper::successWithData($response, 'ok', $result);
    }

    private function authenticatedNodeId(ServerRequest $request): ?int
    {
        $nodeId = (int) $request->getAttribute('legacy_node_id', 0);

        return $nodeId > 0 ? $nodeId : null;
    }
}
