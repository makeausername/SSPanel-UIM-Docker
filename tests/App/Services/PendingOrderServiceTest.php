<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;

final class PendingOrderServiceTest extends TestCase
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

        Capsule::schema()->create('order', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->integer('product_id');
            $table->string('status');
        });
        Capsule::schema()->create('invoice', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->integer('order_id');
            $table->string('status');
        });
    }

    protected function tearDown(): void
    {
        Capsule::connection()->disconnect();
        parent::tearDown();
    }

    public function testExistingPendingProductOrderIsReused(): void
    {
        Capsule::table('order')->insert([
            'id' => 10,
            'user_id' => 1,
            'product_id' => 5,
            'status' => 'pending_payment',
        ]);
        Capsule::table('invoice')->insert([
            'id' => 20,
            'user_id' => 1,
            'order_id' => 10,
            'status' => 'unpaid',
        ]);

        $invoice = PendingOrderService::reusableProductInvoice(1, 5);

        self::assertNotNull($invoice);
        self::assertSame(20, (int) $invoice->id);
        self::assertNull(PendingOrderService::reusableProductInvoice(2, 5));
    }

    public function testPendingOrderLimitBlocksTheSixthReservation(): void
    {
        for ($id = 1; $id <= PendingOrderService::MAX_ACTIVE_ORDERS_PER_USER; $id++) {
            Capsule::table('order')->insert([
                'id' => $id,
                'user_id' => 1,
                'product_id' => $id,
                'status' => 'pending_payment',
            ]);
        }

        self::assertTrue(PendingOrderService::limitReached(1));
        self::assertFalse(PendingOrderService::limitReached(2));
    }
}
