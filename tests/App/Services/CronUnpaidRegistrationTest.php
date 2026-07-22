<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class CronUnpaidRegistrationTest extends TestCase
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

    public function testOnlyOverduePublicRegistrationIsDeleted(): void
    {
        $this->seedUser(1, '2000-01-01 00:00:00');
        $this->seedUser(2, null);
        $this->seedUser(3, '2099-01-01 00:00:00');
        $this->seedUser(4, '2000-01-01 00:00:00', 1);
        Capsule::table('link')->insert(['userid' => 1]);

        $this->runCleanup();

        $this->assertNull(Capsule::table('user')->find(1));
        $this->assertNotNull(Capsule::table('user')->find(2));
        $this->assertNotNull(Capsule::table('user')->find(3));
        $this->assertNotNull(Capsule::table('user')->find(4));
        $this->assertSame(0, Capsule::table('link')->where('userid', 1)->count());
    }

    public function testPaidOrActivatedPlanProtectsAccountAtDeletionBoundary(): void
    {
        $this->seedUser(5, '2000-01-01 00:00:00');
        $this->seedUser(6, '2000-01-01 00:00:00');
        Capsule::table('order')->insert([
            ['id' => 50, 'user_id' => 5, 'product_type' => 'tabp', 'status' => 'activated'],
            ['id' => 60, 'user_id' => 6, 'product_type' => 'tabp', 'status' => 'pending_payment'],
        ]);
        Capsule::table('invoice')->insert([
            'id' => 600,
            'user_id' => 6,
            'order_id' => 60,
            'type' => 'product',
            'status' => 'paid_gateway',
        ]);

        $this->runCleanup();

        $this->assertNull(Capsule::table('user')->find(5)->unpaid_delete_at);
        $this->assertNull(Capsule::table('user')->find(6)->unpaid_delete_at);
    }

    private function runCleanup(): void
    {
        ob_start();
        try {
            Cron::deleteUnpaidRegistrations();
        } finally {
            ob_end_clean();
        }
    }

    private function seedUser(int $id, ?string $deleteAt, int $isAdmin = 0): void
    {
        Capsule::table('user')->insert([
            'id' => $id,
            'is_admin' => $isAdmin,
            'unpaid_delete_at' => $deleteAt,
        ]);
    }

    private function createSchema(): void
    {
        Capsule::schema()->create('user', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('is_admin')->default(0);
            $table->string('unpaid_delete_at')->nullable();
        });
        Capsule::schema()->create('order', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->string('product_type');
            $table->string('status');
        });
        Capsule::schema()->create('invoice', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->integer('order_id');
            $table->string('type');
            $table->string('status');
        });

        foreach ([
            'detect_ban_log' => 'user_id',
            'detect_log' => 'user_id',
            'user_invite_code' => 'user_id',
            'online_log' => 'user_id',
            'link' => 'userid',
            'login_ip' => 'userid',
            'subscribe_log' => 'user_id',
            'mfa_devices' => 'userid',
            'client_sessions' => 'user_id',
        ] as $tableName => $userColumn) {
            Capsule::schema()->create($tableName, static function (Blueprint $table) use ($userColumn): void {
                $table->increments('id');
                $table->integer($userColumn);
            });
        }
    }
}
