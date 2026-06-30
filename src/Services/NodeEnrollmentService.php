<?php

declare(strict_types=1);

namespace App\Services;

use function is_string;

final class NodeEnrollmentService
{
    /**
     * @param mixed $nodeId
     */
    public function buildStubEnrollment($nodeId, string $domain): array
    {
        // TODO: Validate a one-time enroll token and persist a real node token hash.
        return [
            'node_token' => 'xn_stub_do_not_use_in_production',
            'panel_url' => $this->getPanelUrl(),
            'node_id' => $nodeId,
            'domain' => $domain,
            'report_interval_sec' => 60,
            'config_interval_sec' => 60,
        ];
    }

    private function getPanelUrl(): string
    {
        $panelUrl = $_ENV['baseUrl'] ?? '';

        return is_string($panelUrl) ? $panelUrl : '';
    }
}
