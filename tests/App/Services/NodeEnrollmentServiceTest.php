<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use function hash;
use function strlen;

class NodeEnrollmentServiceTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();

        parent::tearDown();
    }

    /**
     * @covers App\Services\NodeEnrollmentService::hashToken
     */
    public function testHashTokenUsesSha256(): void
    {
        $service = new NodeEnrollmentService();

        $this->assertSame(hash('sha256', 'xn_example'), $service->hashToken('xn_example'));
        $this->assertNotSame('xn_example', $service->hashToken('xn_example'));
    }

    /**
     * @covers App\Services\NodeEnrollmentService::generateNodeToken
     */
    public function testGenerateNodeTokenUsesExpectedPrefix(): void
    {
        $service = new NodeEnrollmentService();
        $token = $service->generateNodeToken();

        $this->assertStringStartsWith('xn_', $token);
        $this->assertGreaterThan(32, strlen($token));
    }

    /**
     * @covers App\Services\NodeEnrollmentService::createEnrollTokenForNode
     */
    public function testCreateEnrollTokenRejectsInvalidNodeId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('node_id must be a positive integer.');

        NodeEnrollmentService::createEnrollTokenForNode(0);
    }

    /**
     * @covers App\Services\NodeEnrollmentService::createProbeToken
     */
    public function testCreateProbeTokenStoresGlobalProbeTokenHash(): void
    {
        $token = NodeEnrollmentService::createProbeToken(120);
        $service = new NodeEnrollmentService();
        $record = Capsule::table('node_tokens')->where('token_hash', $service->hashToken($token))->first();

        $this->assertStringStartsWith('xnp_', $token);
        $this->assertNotNull($record);
        $this->assertSame(0, (int) $record->node_id);
        $this->assertSame('probe', $record->token_type);
        $this->assertSame('xnode-probe', $record->name);
        $this->assertSame($service->hashToken($token), $record->token_hash);
        $this->assertNotSame($token, $record->token_hash);
        $this->assertGreaterThan(0, (int) $record->created_at);
        $this->assertGreaterThan((int) $record->created_at, (int) $record->expires_at);
    }
}
