<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

class NodeRuntimeServiceTest extends TestCase
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
        $this->seedTrafficLogConfig(false);
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();

        parent::tearDown();
    }

    public function testTrafficReportUpdatesUsageAndDuplicateReportDoesNotChargeTwice(): void
    {
        $this->seedNode([
            'id' => 1,
            'traffic_rate' => 2,
        ]);
        $this->seedUser([
            'id' => 10,
            'u' => 5,
            'd' => 7,
            'transfer_total' => 12,
            'transfer_today' => 12,
        ]);

        $payload = [
            'report_id' => 'traffic-report-1',
            'period_start' => 100,
            'period_end' => 200,
            'data' => [
                [
                    'user_id' => 10,
                    'u' => 123,
                    'd' => 456,
                ],
            ],
        ];

        $first = (new NodeRuntimeService())->acceptTraffic($payload, 1);

        $this->assertSame(true, $first['accepted']);
        $this->assertSame(false, $first['duplicate']);
        $this->assertSame(1, $first['users']);
        $this->assertSame(579, $first['bytes']);
        $this->assertSame(0, $first['skipped']);

        $user = Capsule::table('user')->where('id', 10)->first();
        $node = Capsule::table('node')->where('id', 1)->first();

        $this->assertSame(251, (int) $user->u);
        $this->assertSame(919, (int) $user->d);
        $this->assertSame(591, (int) $user->transfer_total);
        $this->assertSame(1170, (int) $user->transfer_today);
        $this->assertGreaterThan(0, (int) $user->last_use_time);
        $this->assertSame(579, (int) $node->node_bandwidth);
        $this->assertSame(1, Capsule::table('node_report_receipts')->where('report_id', 'traffic-report-1')->count());

        $second = (new NodeRuntimeService())->acceptTraffic($payload, 1);

        $this->assertSame(true, $second['accepted']);
        $this->assertSame(true, $second['duplicate']);
        $this->assertSame(0, $second['users']);
        $this->assertSame(0, $second['bytes']);
        $this->assertSame(0, $second['skipped']);

        $userAfterDuplicate = Capsule::table('user')->where('id', 10)->first();
        $nodeAfterDuplicate = Capsule::table('node')->where('id', 1)->first();

        $this->assertSame(251, (int) $userAfterDuplicate->u);
        $this->assertSame(919, (int) $userAfterDuplicate->d);
        $this->assertSame(591, (int) $userAfterDuplicate->transfer_total);
        $this->assertSame(1170, (int) $userAfterDuplicate->transfer_today);
        $this->assertSame(579, (int) $nodeAfterDuplicate->node_bandwidth);
    }

    public function testOnlineReportConvertsIpv4ToIpv4MappedIpv6(): void
    {
        $this->seedNode(['id' => 1]);

        $result = (new NodeRuntimeService())->acceptOnline([
            'report_id' => 'online-report-1',
            'data' => [
                [
                    'user_id' => 10,
                    'ip' => '1.2.3.4',
                ],
                [
                    'user_id' => 11,
                    'ip' => 'invalid-ip',
                ],
            ],
        ], 1);

        $this->assertSame(true, $result['accepted']);
        $this->assertSame(false, $result['duplicate']);
        $this->assertSame(1, $result['online_count']);
        $this->assertSame(1, $result['skipped_count']);

        $online = Capsule::table('online_log')->where('user_id', 10)->first();

        $this->assertSame('::ffff:1.2.3.4', $online->ip);
        $this->assertSame(1, (int) $online->node_id);
        $this->assertGreaterThan(0, (int) $online->first_time);
        $this->assertGreaterThan(0, (int) $online->last_time);
    }

    public function testInvalidTrafficPayloadDoesNotFatalOrCreateReceipt(): void
    {
        $result = (new NodeRuntimeService())->acceptTraffic([
            'report_id' => 'invalid-report-1',
            'data' => 'not-an-array',
        ], 1);

        $this->assertSame(false, $result['accepted']);
        $this->assertSame('invalid_data', $result['code']);
        $this->assertSame(0, Capsule::table('node_report_receipts')->where('report_id', 'invalid-report-1')->count());
    }

    public function testMissingNodeReturnsErrorWithoutFatalOrReceipt(): void
    {
        $result = (new NodeRuntimeService())->acceptTraffic([
            'report_id' => 'missing-node-report-1',
            'data' => [
                [
                    'user_id' => 10,
                    'u' => 1,
                    'd' => 2,
                ],
            ],
        ], 404);

        $this->assertSame(false, $result['accepted']);
        $this->assertSame('node_not_found', $result['code']);
        $this->assertSame(0, Capsule::table('node_report_receipts')->where('report_id', 'missing-node-report-1')->count());
    }

    public function testDisabledNodeDoesNotMutateUserTraffic(): void
    {
        $this->seedNode([
            'id' => 2,
            'type' => 0,
            'traffic_rate' => 2,
        ]);
        $this->seedUser([
            'id' => 20,
            'u' => 5,
            'd' => 7,
            'transfer_total' => 12,
            'transfer_today' => 12,
        ]);

        $result = (new NodeRuntimeService())->acceptTraffic([
            'report_id' => 'disabled-node-report-1',
            'data' => [
                [
                    'user_id' => 20,
                    'u' => 123,
                    'd' => 456,
                ],
            ],
        ], 2);

        $this->assertSame(false, $result['accepted']);
        $this->assertSame('node_disabled', $result['code']);

        $user = Capsule::table('user')->where('id', 20)->first();
        $node = Capsule::table('node')->where('id', 2)->first();

        $this->assertSame(5, (int) $user->u);
        $this->assertSame(7, (int) $user->d);
        $this->assertSame(12, (int) $user->transfer_total);
        $this->assertSame(12, (int) $user->transfer_today);
        $this->assertSame(0, (int) $node->node_bandwidth);
        $this->assertSame(0, Capsule::table('node_report_receipts')->where('report_id', 'disabled-node-report-1')->count());
    }

    public function testDetectLogReportStoresRuleIdAsLegacyListIdAndDuplicateDoesNotInsertTwice(): void
    {
        $this->seedNode(['id' => 1]);

        $payload = [
            'report_id' => 'detect-report-1',
            'data' => [
                [
                    'user_id' => 10,
                    'rule_id' => 7,
                    'ip' => '1.2.3.4',
                    'target' => 'example.com',
                ],
            ],
        ];

        $first = (new NodeRuntimeService())->acceptDetectLog($payload, 1);
        $second = (new NodeRuntimeService())->acceptDetectLog($payload, 1);

        $this->assertSame(true, $first['accepted']);
        $this->assertSame(false, $first['duplicate']);
        $this->assertSame(1, $first['count']);
        $this->assertSame(true, $second['accepted']);
        $this->assertSame(true, $second['duplicate']);
        $this->assertSame(0, $second['count']);

        $logs = Capsule::table('detect_log')->get();

        $this->assertCount(1, $logs);
        $this->assertSame(10, (int) $logs[0]->user_id);
        $this->assertSame(7, (int) $logs[0]->list_id);
        $this->assertSame(1, (int) $logs[0]->node_id);
        $this->assertGreaterThan(0, (int) $logs[0]->datetime);
    }

    public function testValidRealityMetadataIsNormalizedAndStoredAtomically(): void
    {
        $this->seedNode(['id' => 3]);
        $metadata = new XNodeRealityMetadataService();
        $hash = $metadata->calculateRealityHash(
            'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            ['FEDCBA9876543210', '0123456789ABCDEF', 'FEDCBA9876543210']
        );

        (new NodeRuntimeService())->acceptRuntime([
            'agent_version' => 'test-agent',
            'state' => ' RUNNING ',
            'public_key' => '  AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA  ',
            'short_ids' => ['FEDCBA9876543210', '0123456789ABCDEF', 'FEDCBA9876543210'],
            'reality_hash' => strtoupper((string) $hash),
            'last_error' => '',
        ], 3);

        $runtime = Capsule::table('node_runtimes')->where('node_id', 3)->first();

        $this->assertSame('running', $runtime->state);
        $this->assertSame('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', $runtime->public_key);
        $this->assertSame(
            '["0123456789abcdef","fedcba9876543210"]',
            $runtime->short_ids_json
        );
        $this->assertSame($hash, $runtime->reality_hash);
        $this->assertSame('', $runtime->last_error);
    }

    public function testMismatchedHashPreservesExistingValidRealityMetadata(): void
    {
        $this->seedNode(['id' => 4]);
        $metadata = new XNodeRealityMetadataService();
        $existingHash = $metadata->calculateRealityHash(
            'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            ['0123456789abcdef']
        );
        Capsule::table('node_runtimes')->insert([
            'node_id' => 4,
            'state' => 'running',
            'public_key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'short_ids_json' => '["0123456789abcdef"]',
            'reality_hash' => $existingHash,
            'last_seen' => 100,
            'last_error' => null,
            'created_at' => 100,
            'updated_at' => 100,
        ]);

        (new NodeRuntimeService())->acceptRuntime([
            'state' => 'running',
            'public_key' => 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB',
            'short_ids' => ['abcdef'],
            'reality_hash' => $existingHash,
            'last_error' => '',
        ], 4);

        $runtime = Capsule::table('node_runtimes')->where('node_id', 4)->first();

        $this->assertSame('AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', $runtime->public_key);
        $this->assertSame('["0123456789abcdef"]', $runtime->short_ids_json);
        $this->assertSame($existingHash, $runtime->reality_hash);
        $this->assertSame('reality_metadata_hash_mismatch', $runtime->last_error);
        $this->assertGreaterThan(100, (int) $runtime->last_seen);

        (new NodeRuntimeService())->acceptHeartbeat([
            'state' => 'running',
            'last_error' => '',
        ], 4);

        $runtimeAfterHeartbeat = Capsule::table('node_runtimes')->where('node_id', 4)->first();
        $this->assertSame('reality_metadata_hash_mismatch', $runtimeAfterHeartbeat->last_error);
    }

    public function testPrivateKeyPayloadIsNeverPersistedOrDefined(): void
    {
        $this->seedNode(['id' => 5]);
        $metadata = new XNodeRealityMetadataService();
        $hash = $metadata->calculateRealityHash(
            'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            ['0123456789abcdef']
        );

        (new NodeRuntimeService())->acceptRuntime([
            'state' => 'running',
            'public_key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'short_ids' => ['0123456789abcdef'],
            'reality_hash' => $hash,
            'private_key' => 'synthetic-value-that-must-be-ignored',
        ], 5);

        $this->assertSame(1, Capsule::table('node_runtimes')->where('node_id', 5)->count());
        $this->assertFalse(Capsule::schema()->hasColumn('node_runtimes', 'private_key'));
    }

    private function createSchema(): void
    {
        Capsule::schema()->create('config', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('item');
            $table->text('value')->nullable();
            $table->string('class')->default('');
            $table->string('is_public')->default('0');
            $table->string('type')->default('string');
            $table->text('default')->nullable();
            $table->text('mark')->nullable();
        });

        Capsule::schema()->create('node', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('type')->default(1);
            $table->double('traffic_rate')->default(1);
            $table->integer('is_dynamic_rate')->default(0);
            $table->integer('dynamic_rate_type')->default(0);
            $table->text('dynamic_rate_config')->nullable();
            $table->integer('node_bandwidth')->default(0);
            $table->integer('node_heartbeat')->default(0);
        });

        Capsule::schema()->create('user', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('u')->default(0);
            $table->integer('d')->default(0);
            $table->integer('transfer_total')->default(0);
            $table->integer('transfer_today')->default(0);
            $table->integer('last_use_time')->default(0);
        });

        Capsule::schema()->create('node_report_receipts', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('node_id');
            $table->string('report_id', 128)->unique();
            $table->string('report_type', 32);
            $table->integer('period_start')->nullable();
            $table->integer('period_end')->nullable();
            $table->integer('created_at');
            $table->index(['node_id', 'report_type', 'created_at'], 'node_type_created');
        });

        Capsule::schema()->create('online_log', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('ip');
            $table->integer('node_id');
            $table->integer('first_time');
            $table->integer('last_time');
            $table->unique(['user_id', 'ip']);
        });

        Capsule::schema()->create('detect_log', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id')->default(0);
            $table->integer('list_id')->default(0);
            $table->integer('datetime')->default(0);
            $table->integer('node_id')->default(0);
            $table->integer('status')->default(0);
        });

        Capsule::schema()->create('node_runtimes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('node_id')->unique();
            $table->string('agent_version')->nullable();
            $table->string('core_version')->nullable();
            $table->string('state')->nullable();
            $table->string('public_key')->nullable();
            $table->text('short_ids_json')->nullable();
            $table->string('reality_hash', 64)->nullable();
            $table->text('capabilities_json')->nullable();
            $table->string('config_hash')->nullable();
            $table->integer('last_seen')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at')->nullable();
        });
    }

    private function seedTrafficLogConfig(bool $enabled): void
    {
        Capsule::table('config')->insert([
            'item' => 'traffic_log',
            'value' => $enabled ? '1' : '0',
            'class' => '',
            'is_public' => '0',
            'type' => 'bool',
            'default' => '',
            'mark' => '',
        ]);
    }

    private function seedNode(array $overrides): void
    {
        Capsule::table('node')->insert(array_merge([
            'id' => 1,
            'type' => 1,
            'traffic_rate' => 1,
            'is_dynamic_rate' => 0,
            'dynamic_rate_type' => 0,
            'dynamic_rate_config' => '{}',
            'node_bandwidth' => 0,
        ], $overrides));
    }

    private function seedUser(array $overrides): void
    {
        Capsule::table('user')->insert(array_merge([
            'id' => 1,
            'u' => 0,
            'd' => 0,
            'transfer_total' => 0,
            'transfer_today' => 0,
            'last_use_time' => 0,
        ], $overrides));
    }
}
