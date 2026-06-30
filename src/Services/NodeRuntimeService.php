<?php

declare(strict_types=1);

namespace App\Services;

final class NodeRuntimeService
{
    public function acceptRuntime(array $payload = []): array
    {
        // TODO: Save public_key, short_ids, capabilities, and config_hash to node_runtimes.
        return $this->accepted();
    }

    public function acceptTraffic(array $payload = []): array
    {
        // TODO: Add idempotent report_id handling before mutating traffic or billing data.
        return $this->accepted();
    }

    public function acceptOnline(array $payload = []): array
    {
        // TODO: Write online_log only after authentication and payload validation are implemented.
        return $this->accepted();
    }

    public function acceptDetectLog(array $payload = []): array
    {
        // TODO: Write detect_log only after authentication and payload validation are implemented.
        return $this->accepted();
    }

    public function acceptHeartbeat(array $payload = []): array
    {
        // TODO: Update legacy node heartbeat only after the Node API contract is finalized.
        return $this->accepted();
    }

    private function accepted(): array
    {
        return [
            'accepted' => true,
        ];
    }
}
