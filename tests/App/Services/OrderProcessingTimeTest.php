<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use function json_encode;
use function strtotime;
use function time;

final class OrderProcessingTimeTest extends TestCase
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

    public function testExpiredTimePackageStartsFromNow(): void
    {
        $this->seedUser(1, 0, '2000-01-01 00:00:00', '0.00');
        $this->seedOrder(10, 1, 1, 'pending_activation');

        $before = time();
        (new OrderProcessingService())->processTime();

        $user = Capsule::table('user')->find(1);
        $expiry = strtotime((string) $user->class_expire);
        $this->assertSame('activated', Capsule::table('order')->find(10)->status);
        $this->assertSame(1, (int) $user->class);
        $this->assertGreaterThanOrEqual($before + 30 * 86400, $expiry);
        $this->assertLessThanOrEqual(time() + 30 * 86400 + 2, $expiry);
    }

    public function testMismatchedPaidTimePackageIsCancelledAndRefunded(): void
    {
        $this->seedUser(2, 2, '2099-01-01 00:00:00', '5.00');
        $this->seedOrder(20, 2, 1, 'pending_activation');
        Capsule::table('invoice')->insert([
            'id' => 200,
            'user_id' => 2,
            'order_id' => 20,
            'content' => '[]',
            'price' => '10.00',
            'original_price' => '10.00',
            'paid_amount' => '10.00',
            'refunded_amount' => '0.00',
            'status' => 'paid_gateway',
            'update_time' => 0,
        ]);

        (new OrderProcessingService())->processTime();

        $this->assertSame('cancelled', Capsule::table('order')->find(20)->status);
        $this->assertSame('refunded_balance', Capsule::table('invoice')->find(200)->status);
        $this->assertSame(10.0, (float) Capsule::table('invoice')->find(200)->refunded_amount);
        $this->assertSame(15.0, (float) Capsule::table('user')->find(2)->money);
        $this->assertSame(1, Capsule::table('user_money_log')->count());
    }

    public function testMalformedPaidTopupIsCancelledAndRefunded(): void
    {
        $this->seedUser(3, 0, '2000-01-01 00:00:00', '2.00');
        Capsule::table('order')->insert([
            'id' => 30,
            'user_id' => 3,
            'product_id' => 0,
            'product_type' => 'topup',
            'product_content' => json_encode(['amount' => 'invalid'], JSON_THROW_ON_ERROR),
            'coupon' => '',
            'status' => 'pending_activation',
            'update_time' => 0,
        ]);
        Capsule::table('invoice')->insert([
            'id' => 300,
            'user_id' => 3,
            'order_id' => 30,
            'content' => '[]',
            'price' => '8.00',
            'original_price' => '8.00',
            'paid_amount' => '8.00',
            'refunded_amount' => '0.00',
            'status' => 'paid_gateway',
            'update_time' => 0,
        ]);

        (new OrderProcessingService())->processTopups();

        $this->assertSame('cancelled', Capsule::table('order')->find(30)->status);
        $this->assertSame('refunded_balance', Capsule::table('invoice')->find(300)->status);
        $this->assertSame(10.0, (float) Capsule::table('user')->find(3)->money);
    }

    private function seedUser(int $id, int $class, string $expiry, string $money): void
    {
        Capsule::table('user')->insert([
            'id' => $id,
            'class' => $class,
            'class_expire' => $expiry,
            'node_group' => 0,
            'node_speedlimit' => 0,
            'node_iplimit' => 0,
            'money' => $money,
        ]);
    }

    private function seedOrder(int $id, int $userId, int $targetClass, string $status): void
    {
        Capsule::table('order')->insert([
            'id' => $id,
            'user_id' => $userId,
            'product_id' => 0,
            'product_type' => 'time',
            'product_content' => json_encode([
                'class' => $targetClass,
                'class_time' => 30,
                'node_group' => 0,
                'speed_limit' => 0,
                'ip_limit' => 0,
            ], JSON_THROW_ON_ERROR),
            'coupon' => '',
            'status' => $status,
            'update_time' => 0,
        ]);
    }

    private function createSchema(): void
    {
        Capsule::schema()->create('user', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('class');
            $table->string('class_expire');
            $table->integer('node_group');
            $table->integer('node_speedlimit');
            $table->integer('node_iplimit');
            $table->decimal('money', 12, 2);
        });
        Capsule::schema()->create('order', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->integer('product_id');
            $table->string('product_type');
            $table->text('product_content');
            $table->string('coupon');
            $table->string('status');
            $table->integer('update_time');
        });
        Capsule::schema()->create('invoice', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->integer('order_id');
            $table->text('content');
            $table->decimal('price', 12, 2);
            $table->decimal('original_price', 12, 2);
            $table->decimal('paid_amount', 12, 2);
            $table->decimal('refunded_amount', 12, 2);
            $table->string('status');
            $table->integer('update_time');
        });
        Capsule::schema()->create('user_money_log', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->decimal('before', 12, 2);
            $table->decimal('after', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->string('remark');
            $table->integer('create_time');
        });
    }

}
