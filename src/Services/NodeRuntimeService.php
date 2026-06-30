<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\NodeRuntime;
use function array_key_exists;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_scalar;
use function is_string;
use function json_encode;
use function substr;
use function time;

final class NodeRuntimeService
{
    public function acceptRuntime(array $payload = [], ?int $nodeId = null): array
    {
        if ($nodeId !== null && $nodeId > 0) {
            $this->upsertRuntime($nodeId, $payload, true);
        }

        return $this->accepted();
    }

    public function acceptTraffic(array $payload = [], ?int $nodeId = null): array
    {
        // TODO: Add idempotent report_id handling before mutating traffic or billing data.
        return $this->accepted();
    }

    public function acceptOnline(array $payload = [], ?int $nodeId = null): array
    {
        // TODO: Write online_log only after authentication and payload validation are implemented.
        return $this->accepted();
    }

    public function acceptDetectLog(array $payload = [], ?int $nodeId = null): array
    {
        // TODO: Write detect_log only after authentication and payload validation are implemented.
        return $this->accepted();
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
        $runtime = (new NodeRuntime())->where('node_id', $nodeId)->first();

        if ($runtime === null) {
            $runtime = new NodeRuntime();
            $runtime->node_id = $nodeId;
            $runtime->created_at = $now;
        }

        $this->assignString($runtime, $payload, 'agent_version', 64);
        $this->assignString($runtime, $payload, 'core_version', 64);
        $this->assignString($runtime, $payload, 'state', 32);
        $this->assignString($runtime, $payload, 'config_hash', 128);
        $this->assignString($runtime, $payload, 'last_error');

        if ($includeRuntimeFields) {
            $this->assignString($runtime, $payload, 'public_key', 255);
            $this->assignJson($runtime, $payload, 'short_ids', 'short_ids_json');
            $this->assignJson($runtime, $payload, 'capabilities', 'capabilities_json');
        }

        $runtime->last_seen = $now;
        $runtime->updated_at = $now;
        $runtime->save();
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

    private function accepted(): array
    {
        return [
            'accepted' => true,
        ];
    }
}
