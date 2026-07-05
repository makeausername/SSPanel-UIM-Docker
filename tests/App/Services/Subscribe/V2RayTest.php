<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

class V2RayTest extends TestCase
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

    public function testSort14NodeWithValidRuntimeEmitsVlessRealityLink(): void
    {
        $this->seedNode([
            'id' => 1401,
            'name' => 'XNode14',
            'server' => 'sort14.example.com',
            'sort' => 14,
        ]);
        $this->seedRuntime(1401, 'public-key-sort14', '["0123456789abcdef"]', 'hidden-private-key');

        $content = (new V2Ray())->getContent($this->user());

        $this->assertSame([
            'vless://11111111-2222-3333-4444-555555555555@sort14.example.com:443?'
            . 'encryption=none&security=reality&sni=www.cloudflare.com&fp=chrome'
            . '&pbk=public-key-sort14&sid=0123456789abcdef&type=tcp'
            . '&flow=xtls-rprx-vision#XNode14',
        ], $this->subscriptionLines($content));
        $this->assertStringNotContainsString('vmess://', $content);
        $this->assertStringNotContainsString('hidden-private-key', $content);
        $this->assertStringNotContainsString('private_key', $content);
    }

    public function testSort15NodeWithValidRuntimeEmitsVlessRealityLink(): void
    {
        $this->seedNode([
            'id' => 1501,
            'name' => 'XNode15',
            'server' => 'sort15.example.com',
            'sort' => 15,
        ]);
        $this->seedRuntime(1501, 'public-key-sort15', '["0123456789abcdef"]', 'hidden-private-key');

        $content = (new V2Ray())->getContent($this->user());

        $this->assertSame([
            'vless://11111111-2222-3333-4444-555555555555@sort15.example.com:443?'
            . 'encryption=none&security=reality&sni=www.cloudflare.com&fp=chrome'
            . '&pbk=public-key-sort15&sid=0123456789abcdef&type=tcp'
            . '&flow=xtls-rprx-vision#XNode15',
        ], $this->subscriptionLines($content));
        $this->assertStringNotContainsString('vmess://', $content);
        $this->assertStringNotContainsString('hidden-private-key', $content);
        $this->assertStringNotContainsString('private_key', $content);
    }

    public function testSort11NodeKeepsLegacyVmessAndCanAlsoEmitVlessRealityLink(): void
    {
        $this->seedNode([
            'id' => 1101,
            'name' => 'Sort11Node',
            'server' => 'sort11.example.com',
            'sort' => 11,
            'custom_config' => json_encode([
                'offset_port_user' => 8443,
                'security' => 'tls',
                'network' => 'ws',
                'path' => '/ray',
                'host' => 'host.example.com',
            ]),
        ]);
        $this->seedRuntime(1101, 'public-key-sort11', '["fedcba9876543210"]');

        $lines = $this->subscriptionLines((new V2Ray())->getContent($this->user()));

        $this->assertCount(2, $lines);
        $this->assertStringStartsWith('vmess://', $lines[0]);
        $payload = json_decode(base64_decode(substr($lines[0], 8)), true);
        $this->assertSame('Sort11Node', $payload['ps']);
        $this->assertSame('sort11.example.com', $payload['add']);
        $this->assertSame(8443, $payload['port']);
        $this->assertSame(
            'vless://11111111-2222-3333-4444-555555555555@sort11.example.com:443?'
            . 'encryption=none&security=reality&sni=www.cloudflare.com&fp=chrome'
            . '&pbk=public-key-sort11&sid=fedcba9876543210&type=tcp'
            . '&flow=xtls-rprx-vision#Sort11Node',
            $lines[1]
        );
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

    public function testMalformedShortIdsJsonSkipsVlessLinkFromSubscription(): void
    {
        $this->seedNode([
            'id' => 1402,
            'name' => 'MalformedRuntime',
            'server' => 'malformed-runtime.example.com',
            'sort' => 14,
        ]);
        $this->seedRuntime(1402, 'public-key-example', '{malformed-json');

        $this->assertSame('', (new V2Ray())->getContent($this->user()));
    }

    public function testBuildXNodeVlessRealityUrlEmitsExpectedLink(): void
    {
        $user = $this->makeObject([
            'uuid' => '11111111-2222-3333-4444-555555555555',
        ]);
        $node = $this->makeObject([
            'id' => 2001,
            'name' => 'XNode Alpha',
            'server' => 'node.example.com',
        ]);
        $this->seedRuntime(2001, 'public+key/example=', '["0123456789abcdef"]');

        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [$user, $node]);

        $this->assertSame(
            'vless://11111111-2222-3333-4444-555555555555@node.example.com:443?'
            . 'encryption=none&security=reality&sni=www.cloudflare.com&fp=chrome'
            . '&pbk=public%2Bkey%2Fexample%3D&sid=0123456789abcdef&type=tcp'
            . '&flow=xtls-rprx-vision#XNode%20Alpha',
            $url
        );
    }

    public function testParseFirstShortIdReturnsFirstValidEvenLengthHexId(): void
    {
        $shortId = $this->invokeV2Ray('parseFirstShortId', [
            '["", "not-hex", "abcd", "0123456789abcdef"]',
        ]);

        $this->assertSame('abcd', $shortId);
    }

    public function testMalformedShortIdsJsonSkipsXNodeLink(): void
    {
        $this->seedRuntime(2002, 'public-key-example', '{malformed-json');
        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [
            $this->makeObject(['uuid' => '11111111-2222-3333-4444-555555555555']),
            $this->makeObject(['id' => 2002, 'name' => 'XNode Alpha', 'server' => 'node.example.com']),
        ]);

        $this->assertNull($url);
    }

    public function testEmptyPublicKeySkipsXNodeLink(): void
    {
        $this->seedRuntime(2003, '   ', '["0123456789abcdef"]');
        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [
            $this->makeObject(['uuid' => '11111111-2222-3333-4444-555555555555']),
            $this->makeObject(['id' => 2003, 'name' => 'XNode Alpha', 'server' => 'node.example.com']),
        ]);

        $this->assertNull($url);
    }

    public function testXNodeUrlDoesNotExposePrivateKeyData(): void
    {
        $this->seedRuntime(2004, 'public-key-example', '["0123456789abcdef"]', 'hidden-private-key');

        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [
            $this->makeObject(['uuid' => '11111111-2222-3333-4444-555555555555']),
            $this->makeObject(['id' => 2004, 'name' => 'XNode Alpha', 'server' => 'node.example.com']),
        ]);

        $this->assertIsString($url);
        $this->assertStringNotContainsString('hidden-private-key', $url);
        $this->assertStringNotContainsString('private_key', $url);
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
            $table->string('public_key')->nullable();
            $table->text('short_ids_json')->nullable();
            $table->string('private_key')->nullable();
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

    private function seedRuntime(
        int $nodeId,
        string $publicKey,
        string $shortIdsJson,
        ?string $privateKey = null
    ): void {
        Capsule::table('node_runtimes')->insert([
            'node_id' => $nodeId,
            'public_key' => $publicKey,
            'short_ids_json' => $shortIdsJson,
            'private_key' => $privateKey,
        ]);
    }

    private function user(array $overrides = []): stdClass
    {
        return $this->makeObject(array_merge([
            'uuid' => '11111111-2222-3333-4444-555555555555',
            'class' => 1,
            'node_group' => 0,
            'is_admin' => false,
        ], $overrides));
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
