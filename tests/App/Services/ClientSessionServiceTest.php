<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use function hash;
use function time;

final class ClientSessionServiceTest extends TestCase
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

        Capsule::schema()->create('user', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('is_banned')->default(0);
        });
        Capsule::schema()->create('client_sessions', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('token_hash', 64)->unique();
            $table->string('name', 64);
            $table->integer('expires_at');
            $table->integer('last_used_at')->nullable();
            $table->integer('revoked_at')->nullable();
            $table->integer('created_at');
        });
        Capsule::table('user')->insert(['id' => 7, 'is_banned' => 0]);
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();

        parent::tearDown();
    }

    public function testIssueStoresOnlyTokenHashAndAuthenticates(): void
    {
        $service = new ClientSessionService();
        $issued = $service->issue(7, 'test-device');
        $record = Capsule::table('client_sessions')->first();

        $this->assertStringStartsWith('ecs_', $issued['token']);
        $this->assertSame(hash('sha256', $issued['token']), $record->token_hash);
        $this->assertNotSame($issued['token'], $record->token_hash);
        $this->assertGreaterThan(time(), $issued['expires_at']);
        $this->assertSame(7, (int) $service->authenticate($issued['token'])?->id);
    }

    public function testLogoutAndPasswordLifecycleCanRevokeSessions(): void
    {
        $service = new ClientSessionService();
        $first = $service->issue(7)['token'];
        $second = $service->issue(7)['token'];

        $service->revoke($first);
        $this->assertNull($service->authenticate($first));
        $this->assertNotNull($service->authenticate($second));

        $service->revokeAllForUser(7);
        $this->assertNull($service->authenticate($second));
    }

    public function testBannedUserSessionIsRejectedAndRevoked(): void
    {
        $service = new ClientSessionService();
        $token = $service->issue(7)['token'];
        Capsule::table('user')->where('id', 7)->update(['is_banned' => 1]);

        $this->assertNull($service->authenticate($token));
        $this->assertNotNull(Capsule::table('client_sessions')->value('revoked_at'));
    }
}
