<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\TestCase;

class NodeProfileServiceTest extends TestCase
{
    public function testBuildDefaultConfigUsesAgentContractShape(): void
    {
        $config = (new NodeProfileService())->buildDefaultConfig(1001, 'node1.example.com');

        $this->assertSame(1, $config['schema_version']);
        $this->assertSame(1001, $config['node_id']);
        $this->assertSame('node1.example.com', $config['domain']);
        $this->assertSame('vless-reality-vision', $config['profile']['name']);
        $this->assertSame('reality', $config['profile']['security']);
        $this->assertSame(['www.microsoft.com'], $config['reality']['server_names']);
        $this->assertSame(30, $config['report']['heartbeat_interval_sec']);
        $this->assertArrayHasKey('config_hash', $config);
    }

    public function testBuildMockUsersUsesAgentContractShape(): void
    {
        $users = (new NodeProfileService())->buildMockUsers(0, 1001);

        $this->assertSame([
            [
                'id' => 10001,
                'uuid' => '11111111-1111-1111-1111-111111111111',
                'email' => 'user-10001@panel.local',
                'speed_limit_mbps' => 0,
                'ip_limit' => 0,
                'enabled' => true,
                'updated_at' => 0,
            ],
        ], $users);
    }

    public function testBuildMockDetectRulesUsesAgentContractShape(): void
    {
        $rules = (new NodeProfileService())->buildMockDetectRules();

        $this->assertNotEmpty($rules);

        foreach ($rules as $rule) {
            $this->assertSame(['id', 'type', 'pattern'], array_keys($rule));
            $this->assertIsInt($rule['id']);
            $this->assertSame('protocol', $rule['type']);
            $this->assertIsString($rule['pattern']);
        }
    }
}
