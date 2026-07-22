<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use function json_encode;

final class CouponServiceTest extends TestCase
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
        });
        Capsule::schema()->create('product', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->decimal('price', 12, 2);
        });
        Capsule::schema()->create('user_coupon', static function (Blueprint $table): void {
            $table->increments('id');
            $table->string('code');
            $table->text('content');
            $table->text('limit');
            $table->integer('use_count')->default(0);
            $table->integer('expire_time')->default(0);
        });
        Capsule::schema()->create('order', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('user_id');
            $table->string('coupon')->default('');
            $table->string('status');
        });

        Capsule::table('user')->insert(['id' => 1]);
        Capsule::table('product')->insert(['id' => 10, 'price' => '300.00']);
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();
        parent::tearDown();
    }

    public function testFullPercentageDiscountProducesCanonicalZeroTotal(): void
    {
        $result = CouponService::evaluate(
            $this->coupon('percentage', '100.00'),
            (new Product())->find(10),
            (new User())->find(1)
        );

        $this->assertTrue($result['valid']);
        $this->assertSame('300.00', $result['discount']);
        $this->assertSame('0.00', $result['total']);
    }

    public function testNewUserCouponIgnoresCancelledOrdersButRejectsActiveOrders(): void
    {
        Capsule::table('order')->insert([
            'user_id' => 1,
            'coupon' => '',
            'status' => 'cancelled',
        ]);

        $coupon = $this->coupon('fixed', '10.00', ['new_user' => 1]);
        $this->assertTrue(CouponService::evaluate(
            $coupon,
            (new Product())->find(10),
            (new User())->find(1)
        )['valid']);

        Capsule::table('order')->insert([
            'user_id' => 1,
            'coupon' => '',
            'status' => 'pending_payment',
        ]);

        $this->assertFalse(CouponService::evaluate(
            $coupon,
            (new Product())->find(10),
            (new User())->find(1)
        )['valid']);
    }

    public function testFixedDiscountCannotExceedProductPrice(): void
    {
        $result = CouponService::evaluate(
            $this->coupon('fixed', '301.00'),
            (new Product())->find(10),
            (new User())->find(1)
        );

        $this->assertFalse($result['valid']);
    }

    private function coupon(string $type, string $value, array $limitOverrides = []): UserCoupon
    {
        $limit = array_merge([
            'disabled' => 0,
            'product_id' => '',
            'use_time' => -1,
            'total_use_time' => -1,
            'new_user' => 0,
        ], $limitOverrides);

        $coupon = new UserCoupon();
        $coupon->code = 'TEST';
        $coupon->content = json_encode(['type' => $type, 'value' => $value], JSON_THROW_ON_ERROR);
        $coupon->limit = json_encode($limit, JSON_THROW_ON_ERROR);
        $coupon->use_count = 0;
        $coupon->expire_time = 0;
        $coupon->save();

        return $coupon;
    }
}
