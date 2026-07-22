<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;
use App\Models\DetectLog;
use App\Models\HourlyUsage;
use App\Models\Node;
use App\Models\NodeReportReceipt;
use App\Models\NodeRuntime;
use App\Models\OnlineLog;
use App\Models\User;
use App\Models\XNodeAuditEvent;
use App\Models\XNodeAuditRule;
use App\Utils\Tools;
use Illuminate\Database\QueryException;
use function array_key_exists;
use function date;
use function hash;
use function hash_equals;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_numeric;
use function is_scalar;
use function is_string;
use function json_decode;
use function json_encode;
use function ksort;
use function max;
use function strlen;
use function strtolower;
use function substr;
use function time;
use function trim;

final class NodeRuntimeService
{
    private const REALITY_METADATA_ERROR_CODES = [
        'reality_metadata_invalid',
        'reality_metadata_hash_mismatch',
    ];

    public function acceptRuntime(array $payload = [], ?int $nodeId = null): array
    {
        if ($nodeId !== null && $nodeId > 0) {
            $this->upsertRuntime($nodeId, $payload, true);
        }

        return $this->accepted();
    }

    public function acceptTraffic(array $payload = [], ?int $nodeId = null): array
    {
        $reportId = $this->reportId($payload);

        if ($reportId === null) {
            return $this->rejected('missing_report_id', $this->trafficResult());
        }

        if (! is_array($payload['data'] ?? null)) {
            return $this->rejected('invalid_data', $this->trafficResult());
        }

        $node = $this->authenticatedNode($nodeId);

        if ($node === null) {
            return $this->rejected('node_not_found', $this->trafficResult());
        }

        if ((int) $node->type === 0) {
            return $this->rejected('node_disabled', $this->trafficResult());
        }

        if ($this->receiptExists((int) $node->id, 'traffic', $reportId)) {
            return $this->accepted($this->trafficResult(['duplicate' => true]));
        }

        try {
            return DB::connection()->transaction(function () use ($payload, $node, $reportId): array {
                if ($this->receiptExists((int) $node->id, 'traffic', $reportId)) {
                    return $this->accepted($this->trafficResult(['duplicate' => true]));
                }

                $lockedNode = (new Node())
                    ->where('id', (int) $node->id)
                    ->lockForUpdate()
                    ->first();

                if ($lockedNode === null) {
                    return $this->rejected('node_not_found', $this->trafficResult());
                }

                if ((int) $lockedNode->type === 0) {
                    return $this->rejected('node_disabled', $this->trafficResult());
                }

                $this->createReceipt($reportId, 'traffic', (int) $lockedNode->id, $payload);

                $rate = $this->trafficRate($lockedNode);
                $result = $this->trafficResult();
                $isTrafficLog = (bool) Config::obtain('traffic_log');
                $trafficByUser = [];

                foreach ($payload['data'] as $item) {
                    if (! is_array($item)) {
                        $result['skipped']++;
                        continue;
                    }

                    $userId = $this->positiveInt($item['user_id'] ?? null);

                    if ($userId === null) {
                        $result['skipped']++;
                        continue;
                    }

                    $u = $this->nonNegativeInt($item['u'] ?? 0);
                    $d = $this->nonNegativeInt($item['d'] ?? 0);

                    if (! isset($trafficByUser[$userId])) {
                        $trafficByUser[$userId] = ['u' => 0, 'd' => 0];
                    }

                    $trafficByUser[$userId]['u'] += $u;
                    $trafficByUser[$userId]['d'] += $d;
                }

                ksort($trafficByUser, SORT_NUMERIC);

                foreach ($trafficByUser as $userId => $traffic) {
                    $user = (new User())->where('id', $userId)->lockForUpdate()->first();

                    if ($user === null) {
                        $result['skipped']++;
                        continue;
                    }

                    if (! NodeProfileService::canUserUseNode($user, $lockedNode, false)) {
                        $result['skipped']++;
                        continue;
                    }

                    $u = $traffic['u'];
                    $d = $traffic['d'];
                    $billedU = $u * $rate;
                    $billedD = $d * $rate;
                    $now = time();

                    $user->update([
                        'last_use_time' => $now,
                        'u' => $user->u + $billedU,
                        'd' => $user->d + $billedD,
                        'transfer_total' => $user->transfer_total + $u + $d,
                        'transfer_today' => $user->transfer_today + $billedU + $billedD,
                    ]);

                    if ($isTrafficLog) {
                        (new HourlyUsage())->add($userId, $u + $d);
                    }

                    $result['users']++;
                    $result['bytes'] += $u + $d;
                }

                $lockedNode->update([
                    'node_bandwidth' => $lockedNode->node_bandwidth + $result['bytes'],
                ]);

                return $this->accepted($result);
            });
        } catch (QueryException $e) {
            if ($this->receiptExists((int) $node->id, 'traffic', $reportId)) {
                return $this->accepted($this->trafficResult(['duplicate' => true]));
            }

            throw $e;
        }
    }

    public function acceptOnline(array $payload = [], ?int $nodeId = null): array
    {
        $reportId = $this->reportId($payload);

        if ($reportId === null) {
            return $this->rejected('missing_report_id', $this->onlineResult());
        }

        if (! is_array($payload['data'] ?? null)) {
            return $this->rejected('invalid_data', $this->onlineResult());
        }

        $node = $this->authenticatedNode($nodeId);

        if ($node === null) {
            return $this->rejected('node_not_found', $this->onlineResult());
        }

        if ((int) $node->type === 0) {
            return $this->rejected('node_disabled', $this->onlineResult());
        }

        if ($this->receiptExists((int) $node->id, 'online', $reportId)) {
            return $this->accepted($this->onlineResult(['duplicate' => true]));
        }

        try {
            return DB::connection()->transaction(function () use ($payload, $node, $reportId): array {
                if ($this->receiptExists((int) $node->id, 'online', $reportId)) {
                    return $this->accepted($this->onlineResult(['duplicate' => true]));
                }

                $this->createReceipt($reportId, 'online', (int) $node->id, $payload);

                $result = $this->onlineResult();
                $now = time();

                foreach ($payload['data'] as $item) {
                    if (! is_array($item)) {
                        $result['skipped_count']++;
                        continue;
                    }

                    $userId = $this->positiveInt($item['user_id'] ?? null);
                    $ip = $this->normalizedIp($item['ip'] ?? null);

                    if ($userId === null || $ip === null) {
                        $result['skipped_count']++;
                        continue;
                    }

                    $user = (new User())->where('id', $userId)->first();

                    if ($user === null || ! NodeProfileService::canUserUseNode($user, $node, false)) {
                        $result['skipped_count']++;
                        continue;
                    }

                    (new OnlineLog())->upsert(
                        [
                            'user_id' => $userId,
                            'ip' => $ip,
                            'node_id' => (int) $node->id,
                            'first_time' => $now,
                            'last_time' => $now,
                        ],
                        ['user_id', 'ip'],
                        ['node_id', 'last_time']
                    );

                    $result['online_count']++;
                }

                return $this->accepted($result);
            });
        } catch (QueryException $e) {
            if ($this->receiptExists((int) $node->id, 'online', $reportId)) {
                return $this->accepted($this->onlineResult(['duplicate' => true]));
            }

            throw $e;
        }
    }

    public function acceptDetectLog(array $payload = [], ?int $nodeId = null): array
    {
        $reportId = $this->reportId($payload);

        if ($reportId === null) {
            return $this->rejected('missing_report_id', $this->detectLogResult());
        }

        if (! is_array($payload['data'] ?? null)) {
            return $this->rejected('invalid_data', $this->detectLogResult());
        }

        $node = $this->authenticatedNode($nodeId);

        if ($node === null) {
            return $this->rejected('node_not_found', $this->detectLogResult());
        }

        if ((int) $node->type === 0) {
            return $this->rejected('node_disabled', $this->detectLogResult());
        }

        if ($this->receiptExists((int) $node->id, 'detect-log', $reportId)) {
            return $this->accepted($this->detectLogResult(['duplicate' => true]));
        }

        try {
            return DB::connection()->transaction(function () use ($payload, $node, $reportId): array {
                if ($this->receiptExists((int) $node->id, 'detect-log', $reportId)) {
                    return $this->accepted($this->detectLogResult(['duplicate' => true]));
                }

                $this->createReceipt($reportId, 'detect-log', (int) $node->id, $payload);

                $result = $this->detectLogResult();
                $now = time();

                foreach ($payload['data'] as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $userId = $this->positiveInt($item['user_id'] ?? null);
                    $listId = $this->positiveInt($item['list_id'] ?? ($item['rule_id'] ?? null));

                    if ($userId === null || $listId === null) {
                        continue;
                    }

                    $user = (new User())->where('id', $userId)->first();

                    if ($user === null || ! NodeProfileService::canUserUseNode($user, $node, false)) {
                        continue;
                    }

                    $eventId = $this->scalarString($item['event_id'] ?? null, 128);
                    if ($eventId !== null) {
                        $rule = (new XNodeAuditRule())->where('id', $listId)->where('enabled', 1)->first();
                        if ($rule === null || ! (new XNodeAuditService())->appliesToNode($rule, $node)) {
                            continue;
                        }

                        $eventKey = hash('sha256', (int) $node->id . '|' . $eventId);
                        $inserted = (new XNodeAuditEvent())->newQuery()->insertOrIgnore([
                            'event_key' => $eventKey,
                            'node_id' => (int) $node->id,
                            'user_id' => $userId,
                            'rule_id' => $listId,
                            'source_ip' => $this->normalizedIp($item['source_ip'] ?? ($item['ip'] ?? null)),
                            'target_host' => $this->scalarString($item['target_host'] ?? ($item['target'] ?? null), 255),
                            'target_port' => $this->portNumber($item['target_port'] ?? null),
                            'protocol' => $this->scalarString($item['protocol'] ?? null, 32),
                            'action' => (string) $rule->action,
                            'observed_at' => $this->positiveInt($item['observed_at'] ?? null) ?? $now,
                            'created_at' => $now,
                            'processed' => 0,
                        ]);

                        $result['count'] += $inserted > 0 ? 1 : 0;
                        continue;
                    }

                    (new DetectLog())->insert([
                        'user_id' => $userId,
                        'list_id' => $listId,
                        'node_id' => (int) $node->id,
                        'datetime' => $now,
                    ]);

                    $result['count']++;
                }

                return $this->accepted($result);
            });
        } catch (QueryException $e) {
            if ($this->receiptExists((int) $node->id, 'detect-log', $reportId)) {
                return $this->accepted($this->detectLogResult(['duplicate' => true]));
            }

            throw $e;
        }
    }

    public function acceptHeartbeat(array $payload = [], ?int $nodeId = null): array
    {
        if ($nodeId !== null && $nodeId > 0) {
            $this->upsertRuntime($nodeId, $payload, false);
        }

        return $this->accepted();
    }

    private function upsertRuntime(int $nodeId, array $payload, bool $includeRuntimeFields): void
    {
        $now = time();
        DB::connection()->transaction(function () use ($nodeId, $payload, $includeRuntimeFields, $now): void {
            (new NodeRuntime())->newQuery()->insertOrIgnore([
                'node_id' => $nodeId,
                'created_at' => $now,
                'updated_at' => $now,
                'last_seen' => $now,
            ]);
            $runtime = (new NodeRuntime())->where('node_id', $nodeId)->lockForUpdate()->first();

            if ($runtime === null) {
                return;
            }

            $this->assignString($runtime, $payload, 'agent_version', 64);
            $this->assignString($runtime, $payload, 'core_version', 64);
            $this->assignNormalizedState($runtime, $payload);
            $this->assignString($runtime, $payload, 'config_hash', 128);
            $this->assignString($runtime, $payload, 'audit_revision', 64);
            $this->assignString($runtime, $payload, 'audit_hash', 128);
            $this->assignString($runtime, $payload, 'audit_status', 32);
            $this->assignString($runtime, $payload, 'audit_error', 2048);
            if (array_key_exists('audit_applied_at', $payload)) {
                $runtime->audit_applied_at = $this->optionalNonNegativeInt($payload['audit_applied_at']);
            }
            $this->assignRuntimeLastError($runtime, $payload, $includeRuntimeFields);

            if ($includeRuntimeFields) {
                $this->assignJson($runtime, $payload, 'capabilities', 'capabilities_json');
                $this->assignRealityMetadata($runtime, $payload);
            }

            $runtime->last_seen = $now;
            $runtime->updated_at = $now;
            $runtime->save();
            $node = (new Node())->where('id', $nodeId)->lockForUpdate()->first();
            if ($node !== null) {
                $node->node_heartbeat = $now;
                $node->save();
            }
        });
    }

    private function assignNormalizedState(NodeRuntime $runtime, array $payload): void
    {
        if (! array_key_exists('state', $payload)) {
            return;
        }

        if ($payload['state'] === null) {
            $runtime->state = null;
            return;
        }

        if (is_scalar($payload['state'])) {
            $runtime->state = substr(strtolower(trim((string) $payload['state'])), 0, 32);
        }
    }

    private function assignRuntimeLastError(
        NodeRuntime $runtime,
        array $payload,
        bool $includeRuntimeFields
    ): void
    {
        if (
            ! $includeRuntimeFields
            && in_array($runtime->last_error, self::REALITY_METADATA_ERROR_CODES, true)
        ) {
            return;
        }

        $this->assignString($runtime, $payload, 'last_error');
    }

    private function assignRealityMetadata(NodeRuntime $runtime, array $payload): void
    {
        $metadata = new XNodeRealityMetadataService();
        $publicKey = $metadata->normalizePublicKey($payload['public_key'] ?? null);
        $shortIds = $metadata->normalizeShortIds($payload['short_ids'] ?? null);
        $suppliedHash = $metadata->normalizeRealityHash($payload['reality_hash'] ?? null);
        $calculatedHash = $metadata->calculateRealityHash($publicKey, $shortIds);

        if (
            $publicKey !== null
            && $metadata->validatePublicKey($publicKey)
            && $shortIds !== null
            && $suppliedHash !== null
            && $calculatedHash !== null
            && hash_equals($calculatedHash, $suppliedHash)
        ) {
            $runtime->public_key = $publicKey;
            $runtime->short_ids_json = json_encode($shortIds);
            $runtime->reality_hash = $suppliedHash;

            if (
                ! array_key_exists('last_error', $payload)
                && in_array($runtime->last_error, self::REALITY_METADATA_ERROR_CODES, true)
            ) {
                $runtime->last_error = null;
            }

            return;
        }

        if (
            $suppliedHash !== null
            && $calculatedHash !== null
            && ! hash_equals($calculatedHash, $suppliedHash)
        ) {
            $runtime->last_error = 'reality_metadata_hash_mismatch';

            return;
        }

        $runtime->last_error = 'reality_metadata_invalid';
    }

    private function assignString(NodeRuntime $runtime, array $payload, string $field, ?int $maxLength = null): void
    {
        if (! array_key_exists($field, $payload)) {
            return;
        }

        $value = $payload[$field];

        if ($value === null) {
            $runtime->{$field} = null;
            return;
        }

        if (! is_scalar($value)) {
            return;
        }

        $value = (string) $value;
        $runtime->{$field} = $maxLength === null ? $value : substr($value, 0, $maxLength);
    }

    private function assignJson(NodeRuntime $runtime, array $payload, string $sourceField, string $targetField): void
    {
        if (array_key_exists($targetField, $payload) && (is_string($payload[$targetField]) || $payload[$targetField] === null)) {
            $runtime->{$targetField} = $payload[$targetField];
            return;
        }

        if (! array_key_exists($sourceField, $payload)) {
            return;
        }

        $value = $payload[$sourceField];

        if ($value === null) {
            $runtime->{$targetField} = null;
            return;
        }

        if (! is_array($value) && ! is_bool($value) && ! is_int($value) && ! is_float($value) && ! is_string($value)) {
            return;
        }

        $encoded = json_encode($value);

        if (is_string($encoded)) {
            $runtime->{$targetField} = $encoded;
        }
    }

    private function authenticatedNode(?int $nodeId): ?Node
    {
        if ($nodeId === null || $nodeId <= 0) {
            return null;
        }

        return (new Node())->find($nodeId);
    }

    private function reportId(array $payload): ?string
    {
        if (! array_key_exists('report_id', $payload) || ! is_scalar($payload['report_id'])) {
            return null;
        }

        $reportId = trim((string) $payload['report_id']);

        if ($reportId === '' || strlen($reportId) > 128) {
            return null;
        }

        return $reportId;
    }

    private function receiptExists(int $nodeId, string $reportType, string $reportId): bool
    {
        return (new NodeReportReceipt())
            ->where('node_id', $nodeId)
            ->where('report_type', $reportType)
            ->where('report_id', $reportId)
            ->exists();
    }

    private function createReceipt(string $reportId, string $reportType, int $nodeId, array $payload): void
    {
        (new NodeReportReceipt())->insert([
            'node_id' => $nodeId,
            'report_id' => $reportId,
            'report_type' => $reportType,
            'period_start' => $this->optionalNonNegativeInt($payload['period_start'] ?? null),
            'period_end' => $this->optionalNonNegativeInt($payload['period_end'] ?? null),
            'created_at' => time(),
        ]);
    }

    private function trafficRate(Node $node): float
    {
        if (! $node->is_dynamic_rate) {
            return (float) $node->traffic_rate;
        }

        $dynamicRateConfig = json_decode((string) $node->dynamic_rate_config);
        $dynamicRateType = match ((int) $node->dynamic_rate_type) {
            1 => 'linear',
            default => 'logistic',
        };

        return DynamicRate::getRateByTime(
            (float) ($dynamicRateConfig?->max_rate ?? 0),
            (int) ($dynamicRateConfig?->max_rate_time ?? 0),
            (float) ($dynamicRateConfig?->min_rate ?? 0),
            (int) ($dynamicRateConfig?->min_rate_time ?? 0),
            (int) date('H'),
            $dynamicRateType
        );
    }

    private function normalizedIp(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $ip = trim((string) $value);

        if (Tools::isIPv4($ip)) {
            return '::ffff:' . $ip;
        }

        if (Tools::isIPv6($ip)) {
            return $ip;
        }

        return null;
    }

    private function scalarString(mixed $value, int $maxLength): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : substr($value, 0, $maxLength);
    }

    private function positiveInt(mixed $value): ?int
    {
        if (! is_numeric($value)) {
            return null;
        }

        $value = (int) $value;

        return $value > 0 ? $value : null;
    }

    private function portNumber(mixed $value): ?int
    {
        $port = $this->positiveInt($value);

        return $port !== null && $port <= 65535 ? $port : null;
    }

    private function optionalNonNegativeInt(mixed $value): ?int
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    private function nonNegativeInt(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    private function trafficResult(array $overrides = []): array
    {
        return $overrides + [
            'duplicate' => false,
            'users' => 0,
            'bytes' => 0,
            'skipped' => 0,
        ];
    }

    private function onlineResult(array $overrides = []): array
    {
        return $overrides + [
            'duplicate' => false,
            'online_count' => 0,
            'skipped_count' => 0,
        ];
    }

    private function detectLogResult(array $overrides = []): array
    {
        return $overrides + [
            'duplicate' => false,
            'count' => 0,
        ];
    }

    private function rejected(string $code, array $data = []): array
    {
        return $data + [
            'accepted' => false,
            'code' => $code,
        ];
    }

    private function accepted(array $data = []): array
    {
        return $data + [
            'accepted' => true,
        ];
    }
}
