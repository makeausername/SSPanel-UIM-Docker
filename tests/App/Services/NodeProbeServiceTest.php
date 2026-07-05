<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

class NodeProbeServiceTest extends TestCase
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
        Capsule::table('node')->insert([
            'id' => 1,
            'name' => 'node-1',
            'gfw_block' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();

        parent::tearDown();
    }

    public function testRecordResultStoresExternalProbeResultAndState(): void
    {
        $summary = NodeProbeService::recordResult([
            'node_id' => 1,
            'probe_region' => 'cn',
            'probe_provider' => 'aliyun',
            'probe_location' => 'cn-mainland-1',
            'probe_type' => 'external_tcp',
            'target_host' => 'node1.example.com',
            'status' => 'ok',
            'latency_ms' => 32,
            'error' => '',
            'checked_at' => 1760000000,
        ], true);

        $result = Capsule::table('node_probe_results')->where('node_id', 1)->first();
        $state = Capsule::table('node_probe_states')->where('node_id', 1)->first();

        $this->assertSame('ok', $summary['status']);
        $this->assertNotNull($result);
        $this->assertSame('cn', $result->probe_region);
        $this->assertSame('aliyun', $result->probe_provider);
        $this->assertSame('cn-mainland-1', $result->probe_location);
        $this->assertSame('external_tcp', $result->probe_type);
        $this->assertSame('node1.example.com', $result->target_host);
        $this->assertSame(443, (int) $result->target_port);
        $this->assertSame('ok', $result->status);
        $this->assertSame(32, (int) $result->latency_ms);
        $this->assertSame(1760000000, (int) $result->checked_at);
        $this->assertNotNull($state);
        $this->assertSame('ok', $state->status);
        $this->assertSame('external_tcp', $state->probe_type);
        $this->assertSame('node1.example.com', $state->target_host);
    }

    private function createSchema(): void
    {
        Capsule::schema()->create('node', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('name')->nullable();
            $table->integer('gfw_block')->default(0);
        });

        Capsule::schema()->create('node_probe_results', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('node_id');
            $table->string('probe_region', 64);
            $table->string('probe_provider', 64)->nullable();
            $table->string('probe_location', 128)->nullable();
            $table->string('probe_type', 32);
            $table->string('target_host', 255);
            $table->integer('target_port')->default(443);
            $table->string('status', 32);
            $table->integer('latency_ms')->nullable();
            $table->string('error', 512)->nullable();
            $table->integer('checked_at');
            $table->integer('created_at');
        });

        Capsule::schema()->create('node_probe_states', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('node_id')->unique();
            $table->string('status', 32);
            $table->string('previous_status', 32)->nullable();
            $table->string('probe_region', 64)->nullable();
            $table->string('probe_provider', 64)->nullable();
            $table->string('probe_location', 128)->nullable();
            $table->string('probe_type', 32)->nullable();
            $table->string('target_host', 255)->nullable();
            $table->integer('target_port')->default(443);
            $table->integer('latency_ms')->nullable();
            $table->string('error', 512)->nullable();
            $table->integer('last_checked_at')->nullable();
            $table->integer('last_changed_at')->nullable();
            $table->string('last_notified_status', 32)->nullable();
            $table->integer('last_notified_at')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at');
        });
    }
}
