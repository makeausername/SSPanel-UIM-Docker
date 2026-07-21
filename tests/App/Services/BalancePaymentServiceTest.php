<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class BalancePaymentServiceTest extends TestCase
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

    public function testPaidInvoiceCannotDeductBalanceTwice(): void
    {
        $this->seedUser('10.00');
        $this->seedInvoice('5.00');
        $service = new BalancePaymentService();

        $this->assertSame('paid', $service->pay(1, 100)['status']);
        $this->assertSame('error', $service->pay(1, 100)['status']);

        $this->assertSame('5', self::decimal(Capsule::table('user')->find(1)->money));
        $this->assertSame('paid_balance', Capsule::table('invoice')->find(100)->status);
        $this->assertSame(1, Capsule::table('user_money_log')->count());
    }

    public function testPartialPaymentUsesDecimalArithmetic(): void
    {
        $this->seedUser('0.99');
        $this->seedInvoice('1.50');

        $result = (new BalancePaymentService())->pay(1, 100);

        $this->assertSame('partial', $result['status']);
        $this->assertSame('0', self::decimal(Capsule::table('user')->find(1)->money));
        $this->assertSame('0.51', self::decimal(Capsule::table('invoice')->find(100)->price));
        $this->assertSame('partially_paid', Capsule::table('invoice')->find(100)->status);
    }

    private function createSchema(): void
    {
        Capsule::schema()->create('user', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->decimal('money', 12, 2);
            $table->boolean('is_shadow_banned')->default(false);
        });
        Capsule::schema()->create('invoice', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->string('type');
            $table->decimal('price', 12, 2);
            $table->decimal('original_price', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->string('status');
            $table->text('content');
            $table->integer('update_time')->default(0);
            $table->integer('pay_time')->default(0);
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

    private function seedUser(string $money): void
    {
        Capsule::table('user')->insert([
            'id' => 1,
            'money' => $money,
            'is_shadow_banned' => false,
        ]);
    }

    private function seedInvoice(string $price): void
    {
        Capsule::table('invoice')->insert([
            'id' => 100,
            'user_id' => 1,
            'type' => 'product',
            'price' => $price,
            'status' => 'unpaid',
            'content' => '[]',
        ]);
    }

    private static function decimal(mixed $value): string
    {
        return (string) (float) $value;
    }
}
