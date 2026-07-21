<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Gateway\Base;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class GatewayPaymentTest extends TestCase
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

    public function testGatewayCallbackIsIdempotentAndCreditsOnlyOverpayment(): void
    {
        $this->seedUser(1, '1.50');
        $this->seedInvoice(100, 1, '10.00');
        $this->seedPaylist('trade-1', 1, 100, '12.00');
        $gateway = self::gateway();

        $gateway->postPayment('trade-1');
        $gateway->postPayment('trade-1');

        $this->assertSame('paid_gateway', Capsule::table('invoice')->find(100)->status);
        $this->assertSame('3.5', self::decimal(Capsule::table('user')->find(1)->money));
        $this->assertSame(1, Capsule::table('user_money_log')->count());
    }

    public function testGatewayPartialPaymentPreservesCents(): void
    {
        $this->seedUser(1, '0.00');
        $this->seedInvoice(100, 1, '9.99');
        $this->seedPaylist('trade-2', 1, 100, '9.01');

        self::gateway()->postPayment('trade-2');

        $invoice = Capsule::table('invoice')->find(100);
        $this->assertSame('partially_paid', $invoice->status);
        $this->assertSame('0.98', self::decimal($invoice->price));
        $this->assertSame('9.99', self::decimal($invoice->original_price));
        $this->assertSame('9.01', self::decimal($invoice->paid_amount));
    }

    public function testGatewayRejectsProviderAmountMismatch(): void
    {
        $this->seedUser(1, '0.00');
        $this->seedInvoice(100, 1, '10.00');
        $this->seedPaylist('trade-provider', 1, 100, '10.00', '1000', 'USD');

        $this->expectException(RuntimeException::class);
        self::gateway()->postPayment('trade-provider', '999', 'USD', 'provider-1');
    }

    public function testRefundUsesAccumulatedPaymentsAfterPartialPayment(): void
    {
        $this->seedUser(1, '0.00');
        $this->seedInvoice(100, 1, '10.00');
        $this->seedPaylist('trade-part-1', 1, 100, '4.00');
        self::gateway()->postPayment('trade-part-1');
        $this->seedPaylist('trade-part-2', 1, 100, '6.00');
        self::gateway()->postPayment('trade-part-2');

        (new InvoiceRefundService())->refund(100);

        $invoice = Capsule::table('invoice')->find(100);
        $this->assertSame('refunded_balance', $invoice->status);
        $this->assertSame('10', self::decimal($invoice->refunded_amount));
        $this->assertSame('10', self::decimal(Capsule::table('user')->find(1)->money));
    }

    public function testGatewayRejectsMismatchedInvoiceOwnership(): void
    {
        $this->seedUser(1, '0.00');
        $this->seedInvoice(100, 2, '5.00');
        $this->seedPaylist('trade-3', 1, 100, '5.00');

        $this->expectException(RuntimeException::class);
        self::gateway()->postPayment('trade-3');
    }

    private static function gateway(): Base
    {
        return new class() extends Base {
            public static function _name(): string
            {
                return 'test';
            }

            public static function _readableName(): string
            {
                return 'Test';
            }

            public static function _enable(): bool
            {
                return true;
            }

            public static function getPurchaseHTML(): string
            {
                return '';
            }

            public function purchase(ServerRequest $request, Response $response, array $args): ResponseInterface
            {
                return $response;
            }

            public function notify(ServerRequest $request, Response $response, array $args): ResponseInterface
            {
                return $response;
            }
        };
    }

    private function createSchema(): void
    {
        Capsule::schema()->create('user', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->decimal('money', 12, 2);
            $table->integer('ref_by')->default(0);
        });
        Capsule::schema()->create('invoice', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->decimal('price', 12, 2);
            $table->decimal('original_price', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->string('status');
            $table->text('content');
            $table->integer('update_time')->default(0);
            $table->integer('pay_time')->default(0);
        });
        Capsule::schema()->create('paylist', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('userid');
            $table->decimal('total', 12, 2);
            $table->integer('status')->default(0);
            $table->integer('invoice_id');
            $table->string('tradeno')->unique();
            $table->string('gateway');
            $table->decimal('expected_provider_amount', 20, 8)->nullable();
            $table->string('expected_provider_currency', 16)->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->integer('datetime')->default(0);
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

    private function seedUser(int $id, string $money): void
    {
        Capsule::table('user')->insert(['id' => $id, 'money' => $money, 'ref_by' => 0]);
    }

    private function seedInvoice(int $id, int $userId, string $price): void
    {
        Capsule::table('invoice')->insert([
            'id' => $id,
            'user_id' => $userId,
            'price' => $price,
            'status' => 'unpaid',
            'content' => '[]',
        ]);
    }

    private function seedPaylist(
        string $tradeNo,
        int $userId,
        int $invoiceId,
        string $total,
        ?string $providerAmount = null,
        ?string $providerCurrency = null
    ): void
    {
        Capsule::table('paylist')->insert([
            'userid' => $userId,
            'total' => $total,
            'status' => 0,
            'invoice_id' => $invoiceId,
            'tradeno' => $tradeNo,
            'gateway' => 'Test',
            'expected_provider_amount' => $providerAmount,
            'expected_provider_currency' => $providerCurrency,
        ]);
    }

    private static function decimal(mixed $value): string
    {
        return (string) (float) $value;
    }
}
