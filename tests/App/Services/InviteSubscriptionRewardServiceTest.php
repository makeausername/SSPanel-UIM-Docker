<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use function date;
use function json_encode;
use function strtotime;
use function time;

final class InviteSubscriptionRewardServiceTest extends TestCase
{
    private Capsule $db;
    private int $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = time();
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

    public function testFixedSkuMappingRejectsUnmanagedAndAddonProducts(): void
    {
        $this->assertSame(30, InviteSubscriptionRewardService::rewardDays($this->planContent('mini')));
        $this->assertSame(30, InviteSubscriptionRewardService::rewardDays($this->planContent('lite')));
        $this->assertSame(30, InviteSubscriptionRewardService::rewardDays($this->planContent('basic')));
        $this->assertSame(60, InviteSubscriptionRewardService::rewardDays($this->planContent('standard')));
        $this->assertSame(60, InviteSubscriptionRewardService::rewardDays($this->planContent('pro')));
        $this->assertSame(60, InviteSubscriptionRewardService::rewardDays($this->planContent('ultra')));
        $this->assertSame(0, InviteSubscriptionRewardService::rewardDays((object) [
            'managed_by' => InviteSubscriptionRewardService::MANAGED_BY,
            'billing_cycle' => 'annual',
            'sku' => 'addon-100gb',
        ]));
        $this->assertSame(0, InviteSubscriptionRewardService::rewardDays((object) [
            'managed_by' => 'other-shop',
            'billing_cycle' => 'annual',
            'sku' => 'mini',
        ]));
    }

    public function testMultipleInvitesAcrossPaymentMethodsAccumulateWithoutDuplicates(): void
    {
        $this->seedUser(1, date('Y-m-d H:i:s', $this->now + 365 * 86400));
        $this->seedOrder(10, 1, 'mini', 'activated', '300.00', $this->now);

        $this->seedInvitedPurchase(2, 20, 200, 'mini', 'paid_gateway', '300.00');
        $this->seedInvitedPurchase(3, 30, 300, 'standard', 'paid_balance', '900.00');
        $this->seedInvitedPurchase(4, 40, 400, 'basic', 'paid_admin', '600.00');
        $this->seedInvitedPurchase(5, 50, 500, 'ultra', 'paid_gateway', '0.00');

        (new OrderProcessingService())->processTabp();

        $this->assertSame(3, Capsule::table('invite_subscription_reward')->count());
        $this->assertSame(
            120,
            (int) Capsule::table('invite_subscription_reward')->where('status', 'applied')->sum('reward_days')
        );
        $this->assertSame(
            date('Y-m-d H:i:s', $this->now + (365 + 120) * 86400),
            (string) Capsule::table('user')->find(1)->class_expire
        );
        $activeOrder = (new Order())->find(10);
        $this->assertSame(
            $this->now + (365 + 120) * 86400,
            InviteSubscriptionRewardService::effectiveExpiryTimestamp(
                $activeOrder,
                $this->planContent('mini')
            )
        );
        $this->assertSame('activated', (string) Capsule::table('order')->find(50)->status);

        (new OrderProcessingService())->processTabp();

        $this->assertSame(3, Capsule::table('invite_subscription_reward')->count());

        $secondOrder = $this->seedOrder(21, 2, 'standard', 'activated', '900.00', $this->now);
        $this->seedInvoice(201, 2, 21, 'paid_gateway', '900.00');
        $invitedUser = (new User())->find(2);

        $this->assertNull(InviteSubscriptionRewardService::recordForActivatedOrder(
            $secondOrder,
            $invitedUser,
            $this->planContent('standard')
        ));
        $this->assertSame(3, Capsule::table('invite_subscription_reward')->count());
    }

    public function testRewardWaitsUntilInviterActivatesAManagedPlan(): void
    {
        $this->seedUser(6, '2000-01-01 00:00:00');
        $this->seedInvitedPurchase(7, 70, 700, 'mini', 'paid_gateway', '300.00', 6);

        (new OrderProcessingService())->processTabp();

        $reward = Capsule::table('invite_subscription_reward')->first();
        $this->assertSame('pending', $reward->status);
        $this->assertSame('2000-01-01 00:00:00', (string) Capsule::table('user')->find(6)->class_expire);

        $this->seedOrder(60, 6, 'lite', 'pending_activation', '450.00', $this->now);
        $this->seedInvoice(600, 6, 60, 'paid_balance', '450.00');
        $beforeActivation = time();
        (new OrderProcessingService())->processTabp();

        $reward = Capsule::table('invite_subscription_reward')->first();
        $expiry = strtotime((string) Capsule::table('user')->find(6)->class_expire);
        $this->assertSame('applied', $reward->status);
        $this->assertSame(60, (int) $reward->applied_order_id);
        $this->assertGreaterThanOrEqual($beforeActivation + 395 * 86400, $expiry);
        $this->assertLessThanOrEqual(time() + 395 * 86400 + 2, $expiry);
    }

    public function testRewardDoesNotReviveAnExpiredManagedPlan(): void
    {
        $expiredAt = $this->now - 10 * 86400;
        $this->seedUser(8, date('Y-m-d H:i:s', $expiredAt));
        $this->seedOrder(80, 8, 'mini', 'activated', '300.00', $this->now - 375 * 86400);
        $this->seedInvoice(800, 9, 90, 'paid_gateway', '300.00');
        Capsule::table('invite_subscription_reward')->insert([
            'inviter_user_id' => 8,
            'invited_user_id' => 9,
            'qualifying_order_id' => 90,
            'invoice_id' => 800,
            'applied_order_id' => 0,
            'product_sku' => 'mini',
            'reward_days' => 30,
            'status' => 'pending',
            'create_time' => $this->now,
            'apply_time' => 0,
        ]);

        InviteSubscriptionRewardService::applyPendingForInviter(8);

        $this->assertSame('expired', (string) Capsule::table('order')->find(80)->status);
        $this->assertSame('pending', (string) Capsule::table('invite_subscription_reward')->first()->status);
        $this->assertSame(
            date('Y-m-d H:i:s', $expiredAt),
            (string) Capsule::table('user')->find(8)->class_expire
        );
    }

    private function createSchema(): void
    {
        Capsule::schema()->create('user', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->string('user_name');
            $table->boolean('is_banned')->default(false);
            $table->boolean('is_shadow_banned')->default(false);
            $table->integer('u')->default(0);
            $table->integer('d')->default(0);
            $table->integer('transfer_today')->default(0);
            $table->bigInteger('transfer_enable')->default(0);
            $table->integer('class')->default(0);
            $table->string('class_expire');
            $table->integer('node_group')->default(0);
            $table->integer('node_speedlimit')->default(0);
            $table->integer('node_iplimit')->default(0);
            $table->integer('auto_reset_day')->default(0);
            $table->decimal('auto_reset_bandwidth', 20, 2)->default(0);
            $table->string('unpaid_delete_at')->nullable();
        });
        Capsule::schema()->create('order', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->string('product_type');
            $table->text('product_content');
            $table->decimal('price', 12, 2)->default(0);
            $table->string('status');
            $table->integer('update_time')->default(0);
        });
        Capsule::schema()->create('invoice', static function (Blueprint $table): void {
            $table->integer('id')->primary();
            $table->integer('user_id');
            $table->integer('order_id');
            $table->decimal('price', 12, 2)->default(0);
            $table->decimal('original_price', 12, 2)->nullable();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('refunded_amount', 12, 2)->default(0);
            $table->string('status');
            $table->text('content')->default('[]');
        });
        Capsule::schema()->create('user_referral', static function (Blueprint $table): void {
            $table->integer('invited_user_id')->primary();
            $table->integer('inviter_user_id');
            $table->string('invite_code')->default('');
            $table->integer('create_time')->default(0);
        });
        Capsule::schema()->create('invite_subscription_reward', static function (Blueprint $table): void {
            $table->increments('id');
            $table->integer('inviter_user_id');
            $table->integer('invited_user_id')->unique();
            $table->integer('qualifying_order_id')->unique();
            $table->integer('invoice_id')->unique();
            $table->integer('applied_order_id')->default(0);
            $table->string('product_sku', 32);
            $table->integer('reward_days');
            $table->string('status', 16)->default('pending');
            $table->string('expiry_before')->nullable();
            $table->string('expiry_after')->nullable();
            $table->integer('create_time')->default(0);
            $table->integer('apply_time')->default(0);
        });
    }

    private function seedUser(int $id, string $classExpire): void
    {
        Capsule::table('user')->insert([
            'id' => $id,
            'user_name' => 'user-' . $id,
            'class' => $id === 1 ? 65535 : 0,
            'class_expire' => $classExpire,
        ]);
    }

    private function seedInvitedPurchase(
        int $userId,
        int $orderId,
        int $invoiceId,
        string $sku,
        string $invoiceStatus,
        string $price,
        int $inviterId = 1
    ): void {
        $this->seedUser($userId, '2000-01-01 00:00:00');
        Capsule::table('user_referral')->insert([
            'invited_user_id' => $userId,
            'inviter_user_id' => $inviterId,
            'invite_code' => 'invite-' . $inviterId,
            'create_time' => $this->now,
        ]);
        $this->seedOrder($orderId, $userId, $sku, 'pending_activation', $price, $this->now);
        $this->seedInvoice($invoiceId, $userId, $orderId, $invoiceStatus, $price);
    }

    private function seedOrder(
        int $id,
        int $userId,
        string $sku,
        string $status,
        string $price,
        int $updateTime
    ): Order {
        Capsule::table('order')->insert([
            'id' => $id,
            'user_id' => $userId,
            'product_type' => 'tabp',
            'product_content' => json_encode($this->planContent($sku), JSON_THROW_ON_ERROR),
            'price' => $price,
            'status' => $status,
            'update_time' => $updateTime,
        ]);

        return (new Order())->find($id);
    }

    private function seedInvoice(
        int $id,
        int $userId,
        int $orderId,
        string $status,
        string $price
    ): void {
        Capsule::table('invoice')->insert([
            'id' => $id,
            'user_id' => $userId,
            'order_id' => $orderId,
            'price' => $price,
            'original_price' => $price,
            'paid_amount' => $price,
            'refunded_amount' => '0.00',
            'status' => $status,
            'content' => '[]',
        ]);
    }

    private function planContent(string $sku): object
    {
        return (object) [
            'managed_by' => InviteSubscriptionRewardService::MANAGED_BY,
            'billing_cycle' => 'annual',
            'sku' => $sku,
            'time' => 365,
            'class' => 65535,
            'class_time' => 365,
            'bandwidth' => 100,
            'node_group' => 0,
            'speed_limit' => 0,
            'ip_limit' => 0,
            'monthly_plan' => true,
            'auto_reset_day' => 1,
            'auto_reset_bandwidth' => 100,
        ];
    }
}
