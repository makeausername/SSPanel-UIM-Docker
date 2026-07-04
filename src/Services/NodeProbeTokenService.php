<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NodeProbeToken;
use InvalidArgumentException;
use function array_map;
use function array_values;
use function bin2hex;
use function function_exists;
use function hash;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function mb_substr;
use function random_bytes;
use function substr;
use function time;
use function trim;

final class NodeProbeTokenService
{
    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function generateProbeToken(): string
    {
        return 'xnp_' . bin2hex(random_bytes(32));
    }

    public static function createProbeToken(
        string $name,
        string $probeRegion,
        ?string $probeProvider = null,
        ?string $probeLocation = null,
        array $allowedNodeIds = [],
        ?int $ttlSeconds = null
    ): string {
        $service = new self();
        $name = self::cleanRequiredString($name, 128, 'name');
        $probeRegion = self::cleanRequiredString($probeRegion, 64, 'probe_region');
        $allowedNodeIds = self::normalizeAllowedNodeIds($allowedNodeIds);

        if ($ttlSeconds !== null && $ttlSeconds <= 0) {
            throw new InvalidArgumentException('ttlSeconds must be a positive integer or null.');
        }

        $now = time();
        $probeToken = $service->generateProbeToken();
        $tokenRecord = new NodeProbeToken();
        $tokenRecord->name = $name;
        $tokenRecord->token_hash = $service->hashToken($probeToken);
        $tokenRecord->probe_region = $probeRegion;
        $tokenRecord->probe_provider = self::cleanNullableString($probeProvider, 64);
        $tokenRecord->probe_location = self::cleanNullableString($probeLocation, 128);
        $tokenRecord->allowed_node_ids_json = $allowedNodeIds === [] ? null : (string) json_encode($allowedNodeIds);
        $tokenRecord->is_enabled = 1;
        $tokenRecord->expires_at = $ttlSeconds === null ? null : $now + $ttlSeconds;
        $tokenRecord->created_at = $now;
        $tokenRecord->updated_at = $now;
        $tokenRecord->save();

        return $probeToken;
    }

    public static function allowedNodeIdsFromRecord(NodeProbeToken $record): array
    {
        $decoded = json_decode((string) ($record->allowed_node_ids_json ?? ''), true);

        if (! is_array($decoded)) {
            return [];
        }

        return self::normalizeAllowedNodeIds($decoded);
    }

    public static function canReportNode(NodeProbeToken $record, int $nodeId): bool
    {
        if ($nodeId <= 0) {
            return false;
        }

        $allowedNodeIds = self::allowedNodeIdsFromRecord($record);

        return $allowedNodeIds === [] || in_array($nodeId, $allowedNodeIds, true);
    }

    private static function normalizeAllowedNodeIds(array $allowedNodeIds): array
    {
        $normalized = [];

        foreach (array_map('intval', $allowedNodeIds) as $nodeId) {
            if ($nodeId <= 0 || in_array($nodeId, $normalized, true)) {
                continue;
            }

            $normalized[] = $nodeId;
        }

        return array_values($normalized);
    }

    private static function cleanRequiredString(string $value, int $maxLength, string $field): string
    {
        $value = self::cleanString($value, $maxLength);

        if ($value === '') {
            throw new InvalidArgumentException($field . ' is required.');
        }

        return $value;
    }

    private static function cleanNullableString(?string $value, int $maxLength): ?string
    {
        $value = self::cleanString($value ?? '', $maxLength);

        return $value === '' ? null : $value;
    }

    private static function cleanString(string $value, int $maxLength): string
    {
        $value = trim($value);

        if ($maxLength <= 0) {
            return $value;
        }

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }
}
