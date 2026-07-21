<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\NodeEnrollmentService;
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

final class NodeApiTokenTest extends TestCase
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

        Capsule::schema()->create('node_tokens', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('node_id');
            $table->string('token_hash')->unique();
            $table->string('token_type', 32)->default('node');
            $table->string('name', 64)->nullable();
            $table->integer('last_used_at')->nullable();
            $table->integer('expires_at')->nullable();
            $table->integer('used_at')->nullable();
            $table->integer('revoked_at')->nullable();
            $table->integer('created_at');
        });
        Capsule::schema()->create('node', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('type')->default(1);
        });

        AppFactory::setResponseFactory(new HttpFactory());
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();

        parent::tearDown();
    }

    public function testAcceptsTokenOnlyForExistingEnabledNode(): void
    {
        $token = 'xn_' . str_repeat('a', 64);
        $this->seedToken($token, 1);
        Capsule::table('node')->insert(['id' => 1, 'type' => 1]);
        $handler = $this->handler();

        $response = (new NodeApiToken(static fn (): bool => true))
            ->process($this->request($token), $handler);

        $this->assertSame(204, $response->getStatusCode());
        $this->assertSame(1, $handler->request?->getAttribute('xnode_node_id'));
        $this->assertGreaterThan(0, (int) Capsule::table('node_tokens')->value('last_used_at'));
    }

    public function testRejectsDisabledNodeWithoutTouchingCredential(): void
    {
        $token = 'xn_' . str_repeat('b', 64);
        $this->seedToken($token, 2);
        Capsule::table('node')->insert(['id' => 2, 'type' => 0]);

        $response = (new NodeApiToken(static fn (): bool => true))
            ->process($this->request($token), $this->handler());
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('NODE_DISABLED', $payload['code']);
        $this->assertNull(Capsule::table('node_tokens')->value('last_used_at'));
    }

    public function testRejectsTokenWhoseNodeWasDeleted(): void
    {
        $token = 'xn_' . str_repeat('c', 64);
        $this->seedToken($token, 3);

        $response = (new NodeApiToken(static fn (): bool => true))
            ->process($this->request($token), $this->handler());
        $payload = json_decode((string) $response->getBody(), true);

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('AUTH_INVALID_TOKEN', $payload['code']);
    }

    private function request(string $token): ServerRequestInterface
    {
        return (new HttpFactory())
            ->createServerRequest('GET', '/node/api/v1/config')
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

    private function seedToken(string $token, int $nodeId): void
    {
        Capsule::table('node_tokens')->insert([
            'node_id' => $nodeId,
            'token_hash' => (new NodeEnrollmentService())->hashToken($token),
            'token_type' => 'node',
            'name' => 'xnode-agent',
            'created_at' => 100,
        ]);
    }
}
