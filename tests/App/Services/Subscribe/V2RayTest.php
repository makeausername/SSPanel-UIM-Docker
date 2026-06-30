<?php

declare(strict_types=1);

namespace App\Services\Subscribe;

use App\Models\NodeRuntime;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use stdClass;

class V2RayTest extends TestCase
{
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

    public function testBuildXNodeVlessRealityUrlEmitsExpectedLink(): void
    {
        $user = $this->makeObject([
            'uuid' => '11111111-2222-3333-4444-555555555555',
        ]);
        $node = $this->makeObject([
            'name' => 'XNode Alpha',
            'server' => 'node.example.com',
        ]);
        $runtime = $this->runtime('public+key/example=', '["0123456789abcdef"]');

        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [$user, $node, $runtime]);

        $this->assertSame(
            'vless://11111111-2222-3333-4444-555555555555@node.example.com:443?'
            . 'encryption=none&security=reality&sni=www.microsoft.com&fp=chrome'
            . '&pbk=public%2Bkey%2Fexample%3D&sid=0123456789abcdef&type=tcp'
            . '&flow=xtls-rprx-vision#XNode%20Alpha',
            $url
        );
    }

    public function testParseFirstShortIdReturnsFirstValidLowercaseHexId(): void
    {
        $shortId = $this->invokeV2Ray('parseFirstShortId', [
            '["", "0123456789ABCDEF", "0123456789abcdef"]',
        ]);

        $this->assertSame('0123456789abcdef', $shortId);
    }

    public function testMalformedShortIdsJsonSkipsXNodeLink(): void
    {
        $runtime = $this->runtime('public-key-example', '{malformed-json');
        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [
            $this->makeObject(['uuid' => '11111111-2222-3333-4444-555555555555']),
            $this->makeObject(['name' => 'XNode Alpha', 'server' => 'node.example.com']),
            $runtime,
        ]);

        $this->assertNull($url);
    }

    public function testEmptyPublicKeySkipsXNodeLink(): void
    {
        $runtime = $this->runtime('   ', '["0123456789abcdef"]');
        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [
            $this->makeObject(['uuid' => '11111111-2222-3333-4444-555555555555']),
            $this->makeObject(['name' => 'XNode Alpha', 'server' => 'node.example.com']),
            $runtime,
        ]);

        $this->assertNull($url);
    }

    public function testXNodeUrlDoesNotExposePrivateKeyData(): void
    {
        $runtime = $this->runtime('public-key-example', '["0123456789abcdef"]');
        $runtime->private_key = 'hidden-private-key';

        $url = $this->invokeV2Ray('buildXNodeVlessRealityUrl', [
            $this->makeObject(['uuid' => '11111111-2222-3333-4444-555555555555']),
            $this->makeObject(['name' => 'XNode Alpha', 'server' => 'node.example.com']),
            $runtime,
        ]);

        $this->assertIsString($url);
        $this->assertStringNotContainsString('hidden-private-key', $url);
        $this->assertStringNotContainsString('private_key', $url);
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

    private function runtime(string $publicKey, string $shortIdsJson): NodeRuntime
    {
        $runtime = new NodeRuntime();
        $runtime->public_key = $publicKey;
        $runtime->short_ids_json = $shortIdsJson;

        return $runtime;
    }
}
