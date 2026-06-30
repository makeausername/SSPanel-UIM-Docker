<?php

declare(strict_types=1);

namespace App\Services;

final class NodeProfileService
{
    /**
     * @param int|string $nodeId
     */
    public function buildDefaultConfig($nodeId, string $domain): array
    {
        return [
            'schema_version' => 1,
            'node_id' => $nodeId,
            'domain' => $domain,
            'profile' => [
                'name' => 'vless-reality-vision',
                'protocol' => 'vless',
                'network' => 'raw',
                'security' => 'reality',
                'flow' => 'xtls-rprx-vision',
                'listen' => '0.0.0.0',
                'port' => 443,
            ],
            'reality' => [
                'target' => 'www.microsoft.com:443',
                'server_names' => [
                    'www.microsoft.com',
                ],
                'fingerprint' => 'chrome',
            ],
            'report' => [
                'user_sync_interval_sec' => 60,
                'traffic_interval_sec' => 60,
                'online_interval_sec' => 60,
                'heartbeat_interval_sec' => 30,
            ],
            'config_hash' => 'stub-config-v1',
        ];
    }

    public function buildMockUsers(int $updatedAt): array
    {
        return [
            [
                'id' => 10001,
                'uuid' => '11111111-1111-1111-1111-111111111111',
                'email' => 'user-10001@panel.local',
                'speed_limit_mbps' => 0,
                'ip_limit' => 0,
                'enabled' => true,
                'updated_at' => $updatedAt,
            ],
        ];
    }

    public function buildMockDetectRules(): array
    {
        return [
            [
                'id' => 1,
                'type' => 'protocol',
                'pattern' => 'bittorrent',
            ],
        ];
    }
}
