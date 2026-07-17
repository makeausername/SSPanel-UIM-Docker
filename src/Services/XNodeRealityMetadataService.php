<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NodeRuntime;
use Throwable;
use function array_key_exists;
use function hash;
use function hash_equals;
use function implode;
use function in_array;
use function is_array;
use function is_numeric;
use function is_object;
use function is_scalar;
use function is_string;
use function json_decode;
use function preg_match;
use function property_exists;
use function sort;
use function strtolower;
use function time;
use function trim;
use const SORT_STRING;

final class XNodeRealityMetadataService
{
    public const RUNTIME_FRESHNESS_SECONDS = 180;

    public function normalizePublicKey(mixed $publicKey): ?string
    {
        if (! is_scalar($publicKey)) {
            return null;
        }

        return trim((string) $publicKey);
    }

    /**
     * @return list<string>|null
     */
    public function normalizeShortIds(mixed $shortIds): ?array
    {
        if (is_string($shortIds)) {
            $shortIds = json_decode($shortIds, true);
        }

        if (! is_array($shortIds) || $shortIds === []) {
            return null;
        }

        $normalized = [];

        foreach ($shortIds as $shortId) {
            if (! is_scalar($shortId)) {
                return null;
            }

            $shortId = strtolower(trim((string) $shortId));
            if (! $this->validateShortId($shortId)) {
                return null;
            }

            if (! in_array($shortId, $normalized, true)) {
                $normalized[] = $shortId;
            }
        }

        sort($normalized, SORT_STRING);

        return $normalized === [] ? null : $normalized;
    }

    public function validatePublicKey(mixed $publicKey): bool
    {
        $publicKey = $this->normalizePublicKey($publicKey);

        return $publicKey !== null
            && preg_match('/^[A-Za-z0-9_-]{43}$/D', $publicKey) === 1;
    }

    public function validateShortId(mixed $shortId): bool
    {
        if (! is_scalar($shortId)) {
            return false;
        }

        return preg_match('/^(?:[0-9a-f]{2}){1,8}$/D', strtolower(trim((string) $shortId))) === 1;
    }

    public function calculateRealityHash(mixed $publicKey, mixed $shortIds): ?string
    {
        $publicKey = $this->normalizePublicKey($publicKey);
        $shortIds = $this->normalizeShortIds($shortIds);

        if ($publicKey === null || ! $this->validatePublicKey($publicKey) || $shortIds === null) {
            return null;
        }

        return hash('sha256', $publicKey . "\n" . implode("\n", $shortIds));
    }

    public function validateRuntimeMetadata(mixed $runtime): bool
    {
        $publicKey = $this->runtimeValue($runtime, 'public_key');
        $shortIds = $this->runtimeValue($runtime, 'short_ids_json');
        $realityHash = $this->normalizeRealityHash($this->runtimeValue($runtime, 'reality_hash'));
        $calculatedHash = $this->calculateRealityHash($publicKey, $shortIds);

        return $realityHash !== null
            && $calculatedHash !== null
            && hash_equals($calculatedHash, $realityHash);
    }

    public function selectUsableRuntimeForNode(int $nodeId, ?int $now = null): ?NodeRuntime
    {
        if ($nodeId <= 0) {
            return null;
        }

        try {
            $runtime = (new NodeRuntime())
                ->where('node_id', $nodeId)
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->first();
        } catch (Throwable) {
            return null;
        }

        if (! $runtime instanceof NodeRuntime || $runtime->getAttribute('state') !== 'running') {
            return null;
        }

        $now ??= time();
        $lastSeen = $runtime->getAttribute('last_seen');
        if (! is_numeric($lastSeen)) {
            return null;
        }

        $lastSeen = (int) $lastSeen;
        $age = $now - $lastSeen;
        if ($lastSeen <= 0 || $age < 0 || $age > self::RUNTIME_FRESHNESS_SECONDS) {
            return null;
        }

        $lastError = $runtime->getAttribute('last_error');
        if ($lastError !== null && (! is_scalar($lastError) || trim((string) $lastError) !== '')) {
            return null;
        }

        return $this->validateRuntimeMetadata($runtime) ? $runtime : null;
    }

    public function normalizeRealityHash(mixed $realityHash): ?string
    {
        if (! is_scalar($realityHash)) {
            return null;
        }

        $realityHash = strtolower(trim((string) $realityHash));

        return preg_match('/^[0-9a-f]{64}$/D', $realityHash) === 1 ? $realityHash : null;
    }

    private function runtimeValue(mixed $runtime, string $field): mixed
    {
        if ($runtime instanceof NodeRuntime) {
            return $runtime->getAttribute($field);
        }

        if (is_array($runtime)) {
            return array_key_exists($field, $runtime) ? $runtime[$field] : null;
        }

        if (is_object($runtime) && property_exists($runtime, $field)) {
            return $runtime->{$field};
        }

        return null;
    }
}
