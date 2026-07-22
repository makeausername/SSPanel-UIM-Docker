<?php

declare(strict_types=1);

namespace App\Middleware;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use function json_decode;

final class NodeTokenTest extends TestCase
{
    private Capsule $db;
    private array $previousEnv = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['webAPI', 'webAPIUrl', 'muKey', 'checkNodeIp', 'enable_rate_limit'] as $key) {
            $this->previousEnv[$key] = $_ENV[$key] ?? null;
        }

        $_ENV['webAPI'] = true;
        $_ENV['webAPIUrl'] = 'https://panel.example';
        $_ENV['muKey'] = 'legacy-global-key';
        $_ENV['checkNodeIp'] = true;
        $_ENV['enable_rate_limit'] = true;

        $this->db = new Capsule();
        $this->db->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ], 'default');
        $this->db->setAsGlobal();
        $this->db->bootEloquent();

        Capsule::schema()->create('node', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('type')->default(1);
            $table->string('password');
            $table->string('ipv4')->default('127.0.0.1');
            $table->string('ipv6')->default('::1');
        });

        Capsule::table('node')->insert([
            ['id' => 1, 'type' => 1, 'password' => 'node-one-key', 'ipv4' => '198.51.100.10', 'ipv6' => '2001:db8::10'],
            ['id' => 2, 'type' => 1, 'password' => 'node-two-key', 'ipv4' => '198.51.100.20', 'ipv6' => '2001:db8::20'],
            ['id' => 3, 'type' => 0, 'password' => 'node-three-key', 'ipv4' => '198.51.100.30', 'ipv6' => '2001:db8::30'],
        ]);

        AppFactory::setResponseFactory(new HttpFactory());
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();

        foreach ($this->previousEnv as $key => $value) {
            if ($value === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $value;
            }
        }

        parent::tearDown();
    }

    public function testPerNodeCredentialBindsAuthenticatedNode(): void
    {
        $handler = $this->handler();
        $request = $this->request(1, 'node-one-key', '198.51.100.10');

        $response = (new NodeToken(static fn (): bool => true))->process($request, $handler);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(1, $handler->request?->getAttribute('legacy_node_id'));
    }

    public function testGlobalCompatibilityKeyCannotCrossNodeBoundary(): void
    {
        $response = (new NodeToken(static fn (): bool => true))->process(
            $this->request(2, 'legacy-global-key', '198.51.100.10'),
            $this->handler()
        );
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Invalid request IP.', $payload['msg']);
    }

    public function testConflictingNodeIdentifiersAreRejected(): void
    {
        $request = $this->request(1, 'node-one-key', '198.51.100.10')
            ->withHeader('X-Node-Id', '2');

        $response = (new NodeToken(static fn (): bool => true))->process($request, $this->handler());

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testDisabledNodeIsRejected(): void
    {
        $response = (new NodeToken(static fn (): bool => true))->process(
            $this->request(3, 'node-three-key', '198.51.100.30'),
            $this->handler()
        );

        $this->assertSame(403, $response->getStatusCode());
    }

    private function request(int $nodeId, string $token, string $ip): ServerRequestInterface
    {
        return (new HttpFactory())
            ->createServerRequest(
                'GET',
                'https://panel.example/mod_mu/users?node_id=' . $nodeId,
                ['REMOTE_ADDR' => $ip]
            )
            ->withQueryParams(['node_id' => (string) $nodeId])
            ->withHeader('Authorization', 'Bearer ' . $token);
    }

    private function handler(): RequestHandlerInterface
    {
        return new class() implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Psr7Response(204);
            }
        };
    }
}
