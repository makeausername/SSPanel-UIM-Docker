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

final class ProbeApiTokenTest extends TestCase
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

        $factory = new HttpFactory();
        AppFactory::setResponseFactory($factory);
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();

        parent::tearDown();
    }

    public function testAcceptsValidProbeTokenAndUpdatesLastUsedAt(): void
    {
        $token = 'xnp_' . str_repeat('a', 64);
        $this->seedToken($token, 'probe', 0);

        $factory = new HttpFactory();
        $request = $factory
            ->createServerRequest('POST', '/probe/api/v1/report')
            ->withHeader('Authorization', 'Bearer ' . $token);
        $handler = new class() implements RequestHandlerInterface {
            public ?ServerRequestInterface $request = null;

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->request = $request;

                return new Psr7Response(204);
            }
        };

        $response = (new ProbeApiToken(static fn (): bool => true))->process($request, $handler);
        $record = Capsule::table('node_tokens')->where('token_type', 'probe')->first();

        $this->assertSame(204, $response->getStatusCode());
        $this->assertNotNull($handler->request);
        $this->assertSame(1, $handler->request->getAttribute('xnode_probe_token_id'));
        $this->assertGreaterThan(0, (int) $record->last_used_at);
    }

    public function testRejectsNodeToken(): void
    {
        $token = 'xn_' . str_repeat('b', 64);
        $this->seedToken($token, 'node', 1);

        $factory = new HttpFactory();
        $request = $factory
            ->createServerRequest('POST', '/probe/api/v1/report')
            ->withHeader('Authorization', 'Bearer ' . $token);
        $handler = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Psr7Response(204);
            }
        };

        $response = (new ProbeApiToken(static fn (): bool => true))->process($request, $handler);
        $payload = json_decode((string) $response->getBody(), true);
        $record = Capsule::table('node_tokens')->where('token_type', 'node')->first();

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(0, $payload['ret']);
        $this->assertSame('AUTH_INVALID_PROBE_TOKEN', $payload['code']);
        $this->assertNull($record->last_used_at);
    }

    private function seedToken(string $token, string $tokenType, int $nodeId): void
    {
        $service = new NodeEnrollmentService();

        Capsule::table('node_tokens')->insert([
            'node_id' => $nodeId,
            'token_hash' => $service->hashToken($token),
            'token_type' => $tokenType,
            'name' => $tokenType === 'probe' ? 'xnode-probe' : 'xnode-agent',
            'last_used_at' => null,
            'expires_at' => null,
            'used_at' => null,
            'revoked_at' => null,
            'created_at' => 100,
        ]);
    }
}
