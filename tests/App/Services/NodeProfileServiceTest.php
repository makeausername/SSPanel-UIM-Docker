<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class NodeProfileServiceTest extends TestCase
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

    public function testBuildDefaultConfigUsesAgentContractShape(): void
    {
        $this->seedNode([
            'id' => 1001,
            'server' => 'node1.example.com',
            'sort' => 15,
            'custom_config' => json_encode([
                'xnode' => [
                    'port' => 8443,
                    'sni' => 'www.example.com',
                    'target' => 'www.example.com:443',
                ],
            ]),
        ]);
        $config = (new NodeProfileService())->buildDefaultConfig(1001, 'node1.example.com');

        $this->assertSame(1, $config['schema_version']);
        $this->assertSame(1001, $config['node_id']);
        $this->assertSame('node1.example.com', $config['domain']);
        $this->assertSame('vless-reality-vision', $config['profile']['name']);
        $this->assertSame('reality', $config['profile']['security']);
        $this->assertSame(8443, $config['profile']['port']);
        $this->assertSame('www.example.com:443', $config['reality']['target']);
        $this->assertSame(['www.example.com'], $config['reality']['server_names']);
        $this->assertSame(30, $config['report']['heartbeat_interval_sec']);
        $this->assertArrayHasKey('config_hash', $config);
        $this->assertSame(64, strlen($config['config_hash']));
    }

    public function testSyncFromNodePersistsVersionedProfileOnlyWhenItChanges(): void
    {
        $this->seedNode([
            'id' => 22,
            'server' => 'node22.example.com',
            'sort' => 15,
            'custom_config' => '{"xnode":{"port":443,"sni":"one.example.com"}}',
        ]);
        $node = \App\Models\Node::find(22);
        $service = new NodeProfileService();

        $service->syncFromNode($node);
        $first = $service->buildDefaultConfig(22, '');
        $service->syncFromNode($node);
        $unchanged = $service->buildDefaultConfig(22, '');

        $this->assertSame(1, $first['version']);
        $this->assertSame($first['config_hash'], $unchanged['config_hash']);

        $node->custom_config = '{"xnode":{"port":8443,"sni":"two.example.com"}}';
        $service->syncFromNode($node);
        $changed = $service->buildDefaultConfig(22, '');

        $this->assertSame(2, $changed['version']);
        $this->assertSame(8443, $changed['profile']['port']);
        $this->assertNotSame($first['config_hash'], $changed['config_hash']);
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

    public function testBuildUsersForNodeReturnsEligibleRealUsersForRollout(): void
    {
        $subscriptionUserUuid = '01b60f94-488b-4dc2-ab9b-73aafc48317a';
        $this->seedNode([
            'id' => 1,
            'node_class' => 2,
            'node_group' => 3,
        ]);
        $this->seedUser([
            'id' => 1,
            'uuid' => $subscriptionUserUuid,
            'class' => 2,
            'node_group' => 3,
            'transfer_enable' => 1000,
            'u' => 100,
            'd' => 200,
        ]);
        $this->seedUser([
            'id' => 2,
            'uuid' => '22222222-2222-2222-2222-222222222222',
            'is_banned' => 1,
            'class' => 2,
            'node_group' => 3,
            'transfer_enable' => 1000,
        ]);
        $this->seedUser([
            'id' => 3,
            'uuid' => '33333333-3333-3333-3333-333333333333',
            'class' => 2,
            'node_group' => 3,
            'transfer_enable' => 0,
            'u' => 100,
            'd' => 200,
        ]);
        $this->seedUser([
            'id' => 4,
            'uuid' => '44444444-4444-4444-4444-444444444444',
            'class' => 1,
            'node_group' => 3,
            'transfer_enable' => 1000,
        ]);
        $this->seedUser([
            'id' => 5,
            'uuid' => '55555555-5555-5555-5555-555555555555',
            'class' => 2,
            'node_group' => 4,
            'transfer_enable' => 1000,
        ]);
        $this->seedUser([
            'id' => 6,
            'uuid' => '',
            'class' => 2,
            'node_group' => 3,
            'transfer_enable' => 1000,
        ]);
        $this->seedUser([
            'id' => 10,
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'class' => 10,
            'node_group' => 3,
            'transfer_enable' => 1000,
        ]);
        $this->seedUser([
            'id' => 7,
            'uuid' => '77777777-7777-7777-7777-777777777777',
            'class' => 2,
            'class_expire' => '2000-01-01 00:00:00',
            'node_group' => 3,
            'transfer_enable' => 1000,
        ]);
        $this->seedUser([
            'id' => 8,
            'uuid' => '88888888-8888-8888-8888-888888888888',
            'class' => 0,
            'node_group' => 3,
            'transfer_enable' => 1000,
        ]);
        $this->seedUser([
            'id' => 9,
            'uuid' => '99999999-9999-9999-9999-999999999999',
            'class' => 2,
            'node_group' => 3,
            'transfer_enable' => 1000,
            'unpaid_delete_at' => '2099-01-04 00:00:00',
        ]);

        Capsule::connection()->enableQueryLog();

        $users = (new NodeProfileService())->buildUsersForNode(1);

        $this->assertSame([
            [
                'id' => 1,
                'uuid' => $subscriptionUserUuid,
                'email' => 'user-1@panel.local',
                'speed_limit_mbps' => 0,
                'ip_limit' => 0,
                'enabled' => true,
                'updated_at' => 0,
            ],
        ], $users);
        $this->assertSame($subscriptionUserUuid, $users[0]['uuid']);
        $this->assertSame('user-1@panel.local', $users[0]['email']);
        $this->assertNotContains('11111111-1111-1111-1111-111111111111', array_column($users, 'uuid'));

        $executedSql = implode("\n", array_column(Capsule::connection()->getQueryLog(), 'query'));
        $this->assertSame(0, preg_match('/["`]uuid["`]\s*(?:<>|!=)\s*\?/', $executedSql), $executedSql);
        $this->assertStringContainsString('transfer_enable', $executedSql);
        $this->assertStringContainsString('class_expire', $executedSql);
    }

    public function testBuildUsersForNodeAllowsAnyUserGroupForGroupZeroNode(): void
    {
        $this->seedNode([
            'id' => 1002,
            'node_group' => 0,
        ]);
        $this->seedUser([
            'id' => 7,
            'uuid' => '77777777-7777-7777-7777-777777777777',
            'node_group' => 0,
            'transfer_enable' => 1000,
        ]);
        $this->seedUser([
            'id' => 8,
            'uuid' => '88888888-8888-8888-8888-888888888888',
            'node_group' => 8,
            'transfer_enable' => 1000,
        ]);

        $users = (new NodeProfileService())->buildUsersForNode(1002);

        $this->assertSame([
            '77777777-7777-7777-7777-777777777777',
            '88888888-8888-8888-8888-888888888888',
        ], array_column($users, 'uuid'));
    }

    public function testAdministratorsBypassClassAndGroupButStillHonorSafetyExclusions(): void
    {
        $this->seedNode([
            'id' => 1003,
            'node_class' => 10,
            'node_group' => 20,
        ]);
        $this->seedUser([
            'id' => 11,
            'uuid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
            'is_admin' => 1,
            'class' => 0,
            'node_group' => 0,
        ]);
        $this->seedUser([
            'id' => 12,
            'uuid' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
            'is_admin' => 1,
            'is_banned' => 1,
            'class' => 0,
            'node_group' => 0,
        ]);
        $this->seedUser([
            'id' => 13,
            'uuid' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
            'class' => 9,
            'node_group' => 20,
        ]);
        $this->seedUser([
            'id' => 14,
            'uuid' => 'dddddddd-dddd-dddd-dddd-dddddddddddd',
            'class' => 10,
            'node_group' => 21,
        ]);
        $this->seedUser([
            'id' => 15,
            'uuid' => 'eeeeeeee-eeee-eeee-eeee-eeeeeeeeeeee',
            'class' => 10,
            'node_group' => 20,
        ]);
        $this->seedUser([
            'id' => 16,
            'uuid' => '   ',
            'is_admin' => 1,
        ]);
        $this->seedUser([
            'id' => 17,
            'uuid' => '11111111-1111-1111-1111-111111111111',
            'is_admin' => 1,
        ]);

        $users = (new NodeProfileService())->buildUsersForNode(1003);

        $this->assertSame([11, 15], array_column($users, 'id'));
        $this->assertSame(
            ['id', 'uuid', 'email', 'speed_limit_mbps', 'ip_limit', 'enabled', 'updated_at'],
            array_keys($users[0])
        );
        $this->assertArrayNotHasKey('is_admin', $users[0]);
    }

    public function testBuildUsersForNodeReturnsEmptyArrayForMissingNode(): void
    {
        $this->seedUser([
            'id' => 9,
            'uuid' => '99999999-9999-9999-9999-999999999999',
            'transfer_enable' => 1000,
        ]);

        $this->assertSame([], (new NodeProfileService())->buildUsersForNode(404));
    }

    public function testBuildDetectRulesUsesAgentContractShapeAndPreservesEmptyState(): void
    {
        $service = new NodeProfileService();
        $this->assertSame([], $service->buildDetectRules());

        Capsule::table('detect_list')->insert(['id' => 7, 'regex' => 'bittorrent']);
        $rules = $service->buildDetectRules();

        $this->assertNotEmpty($rules);

        foreach ($rules as $rule) {
            $this->assertSame(['id', 'type', 'pattern'], array_keys($rule));
            $this->assertIsInt($rule['id']);
            $this->assertSame('protocol', $rule['type']);
            $this->assertIsString($rule['pattern']);
        }
    }

    private function createSchema(): void
    {
        Capsule::schema()->create('node', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('node_class')->default(0);
            $table->integer('node_group')->default(0);
            $table->string('server')->default('node.example.com');
            $table->integer('sort')->default(15);
            $table->text('custom_config')->default('{}');
        });

        Capsule::schema()->create('node_profiles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('node_id')->unique();
            $table->text('profile_json')->nullable();
            $table->integer('version')->default(1);
            $table->integer('created_at');
            $table->integer('updated_at')->nullable();
        });

        Capsule::schema()->create('detect_list', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->text('regex');
        });

        Capsule::schema()->create('user', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('uuid')->nullable();
            $table->integer('is_admin')->default(0);
            $table->integer('is_banned')->default(0);
            $table->integer('class')->default(0);
            $table->string('class_expire')->nullable();
            $table->integer('node_group')->default(0);
            $table->string('unpaid_delete_at')->nullable();
            $table->integer('transfer_enable')->default(0);
            $table->integer('u')->default(0);
            $table->integer('d')->default(0);
        });
    }

    private function seedNode(array $overrides): void
    {
        Capsule::table('node')->insert(array_merge([
            'id' => 1001,
            'node_class' => 0,
            'node_group' => 0,
            'server' => 'node.example.com',
            'sort' => 15,
            'custom_config' => '{}',
        ], $overrides));
    }

    private function seedUser(array $overrides): void
    {
        Capsule::table('user')->insert(array_merge([
            'id' => 1,
            'uuid' => '00000000-0000-0000-0000-000000000000',
            'is_admin' => 0,
            'is_banned' => 0,
            'class' => 1,
            'class_expire' => '2099-01-01 00:00:00',
            'node_group' => 0,
            'unpaid_delete_at' => null,
            'transfer_enable' => 1000,
            'u' => 0,
            'd' => 0,
        ], $overrides));
    }
}
