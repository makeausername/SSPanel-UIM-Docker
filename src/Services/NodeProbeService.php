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

    public static function recordResult(
        array $payload,
        bool $notify = true,
        bool $authoritativeGfw = false
    ): array
    {
        $nodeId = (int) ($payload['node_id'] ?? 0);

        if ($nodeId <= 0) {
            throw new InvalidArgumentException('node_id is required');
        }

        $now = time();
        $checkedAt = (int) ($payload['checked_at'] ?? $now);
        $checkedAt = $checkedAt > 0 ? $checkedAt : $now;

        if ($checkedAt > $now + 300) {
            throw new InvalidArgumentException('checked_at is too far in the future');
        }

        $status = self::normalizeStatus((string) ($payload['status'] ?? self::STATUS_ERROR));
        $latencyMs = self::nullableUnsignedInt($payload['latency_ms'] ?? null);
        $targetPort = self::unsignedInt($payload['target_port'] ?? 443, 443);

        if ($targetPort <= 0 || $targetPort > 65535) {
            throw new InvalidArgumentException('target_port must be between 1 and 65535');
        }

        $resultData = [
            'probe_region' => self::cleanString($payload['probe_region'] ?? '', 64),
            'probe_provider' => self::nullableString($payload['probe_provider'] ?? null, 64),
            'probe_location' => self::nullableString($payload['probe_location'] ?? null, 128),
            'probe_type' => self::cleanString($payload['probe_type'] ?? '', 32),
            'target_host' => self::cleanString($payload['target_host'] ?? '', 255),
            'target_port' => $targetPort,
            'status' => $status,
            'latency_ms' => $latencyMs,
            'error' => self::nullableString($payload['error'] ?? null, 512),
            'checked_at' => $checkedAt,
        ];

        $outcome = DB::connection()->transaction(static function () use (
            $nodeId,
            $now,
            $checkedAt,
            $status,
            $resultData,
            $notify,
            $authoritativeGfw
        ): array {
            $node = (new Node())->where('id', $nodeId)->lockForUpdate()->first();

            if ($node === null) {
                throw new InvalidArgumentException('node_id does not exist');
            }

            if ((int) $node->type === 0) {
                throw new InvalidArgumentException('node is disabled');
            }

            self::assertTargetMatchesNode($node, (string) $resultData['target_host']);

            (new NodeProbeState())->newQuery()->insertOrIgnore([
                'node_id' => $nodeId,
                'status' => self::STATUS_UNKNOWN,
                'target_port' => 443,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $state = (new NodeProbeState())->where('node_id', $nodeId)->lockForUpdate()->first();

            if ($state === null) {
                throw new InvalidArgumentException('probe state could not be created');
            }

            $oldStatus = self::normalizeStatus((string) ($state->status ?? self::STATUS_UNKNOWN));
            $lastCheckedAt = (int) ($state->last_checked_at ?? 0);

            if ($lastCheckedAt > 0 && $checkedAt <= $lastCheckedAt) {
                return ['summary' => self::summaryFromState($state), 'notification' => null];
            }

            if (
                $oldStatus === self::STATUS_SUSPECTED_BLOCKED
                && $status === self::STATUS_OK
                && ! self::isMainlandProbeRegion((string) $resultData['probe_region'])
            ) {
                return ['summary' => self::summaryFromState($state), 'notification' => null];
            }

            $result = new NodeProbeResult();
            foreach ($resultData as $key => $value) {
                $result->{$key} = $value;
            }
            $result->node_id = $nodeId;
            $result->created_at = $now;
            $result->save();

            $statusChanged = $oldStatus !== $status;
            foreach ($resultData as $key => $value) {
                if ($key !== 'checked_at') {
                    $state->{$key} = $value;
                }
            }
            $state->last_checked_at = $checkedAt;
            $state->updated_at = $now;

            if ($statusChanged) {
                $state->previous_status = $oldStatus;
                $state->last_changed_at = $checkedAt;
            }

            $state->save();
            if ($authoritativeGfw) {
                self::updateNodeGfwBlock($node, $oldStatus, $status);
            }

            $shouldNotify = $notify
                && $statusChanged
                && NodeProbeNotificationService::shouldNotifyTransition($oldStatus, $status);

            return [
                'summary' => self::summaryFromState($state),
                'notification' => $shouldNotify ? [
                    'node_id' => $nodeId,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                    'checked_at' => $checkedAt,
                    'context' => $resultData,
                ] : null,
            ];
        });

        if ($outcome['notification'] !== null) {
            self::sendNotification($outcome['notification'], $now);
        }

        return $outcome['summary'];
    }

    private static function sendNotification(array $notification, int $notifiedAt): void
    {
        $node = (new Node())->where('id', $notification['node_id'])->first();
        if ($node === null) {
            return;
        }

        NodeProbeNotificationService::notifyTransition(
            $node,
            $notification['old_status'],
            $notification['new_status'],
            $notification['context']
        );

        DB::connection()->transaction(static function () use ($notification, $notifiedAt): void {
            $state = (new NodeProbeState())
                ->where('node_id', $notification['node_id'])
                ->where('status', $notification['new_status'])
                ->where('last_checked_at', $notification['checked_at'])
                ->lockForUpdate()
                ->first();

            if ($state !== null) {
                $state->last_notified_status = $notification['new_status'];
                $state->last_notified_at = $notifiedAt;
                $state->save();
            }
        });
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

    public static function isAllowedStatus(string $status): bool
    {
        return in_array(trim($status), self::ALLOWED_STATUSES, true);
    }

    private static function assertTargetMatchesNode(Node $node, string $targetHost): void
    {
        $targetHost = strtolower(trim($targetHost));
        $allowedHosts = [];

        foreach (['server', 'domain'] as $field) {
            $host = strtolower(trim((string) ($node->getAttribute($field) ?? '')));

            if ($host !== '') {
                $allowedHosts[] = $host;
            }
        }

        if ($targetHost === '' || $allowedHosts === [] || ! in_array($targetHost, $allowedHosts, true)) {
            throw new InvalidArgumentException('target_host does not match node');
        }
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

        if (! self::isAllowedStatus($status)) {
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
