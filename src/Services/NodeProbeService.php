<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use App\Models\NodeProbeResult;
use App\Models\NodeProbeState;
use InvalidArgumentException;
use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function date;
use function function_exists;
use function in_array;
use function is_numeric;
use function max;
use function mb_substr;
use function substr;
use function strtolower;
use function time;
use function trim;

final class NodeProbeService
{
    public const STATUS_UNKNOWN = 'unknown';
    public const STATUS_OK = 'ok';
    public const STATUS_SUSPECTED_BLOCKED = 'suspected_blocked';
    public const STATUS_UNREACHABLE = 'unreachable';
    public const STATUS_ERROR = 'error';

    private const ALLOWED_STATUSES = [
        self::STATUS_UNKNOWN,
        self::STATUS_OK,
        self::STATUS_SUSPECTED_BLOCKED,
        self::STATUS_UNREACHABLE,
        self::STATUS_ERROR,
    ];

    private const STATUS_LABELS = [
        self::STATUS_UNKNOWN => '未检测',
        self::STATUS_OK => '正常',
        self::STATUS_SUSPECTED_BLOCKED => '疑似被墙',
        self::STATUS_UNREACHABLE => '不可达',
        self::STATUS_ERROR => '检测异常',
    ];

    private const BADGE_CLASSES = [
        self::STATUS_UNKNOWN => 'bg-secondary text-secondary-fg',
        self::STATUS_OK => 'bg-green text-green-fg',
        self::STATUS_SUSPECTED_BLOCKED => 'bg-red text-red-fg',
        self::STATUS_UNREACHABLE => 'bg-orange text-orange-fg',
        self::STATUS_ERROR => 'bg-yellow text-yellow-fg',
    ];

    public static function recordResult(array $payload, bool $notify = true): array
    {
        $nodeId = (int) ($payload['node_id'] ?? 0);

        if ($nodeId <= 0) {
            throw new InvalidArgumentException('node_id is required');
        }

        $now = time();
        $checkedAt = (int) ($payload['checked_at'] ?? $now);
        $checkedAt = $checkedAt > 0 ? $checkedAt : $now;
        $status = self::normalizeStatus((string) ($payload['status'] ?? self::STATUS_ERROR));
        $latencyMs = self::nullableUnsignedInt($payload['latency_ms'] ?? null);
        $targetPort = self::unsignedInt($payload['target_port'] ?? 443, 443);

        $result = new NodeProbeResult();
        $result->node_id = $nodeId;
        $result->probe_region = self::cleanString($payload['probe_region'] ?? '', 64);
        $result->probe_provider = self::nullableString($payload['probe_provider'] ?? null, 64);
        $result->probe_location = self::nullableString($payload['probe_location'] ?? null, 128);
        $result->probe_type = self::cleanString($payload['probe_type'] ?? '', 32);
        $result->target_host = self::cleanString($payload['target_host'] ?? '', 255);
        $result->target_port = $targetPort;
        $result->status = $status;
        $result->latency_ms = $latencyMs;
        $result->error = self::nullableString($payload['error'] ?? null, 512);
        $result->checked_at = $checkedAt;
        $result->created_at = $now;
        $result->save();

        $state = (new NodeProbeState())->where('node_id', $nodeId)->first();

        if ($state === null) {
            $state = new NodeProbeState();
            $state->node_id = $nodeId;
            $state->created_at = $now;
        }

        $oldStatus = self::normalizeStatus((string) ($state->status ?? self::STATUS_UNKNOWN));

        if (
            $oldStatus === self::STATUS_SUSPECTED_BLOCKED
            && $status === self::STATUS_OK
            && ! self::isMainlandProbeRegion($result->probe_region)
        ) {
            return self::summaryFromState($state);
        }

        $statusChanged = $oldStatus !== $status;

        $state->status = $status;
        $state->probe_region = $result->probe_region;
        $state->probe_provider = $result->probe_provider;
        $state->probe_location = $result->probe_location;
        $state->probe_type = $result->probe_type;
        $state->target_host = $result->target_host;
        $state->target_port = $targetPort;
        $state->latency_ms = $latencyMs;
        $state->error = $result->error;
        $state->last_checked_at = $checkedAt;
        $state->updated_at = $now;

        if ($statusChanged) {
            $state->previous_status = $oldStatus;
            $state->last_changed_at = $checkedAt;
        }

        $state->save();

        $node = (new Node())->where('id', $nodeId)->first();

        if ($node !== null) {
            self::updateNodeGfwBlock($node, $oldStatus, $status);

            if (
                $notify
                && $statusChanged
                && NodeProbeNotificationService::shouldNotifyTransition($oldStatus, $status)
            ) {
                NodeProbeNotificationService::notifyTransition($node, $oldStatus, $status, [
                    'probe_region' => $result->probe_region,
                    'probe_provider' => $result->probe_provider,
                    'probe_location' => $result->probe_location,
                    'probe_type' => $result->probe_type,
                    'target_host' => $result->target_host,
                    'target_port' => $targetPort,
                    'latency_ms' => $latencyMs,
                    'error' => $result->error,
                    'checked_at' => $checkedAt,
                ]);

                $state->last_notified_status = $status;
                $state->last_notified_at = $now;
                $state->save();
            }
        }

        return self::summaryFromState($state);
    }

    public static function isMainlandProbeRegion(string $region): bool
    {
        $region = strtolower(trim($region));

        return $region === 'cn'
            || substr($region, 0, 3) === 'cn-'
            || $region === 'china'
            || $region === 'mainland'
            || substr($region, 0, 9) === 'mainland-';
    }

    public static function isSelfProbeRegion(string $region): bool
    {
        $region = strtolower(trim($region));

        return in_array($region, ['node-self', 'self', 'local', 'agent-self'], true);
    }

    public static function summarizeNode(int $nodeId): array
    {
        if ($nodeId <= 0) {
            return self::defaultSummary();
        }

        $state = (new NodeProbeState())->where('node_id', $nodeId)->first();

        if ($state === null) {
            return self::defaultSummary();
        }

        return self::summaryFromState($state);
    }

    public static function summarizeNodes(array $nodeIds): array
    {
        $nodeIds = array_values(array_unique(array_map('intval', $nodeIds)));
        $nodeIds = array_values(array_filter($nodeIds, static fn (int $nodeId): bool => $nodeId > 0));
        $summaries = [];

        foreach ($nodeIds as $nodeId) {
            $summaries[$nodeId] = self::defaultSummary();
        }

        if ($nodeIds === []) {
            return $summaries;
        }

        foreach ((new NodeProbeState())->whereIn('node_id', $nodeIds)->get() as $state) {
            $summaries[(int) $state->node_id] = self::summaryFromState($state);
        }

        return $summaries;
    }

    private static function updateNodeGfwBlock(Node $node, string $oldStatus, string $newStatus): void
    {
        if ($newStatus === self::STATUS_SUSPECTED_BLOCKED && ! (bool) $node->gfw_block) {
            $node->gfw_block = true;
            $node->save();

            return;
        }

        if (
            $newStatus === self::STATUS_OK
            && $oldStatus === self::STATUS_SUSPECTED_BLOCKED
            && (bool) $node->gfw_block
        ) {
            $node->gfw_block = false;
            $node->save();
        }
    }

    private static function summaryFromState(NodeProbeState $state): array
    {
        $status = self::normalizeStatus((string) $state->status);
        $checkedAt = (int) ($state->last_checked_at ?? 0);
        $latencyMs = $state->latency_ms;

        return [
            'status' => $status,
            'label' => self::STATUS_LABELS[$status],
            'badge_class' => self::BADGE_CLASSES[$status],
            'latest_checked_at' => $checkedAt > 0 ? date('Y-m-d H:i:s', $checkedAt) : '-',
            'latest_region' => self::summaryValue($state->probe_region ?? null),
            'latest_provider' => self::summaryValue($state->probe_provider ?? null),
            'latest_location' => self::summaryValue($state->probe_location ?? null),
            'latest_probe_type' => self::summaryValue($state->probe_type ?? null),
            'latest_latency_ms' => is_numeric($latencyMs) ? (string) max(0, (int) $latencyMs) . ' ms' : '-',
            'latest_error' => trim((string) ($state->error ?? '')),
        ];
    }

    private static function defaultSummary(): array
    {
        return [
            'status' => self::STATUS_UNKNOWN,
            'label' => self::STATUS_LABELS[self::STATUS_UNKNOWN],
            'badge_class' => self::BADGE_CLASSES[self::STATUS_UNKNOWN],
            'latest_checked_at' => '-',
            'latest_region' => '-',
            'latest_provider' => '-',
            'latest_location' => '-',
            'latest_probe_type' => '-',
            'latest_latency_ms' => '-',
            'latest_error' => '',
        ];
    }

    private static function normalizeStatus(string $status): string
    {
        $status = trim($status);

        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            return self::STATUS_ERROR;
        }

        return $status;
    }

    private static function summaryValue(mixed $value): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? '-' : $value;
    }

    private static function nullableString(mixed $value, int $maxLength): ?string
    {
        $value = self::cleanString($value, $maxLength);

        return $value === '' ? null : $value;
    }

    private static function cleanString(mixed $value, int $maxLength): string
    {
        $value = trim((string) ($value ?? ''));

        if ($maxLength <= 0) {
            return $value;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }

    private static function nullableUnsignedInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max(0, (int) $value);
    }

    private static function unsignedInt(mixed $value, int $default): int
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return max(0, (int) $value);
    }
}
