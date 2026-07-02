<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DetectRule;
use App\Models\Node;
use App\Models\User;
use Throwable;
use function is_string;
use function strcasecmp;
use function trim;

final class NodeProfileService
{
    private const MOCK_USER_UUID = '11111111-1111-1111-1111-111111111111';
    private const DEFAULT_REALITY_SERVER_NAME = 'www.cloudflare.com';

    /**
     * @param int|string $nodeId
     */
    public function buildDefaultConfig($nodeId, string $domain): array
    {
        $domain = $this->resolveDomain($nodeId, $domain);

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
                'target' => self::DEFAULT_REALITY_SERVER_NAME . ':443',
                'server_names' => [
                    self::DEFAULT_REALITY_SERVER_NAME,
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

    public function buildMockUsers(int $updatedAt, ?int $nodeId = null): array
    {
        return [
            [
                'id' => 10001,
                'uuid' => self::MOCK_USER_UUID,
                'email' => 'user-10001@panel.local',
                'speed_limit_mbps' => 0,
                'ip_limit' => 0,
                'enabled' => true,
                'updated_at' => $updatedAt,
            ],
        ];
    }

    public function buildUsersForNode(int $nodeId): array
    {
        $node = (new Node())->find($nodeId);

        if ($node === null) {
            return [];
        }

        $nodeClass = (int) $node->node_class;
        $nodeGroup = (int) $node->node_group;

        return (new User())
            ->orderBy('id')
            ->get(['id', 'uuid', 'is_banned', 'class', 'node_group'])
            ->filter(static function (User $user) use ($nodeClass, $nodeGroup): bool {
                $uuid = trim((string) $user->uuid);

                if ($uuid === '' || strcasecmp($uuid, self::MOCK_USER_UUID) === 0) {
                    return false;
                }

                if ((int) $user->is_banned !== 0 || (int) $user->class < $nodeClass) {
                    return false;
                }

                return $nodeGroup === 0 || (int) $user->node_group === $nodeGroup;
            })
            ->map(static fn (User $user): array => [
                'id' => (int) $user->id,
                'uuid' => trim((string) $user->uuid),
                'email' => 'user-' . (int) $user->id . '@panel.local',
                'speed_limit_mbps' => 0,
                'ip_limit' => 0,
                'enabled' => true,
                'updated_at' => 0,
            ])
            ->values()
            ->toArray();
    }

    public function buildMockDetectRules(): array
    {
        try {
            $rules = (new DetectRule())->get();

            if ($rules->isNotEmpty()) {
                return $rules->map(static fn (DetectRule $rule): array => [
                    'id' => (int) $rule->id,
                    'type' => 'protocol',
                    'pattern' => $rule->regex,
                ])->toArray();
            }
        } catch (Throwable) {
            // Keep the endpoint available during early XNode rollout even if detect_list is not migrated.
        }

        return [
            [
                'id' => 1,
                'type' => 'protocol',
                'pattern' => 'bittorrent',
            ],
        ];
    }

    /**
     * @param int|string $nodeId
     */
    private function resolveDomain($nodeId, string $domain): string
    {
        if (trim($domain) !== '') {
            return trim($domain);
        }

        try {
            $node = (new Node())->where('id', $nodeId)->first();
            $server = $node?->getAttribute('server');

            if (is_string($server) && trim($server) !== '') {
                return trim($server);
            }
        } catch (Throwable) {
            // Fallback keeps the skeleton config endpoint usable without widening data dependencies.
        }

        return 'node1.example.com';
    }
}
