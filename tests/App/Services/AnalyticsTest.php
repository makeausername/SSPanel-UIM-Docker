<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use function array_merge;
use function date;
use function time;

final class AnalyticsTest extends TestCase
{
    private Capsule $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new Capsule();
        $this->db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ], 'default');
        $this->db->setAsGlobal();
        $this->db->bootEloquent();

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();

        parent::tearDown();
    }

    public function testNodeCountsMergeLegacyHeartbeatAndXNodeRuntimeWithoutDoubleCounting(): void
    {
        $now = time();

        $this->seedNode(['id' => 1, 'node_heartbeat' => $now - 120]);
        $this->seedNode(['id' => 2, 'node_heartbeat' => 0]);
        $this->seedNode(['id' => 3, 'node_heartbeat' => $now]);
        $this->seedNode(['id' => 4, 'node_heartbeat' => 0]);
        $this->seedNode(['id' => 5, 'node_heartbeat' => 0]);

        $this->seedRuntime(['node_id' => 2, 'last_seen' => $now, 'state' => 'running']);
        $this->seedRuntime(['node_id' => 3, 'last_seen' => $now, 'state' => 'running']);
        $this->seedRuntime(['node_id' => 4, 'last_seen' => $now, 'state' => 'failed']);
        $this->seedRuntime(['node_id' => 5, 'last_seen' => $now - 120, 'state' => 'running']);
        $this->seedRuntime(['node_id' => 404, 'last_seen' => $now, 'state' => 'running']);

        $this->assertSame(5, Analytics::getTotalNode());
        $this->assertSame(2, Analytics::getAliveNode());
        $this->assertSame(4, Analytics::getXNodeTotalNode());
        $this->assertSame(2, Analytics::getXNodeAliveNode());
    }

    public function testXNodeRuntimeSummaryReturnsJoinedRuntimeRows(): void
    {
        $now = time();

        $this->seedNode([
            'id' => 10,
            'name' => 'XNode Alpha',
            'server' => 'alpha.example.com',
        ]);
        $this->seedRuntime([
            'node_id' => 10,
            'last_seen' => $now,
            'state' => 'running',
            'last_error' => '',
            'agent_version' => 'agent-1',
            'core_version' => 'xray-1',
        ]);
        $this->seedRuntime([
            'node_id' => 404,
            'last_seen' => $now,
            'state' => 'running',
        ]);

        $summary = Analytics::getXNodeRuntimeSummary();

        $this->assertCount(1, $summary);
        $this->assertSame(10, (int) $summary[0]->node_id);
        $this->assertSame('XNode Alpha', $summary[0]->name);
        $this->assertSame('alpha.example.com', $summary[0]->server);
        $this->assertSame('running', $summary[0]->state);
        $this->assertSame(date('Y-m-d H:i:s', $now), $summary[0]->last_seen_formatted);
        $this->assertSame('agent-1', $summary[0]->agent_version);
        $this->assertSame('xray-1', $summary[0]->core_version);
    }

    private function createSchema(): void
    {
        Capsule::schema()->create('node', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name')->default('');
            $table->string('server')->default('');
            $table->integer('node_heartbeat')->default(0);
        });

        Capsule::schema()->create('node_runtimes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('node_id');
            $table->string('state')->nullable();
            $table->integer('last_seen')->nullable();
            $table->text('last_error')->nullable();
            $table->string('agent_version')->nullable();
            $table->string('core_version')->nullable();
        });
    }

    private function seedNode(array $overrides): void
    {
        Capsule::table('node')->insert(array_merge([
            'id' => 1,
            'name' => 'Node',
            'server' => 'node.example.com',
            'node_heartbeat' => 0,
        ], $overrides));
    }

    private function seedRuntime(array $overrides): void
    {
        Capsule::table('node_runtimes')->insert(array_merge([
            'node_id' => 1,
            'state' => 'running',
            'last_seen' => 0,
            'last_error' => null,
            'agent_version' => null,
            'core_version' => null,
        ], $overrides));
    }
}
