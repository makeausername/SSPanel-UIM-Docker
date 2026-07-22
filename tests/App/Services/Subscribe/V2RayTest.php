<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Models\User;
use App\Services\NodeProfileService;
use App\Services\XNodeRealityMetadataService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

class V2RayTest extends TestCase
{
    private const PUBLIC_KEY = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';

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
        $this->seedV2RayConfig();
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();

        parent::tearDown();
    }

    public function testLegacyVmessUrlMatchesExistingSubscriptionShape(): void
    {
        $user = $this->makeObject([
            'uuid' => '11111111-2222-3333-4444-555555555555',
        ]);
        $node = $this->makeObject([
            'name' => 'VMess Node',
            'server' => 'vmess.example.com',
        ]);
        $customConfig = [
            'offset_port_user' => 8443,
            'security' => 'tls',
            'network' => 'ws',
            'header' => [
                'type' => 'http',
                'request' => [
                    'headers' => [
                        'Host' => ['host.example.com'],
                    ],
                    'path' => ['/ray'],
                ],
            ],
        ];

        $url = $this->invokeV2Ray('buildLegacyVmessUrl', [$user, $node, $customConfig]);
        $payload = json_decode(base64_decode(substr($url, 8)), true);

        $this->assertStringStartsWith('vmess://', $url);
        $this->assertSame([
            'v' => '2',
            'ps' => 'VMess Node',
            'add' => 'vmess.example.com',
            'port' => 8443,
            'id' => '11111111-2222-3333-4444-555555555555',
            'aid' => 0,
            'net' => 'ws',
            'type' => 'http',
            'host' => 'host.example.com',
            'path' => '/ray',
            'tls' => 'tls',
        ], $payload);
    }

    public function testVerifiedRuntimeUsesSharedProfileFieldsAndCanonicalShortId(): void
    {
        $this->seedNode([
            'id' => 2001,
            'name' => 'XNode Alpha',
            'server' => 'node.example.com',
            'sort' => 15,
        ]);
        $this->seedRuntime(2001, [
            'short_ids_json' => '["fedcba9876543210","0123456789abcdef"]',
            'last_error' => '   ',
        ]);
        $user = $this->makeObject(['uuid' => '11111111-2222-3333-4444-555555555555']);
        $node = $this->makeObject([
            'id' => 2001,
            'name' => 'XNode Alpha',
            'server' => 'node.example.com',
        ]);

        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [$user, $node]);
        $profile = (new NodeProfileService())->buildDefaultConfig(2001, 'node.example.com');

        $this->assertSame(
            'vless://11111111-2222-3333-4444-555555555555@node.example.com:443?'
            . 'encryption=none&security=reality&sni=www.cloudflare.com&fp=chrome'
            . '&pbk=' . self::PUBLIC_KEY . '&sid=0123456789abcdef&type=tcp'
            . '&flow=xtls-rprx-vision#XNode%20Alpha',
            $url
        );
        $this->assertSame(443, $profile['profile']['port']);
        $this->assertSame('xtls-rprx-vision', $profile['profile']['flow']);
        $this->assertSame('www.cloudflare.com', $profile['reality']['server_names'][0]);
        $this->assertSame('chrome', $profile['reality']['fingerprint']);
        $this->assertSame('tcp', $this->invokeV2Ray('mapProfileNetworkToUriType', ['raw']));
    }

    public function testRunningFreshErrorFreeHashValidRuntimeProducesSubscriptionLine(): void
    {
        $this->seedNode([
            'id' => 1401,
            'name' => 'XNode14',
            'server' => 'sort14.example.com',
            'sort' => 14,
        ]);
        $this->seedRuntime(1401);

        $lines = $this->subscriptionLines((new V2Ray())->getContent($this->user()));

        $this->assertCount(1, $lines);
        $this->assertStringStartsWith('vless://', $lines[0]);
        $this->assertStringContainsString('pbk=' . self::PUBLIC_KEY, $lines[0]);
    }

    public function testNonRunningRuntimeIsSkipped(): void
    {
        $this->assertRuntimeIsSkipped(['state' => 'stopped']);
    }

    public function testStaleRuntimeIsSkipped(): void
    {
        $this->assertRuntimeIsSkipped(['last_seen' => time() - 181]);
    }

    public function testRuntimeWithLastErrorIsSkipped(): void
    {
        $this->assertRuntimeIsSkipped(['last_error' => 'runtime_failed']);
    }

    public function testRuntimeWithInvalidPublicKeyIsSkipped(): void
    {
        $this->assertRuntimeIsSkipped(['public_key' => 'invalid-public-key']);
    }

    public function testRuntimeWithInvalidShortIdIsSkipped(): void
    {
        $this->assertRuntimeIsSkipped(['short_ids_json' => '["invalid-short-id"]']);
    }

    public function testRuntimeWithMissingRealityHashIsSkipped(): void
    {
        $this->assertRuntimeIsSkipped(['reality_hash' => null]);
    }

    public function testRuntimeWithMismatchedRealityHashIsSkipped(): void
    {
        $this->assertRuntimeIsSkipped(['reality_hash' => str_repeat('0', 64)]);
    }

    public function testMalformedShortIdsJsonIsSkipped(): void
    {
        $this->assertRuntimeIsSkipped(['short_ids_json' => '{malformed-json']);
    }

    public function testNewestUnusableRuntimeDoesNotFallBackToOlderMetadata(): void
    {
        $this->seedRuntime(2001, [
            'updated_at' => time() - 10,
            'last_seen' => time() - 10,
        ]);
        $this->seedRuntime(2001, [
            'state' => 'stopped',
            'updated_at' => time(),
        ]);

        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [
            $this->makeObject(['uuid' => '11111111-2222-3333-4444-555555555555']),
            $this->makeObject(['id' => 2001, 'name' => 'XNode', 'server' => 'node.example.com']),
        ]);

        $this->assertNull($url);
    }

    public function testMissingRuntimeSkipsVlessWithoutDroppingLegacyVmess(): void
    {
        $this->seedNode([
            'id' => 1102,
            'name' => 'LegacyOnly',
            'server' => 'legacy-only.example.com',
            'sort' => 11,
        ]);

        $lines = $this->subscriptionLines((new V2Ray())->getContent($this->user()));

        $this->assertCount(1, $lines);
        $this->assertStringStartsWith('vmess://', $lines[0]);
        $this->assertStringNotContainsString('vless://', $lines[0]);
    }

    private function assertRuntimeIsSkipped(array $runtimeOverrides): void
    {
        $this->seedRuntime(2001, $runtimeOverrides);
        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [
            $this->makeObject(['uuid' => '11111111-2222-3333-4444-555555555555']),
            $this->makeObject(['id' => 2001, 'name' => 'XNode', 'server' => 'node.example.com']),
        ]);

        $this->assertNull($url);
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
            $table->string('name');
            $table->integer('type')->default(1);
            $table->string('server');
            $table->text('custom_config')->nullable();
            $table->integer('sort')->default(14);
            $table->integer('node_class')->default(0);
            $table->integer('node_group')->default(0);
            $table->integer('node_bandwidth')->default(0);
            $table->integer('node_bandwidth_limit')->default(0);
        });

        Capsule::schema()->create('node_runtimes', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('node_id');
            $table->string('state')->nullable();
            $table->string('public_key')->nullable();
            $table->text('short_ids_json')->nullable();
            $table->string('reality_hash', 64)->nullable();
            $table->integer('last_seen')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('created_at');
            $table->integer('updated_at')->nullable();
        });

        Capsule::schema()->create('node_profiles', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('node_id')->unique();
            $table->text('profile_json')->nullable();
            $table->integer('version')->default(1);
            $table->integer('created_at');
            $table->integer('updated_at')->nullable();
        });
    }

    private function seedV2RayConfig(): void
    {
        Capsule::table('config')->insert([
            'item' => 'enable_v2_sub',
            'value' => '1',
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
            'id' => 1001,
            'name' => 'XNode',
            'type' => 1,
            'server' => 'node.example.com',
            'custom_config' => '{}',
            'sort' => 14,
            'node_class' => 0,
            'node_group' => 0,
            'node_bandwidth' => 0,
            'node_bandwidth_limit' => 0,
        ], $overrides));
    }

    private function seedRuntime(int $nodeId, array $overrides = []): void
    {
        $defaults = [
            'node_id' => $nodeId,
            'state' => 'running',
            'public_key' => self::PUBLIC_KEY,
            'short_ids_json' => '["0123456789abcdef"]',
            'reality_hash' => null,
            'last_seen' => time(),
            'last_error' => null,
            'created_at' => time(),
            'updated_at' => time(),
        ];
        $runtime = array_merge($defaults, $overrides);

        if (! array_key_exists('reality_hash', $overrides)) {
            $runtime['reality_hash'] = (new XNodeRealityMetadataService())->calculateRealityHash(
                $runtime['public_key'],
                $runtime['short_ids_json']
            );
        }

        Capsule::table('node_runtimes')->insert($runtime);
    }

    private function user(array $overrides = []): User
    {
        $user = new User();
        $user->forceFill(array_merge([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'class' => 1,
            'class_expire' => '2099-01-01 00:00:00',
            'node_group' => 0,
            'is_admin' => 0,
            'is_banned' => 0,
            'unpaid_delete_at' => null,
        ], $overrides));

        return $user;
    }

    /**
     * @return list<string>
     */
    private function subscriptionLines(string $content): array
    {
        if (trim($content) === '') {
            return [];
        }

        return array_values(array_filter(explode(PHP_EOL, trim($content))));
    }

    private function invokeV2Ray(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod(V2Ray::class, $method);

        return $reflection->invokeArgs(new V2Ray(), $args);
    }

    private function makeObject(array $properties): stdClass
    {
        $object = new stdClass();
        foreach ($properties as $key => $value) {
            $object->{$key} = $value;
        }

        return $object;
    }
}
