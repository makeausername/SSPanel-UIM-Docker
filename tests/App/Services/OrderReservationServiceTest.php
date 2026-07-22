<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class OrderReservationServiceTest extends TestCase
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

        Capsule::schema()->create('product', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('stock');
            $table->integer('sale_count');
        });
        Capsule::schema()->create('user_coupon', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code');
            $table->integer('use_count');
        });
        Capsule::schema()->create('order', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('product_id');
            $table->string('coupon');
        });
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();
        parent::tearDown();
    }

    public function testReleaseRestoresFiniteStockSaleCountAndCouponUsage(): void
    {
        Capsule::table('product')->insert(['id' => 10, 'stock' => 0, 'sale_count' => 1]);
        Capsule::table('user_coupon')->insert(['code' => 'TEST', 'use_count' => 1]);
        Capsule::table('order')->insert(['id' => 20, 'product_id' => 10, 'coupon' => 'TEST']);

        OrderReservationService::release((new Order())->find(20));

        $this->assertSame(1, (int) Capsule::table('product')->find(10)->stock);
        $this->assertSame(0, (int) Capsule::table('product')->find(10)->sale_count);
        $this->assertSame(0, (int) Capsule::table('user_coupon')->where('code', 'TEST')->first()->use_count);
    }

    public function testReleaseKeepsUnlimitedStockUnlimited(): void
    {
        Capsule::table('product')->insert(['id' => 11, 'stock' => -1, 'sale_count' => 1]);
        Capsule::table('order')->insert(['id' => 21, 'product_id' => 11, 'coupon' => '']);

        OrderReservationService::release((new Order())->find(21));

        $this->assertSame(-1, (int) Capsule::table('product')->find(11)->stock);
        $this->assertSame(0, (int) Capsule::table('product')->find(11)->sale_count);
    }
}
