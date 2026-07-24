<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DetectRule;
use App\Models\Node;
use App\Models\NodeProfile;
use App\Models\User;
use InvalidArgumentException;
use JsonException;
use function date;
use function hash;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function max;
use function strcasecmp;
use function trim;
use const JSON_PRESERVE_ZERO_FRACTION;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final class NodeProfileService
{
    private const MOCK_USER_UUID = '11111111-1111-1111-1111-111111111111';
    private const DEFAULT_REALITY_SERVER_NAME = 'www.cloudflare.com';

    /**
     * @param int|string $nodeId
     *
     * @throws JsonException
     */
    public function buildDefaultConfig($nodeId, string $domain): array
    {
        $node = (new Node())->find((int) $nodeId);
        if ($node === null) {
            throw new InvalidArgumentException('Node does not exist.');
        }

        $domain = trim($domain) !== '' ? trim($domain) : trim((string) $node->server);
        if ($domain === '') {
            throw new InvalidArgumentException('Node domain is empty.');
        }

        $storedProfile = (new NodeProfile())
            ->where('node_id', (int) $node->id)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();
        $profile = $storedProfile === null
            ? $this->profileFromCustomConfig((string) ($node->custom_config ?? '{}'), (int) $node->sort)
            : $this->decodeStoredProfile((string) $storedProfile->profile_json);

        $config = $this->buildConfig(
            (int) $node->id,
            $domain,
            $profile,
            max(1, (int) ($storedProfile->version ?? 1))
        );
        $config['config_hash'] = hash('sha256', $this->canonicalJson($config));

        return $config;
    }

    /**
     * Validate and persist the XNode portion of a node's custom configuration.
     * The version changes only when the effective profile changes.
     *
     * @throws JsonException
     */
    public function syncFromNode(Node $node): void
    {
        if ((int) $node->sort !== 15) {
            return;
        }

        $profile = $this->profileFromCustomConfig((string) ($node->custom_config ?? '{}'), 15);
        $profileJson = $this->canonicalJson($profile);
        $stored = (new NodeProfile())
            ->where('node_id', (int) $node->id)
            ->orderByDesc('version')
            ->orderByDesc('id')
            ->first();

        if ($stored !== null && $this->canonicalJson($this->decodeStoredProfile((string) $stored->profile_json)) === $profileJson) {
            return;
        }

        $profileRecord = $stored ?? new NodeProfile();
        $profileRecord->node_id = (int) $node->id;
        $profileRecord->profile_json = $profileJson;
        $profileRecord->version = max(1, (int) ($stored->version ?? 0) + 1);
        $profileRecord->created_at = (int) ($stored->created_at ?? time());
        $profileRecord->updated_at = time();

        if (! $profileRecord->save()) {
            throw new InvalidArgumentException('Unable to save the node profile.');
        }
    }

    /**
     * @throws JsonException
     */
    public function validateCustomConfig(string $customConfig, int $sort): void
    {
        $this->profileFromCustomConfig($customConfig, $sort);
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

        $query = (new User())
            ->whereNotNull('uuid')
            ->where('is_banned', 0)
            ->where(static function ($query): void {
                $query->where('is_admin', 1)
                    ->orWhere(static function ($query): void {
                        $query->whereNull('unpaid_delete_at')
                            ->where('class', '>', 0)
                            ->where('class_expire', '>', date('Y-m-d H:i:s'));
                    });
            })
            ->where(static function ($query): void {
                $query->where('is_admin', 1)
                    ->orWhereRaw('`transfer_enable` > (`u` + `d`)');
            })
            ->where(static function ($query) use ($node): void {
                $query->where('is_admin', 1)
                    ->orWhere(static function ($query) use ($node): void {
                        $query->where('class', '>=', (int) $node->node_class);
                        if ((int) $node->node_group !== 0) {
                            $query->where('node_group', (int) $node->node_group);
                        }
                    });
            })
            ->orderBy('id');

        return $query
            ->get([
                'id',
                'uuid',
                'is_admin',
                'is_banned',
                'class',
                'class_expire',
                'node_group',
                'unpaid_delete_at',
                'transfer_enable',
                'u',
                'd',
            ])
            ->filter(static fn (User $user): bool => self::canUserUseNode($user, $node))
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

    public static function canUserUseNode(
        User $user,
        Node $node,
        bool $requireRemainingTraffic = true
    ): bool {
        $uuid = trim((string) $user->uuid);

        if (in_array(true, [
            $uuid === '',
            strcasecmp($uuid, self::MOCK_USER_UUID) === 0,
        ], true)) {
            return false;
        }

        $hasAccess = $requireRemainingTraffic
            ? UserAccessPolicy::canUseNodes($user)
            : UserAccessPolicy::hasActivePlan($user);

        if (! $hasAccess) {
            return false;
        }

        if ((int) $user->is_admin === 1) {
            return true;
        }

        $nodeGroup = (int) $node->node_group;

        return ! in_array(false, [
            (int) $user->class >= (int) $node->node_class,
            in_array($nodeGroup, [0, (int) $user->node_group], true),
        ], true);
    }

    /**
     * Database failures intentionally propagate so the API can return a 503
     * instead of silently replacing live rules with a fake rule.
     */
    public function buildDetectRules(): array
    {
        return (new DetectRule())->get()
            ->map(static fn (DetectRule $rule): array => [
                'id' => (int) $rule->id,
                'type' => 'protocol',
                'pattern' => (string) $rule->regex,
            ])
            ->values()
            ->toArray();
    }

    /**
     * @throws JsonException
     */
    private function profileFromCustomConfig(string $customConfig, int $sort): array
    {
        $decoded = json_decode(trim($customConfig) === '' ? '{}' : $customConfig, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Custom config must be a JSON object.');
        }

        if ($sort !== 15) {
            return $this->normalizeProfile([]);
        }

        $xnode = $decoded['xnode'] ?? [];
        if (! is_array($xnode)) {
            throw new InvalidArgumentException('The xnode config must be a JSON object.');
        }

        return $this->normalizeProfile($xnode);
    }

    /**
     * @throws JsonException
     */
    private function decodeStoredProfile(string $profileJson): array
    {
        $decoded = json_decode($profileJson, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Stored node profile is invalid.');
        }

        return $this->normalizeProfile($decoded);
    }

    private function normalizeProfile(array $profile): array
    {
        $port = (int) ($profile['port'] ?? 443);
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('XNode port must be between 1 and 65535.');
        }

        $serverName = trim((string) ($profile['sni'] ?? self::DEFAULT_REALITY_SERVER_NAME));
        if ($serverName === '') {
            throw new InvalidArgumentException('XNode SNI cannot be empty.');
        }

        $target = trim((string) ($profile['target'] ?? $serverName . ':443'));
        if ($target === '') {
            throw new InvalidArgumentException('XNode Reality target cannot be empty.');
        }

        $serverNames = $profile['server_names'] ?? [$serverName];
        if (! is_array($serverNames)) {
            throw new InvalidArgumentException('XNode server_names must be an array.');
        }
        $serverNames = array_values(array_filter(array_map(
            static fn (mixed $name): string => trim((string) $name),
            $serverNames
        )));
        if ($serverNames === []) {
            $serverNames = [$serverName];
        }

        return [
            'name' => trim((string) ($profile['profile'] ?? $profile['name'] ?? 'vless-reality-vision')),
            'protocol' => trim((string) ($profile['protocol'] ?? 'vless')),
            'network' => trim((string) ($profile['network'] ?? 'raw')),
            'security' => trim((string) ($profile['security'] ?? 'reality')),
            'flow' => trim((string) ($profile['flow'] ?? 'xtls-rprx-vision')),
            'listen' => trim((string) ($profile['listen'] ?? '0.0.0.0')),
            'port' => $port,
            'target' => $target,
            'server_names' => $serverNames,
            'fingerprint' => trim((string) ($profile['fingerprint'] ?? 'chrome')),
            'report' => [
                'user_sync_interval_sec' => max(10, (int) ($profile['user_sync_interval_sec'] ?? 60)),
                'traffic_interval_sec' => max(10, (int) ($profile['traffic_interval_sec'] ?? 60)),
                'online_interval_sec' => max(10, (int) ($profile['online_interval_sec'] ?? 60)),
                'heartbeat_interval_sec' => max(10, (int) ($profile['heartbeat_interval_sec'] ?? 30)),
            ],
        ];
    }

    private function buildConfig(int $nodeId, string $domain, array $profile, int $version): array
    {
        return [
            'schema_version' => 1,
            'version' => $version,
            'node_id' => $nodeId,
            'domain' => $domain,
            'profile' => [
                'name' => $profile['name'],
                'protocol' => $profile['protocol'],
                'network' => $profile['network'],
                'security' => $profile['security'],
                'flow' => $profile['flow'],
                'listen' => $profile['listen'],
                'port' => $profile['port'],
            ],
            'reality' => [
                'target' => $profile['target'],
                'server_names' => $profile['server_names'],
                'fingerprint' => $profile['fingerprint'],
            ],
            'report' => $profile['report'],
        ];
    }

    /**
     * @throws JsonException
     */
    private function canonicalJson(array $value): string
    {
        $this->sortRecursively($value);

        return json_encode(
            $value,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    private function sortRecursively(array &$value): void
    {
        foreach ($value as &$entry) {
            if (is_array($entry)) {
                $this->sortRecursively($entry);
            }
        }
        unset($entry);

        if (! array_is_list($value)) {
            ksort($value);
        }
    }
}
