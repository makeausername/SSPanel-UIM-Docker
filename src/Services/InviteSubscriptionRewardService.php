<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InviteSubscriptionReward;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\UserReferral;
use function bccomp;
use function date;
use function in_array;
use function json_decode;
use function max;
use function strtotime;
use function time;

final class InviteSubscriptionRewardService
{
    public const MANAGED_BY = 'eziplc_monthly_shop_v1';

    private const PAID_STATUSES = ['paid_gateway', 'paid_balance', 'paid_admin'];
    private const REWARD_DAYS = [
        'mini' => 30,
        'lite' => 30,
        'basic' => 30,
        'standard' => 60,
        'pro' => 60,
        'ultra' => 60,
    ];

    public static function bindReferral(
        int $invitedUserId,
        int $inviterUserId,
        string $inviteCode
    ): void {
        if ($invitedUserId <= 0 || $inviterUserId <= 0 || $invitedUserId === $inviterUserId) {
            return;
        }

        (new UserReferral())->firstOrCreate(
            ['invited_user_id' => $invitedUserId],
            [
                'inviter_user_id' => $inviterUserId,
                'invite_code' => $inviteCode,
                'create_time' => time(),
            ]
        );
    }

    public static function rewardDays(object $content): int
    {
        if (
            ($content->managed_by ?? '') !== self::MANAGED_BY
            || ($content->billing_cycle ?? '') !== 'annual'
        ) {
            return 0;
        }

        return self::REWARD_DAYS[(string) ($content->sku ?? '')] ?? 0;
    }

    public static function recordForActivatedOrder(
        Order $order,
        User $invitedUser,
        object $content
    ): ?int {
        $rewardDays = self::rewardDays($content);

        if (
            $rewardDays === 0
            || $order->status !== 'activated'
            || $order->product_type !== 'tabp'
            || bccomp((string) $order->price, '0.00', 2) <= 0
        ) {
            return null;
        }

        $referral = (new UserReferral())->where('invited_user_id', $invitedUser->id)->first();
        if ($referral === null || (int) $referral->inviter_user_id === (int) $invitedUser->id) {
            return null;
        }

        if ((new InviteSubscriptionReward())->where('invited_user_id', $invitedUser->id)->exists()) {
            return null;
        }

        $invoice = (new Invoice())->where('order_id', $order->id)->lockForUpdate()->first();
        if ($invoice === null || ! in_array($invoice->status, self::PAID_STATUSES, true)) {
            return null;
        }

        InvoiceAccountingService::initialize($invoice);
        if (bccomp((string) $invoice->paid_amount, '0.00', 2) <= 0) {
            return null;
        }

        $inviter = (new User())->where('id', $referral->inviter_user_id)
            ->where('is_banned', 0)
            ->where('is_shadow_banned', 0)
            ->first();
        if ($inviter === null) {
            return null;
        }

        $reward = new InviteSubscriptionReward();
        $reward->inviter_user_id = $inviter->id;
        $reward->invited_user_id = $invitedUser->id;
        $reward->qualifying_order_id = $order->id;
        $reward->invoice_id = $invoice->id;
        $reward->applied_order_id = 0;
        $reward->product_sku = (string) $content->sku;
        $reward->reward_days = $rewardDays;
        $reward->status = 'pending';
        $reward->create_time = time();
        $reward->apply_time = 0;
        $reward->save();

        return (int) $inviter->id;
    }

    public static function applyPendingForInviter(int $inviterUserId): void
    {
        if ($inviterUserId <= 0) {
            return;
        }

        DB::connection()->transaction(static function () use ($inviterUserId): void {
            $inviter = (new User())->where('id', $inviterUserId)
                ->where('is_banned', 0)
                ->where('is_shadow_banned', 0)
                ->lockForUpdate()
                ->first();
            if ($inviter === null) {
                return;
            }

            $orders = (new Order())->where('user_id', $inviterUserId)
                ->where('product_type', 'tabp')
                ->where('status', 'activated')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $activeOrder = null;
            $activeContent = null;

            foreach ($orders as $order) {
                $content = json_decode((string) $order->product_content);
                if ($content !== null && self::rewardDays($content) > 0) {
                    $activeOrder = $order;
                    $activeContent = $content;
                    break;
                }
            }

            if ($activeOrder === null || $activeContent === null) {
                return;
            }

            $pendingRewards = (new InviteSubscriptionReward())
                ->where('inviter_user_id', $inviterUserId)
                ->where('status', 'pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $applicableRewards = [];
            $rewardDays = 0;

            foreach ($pendingRewards as $reward) {
                $invoice = (new Invoice())->where('id', $reward->invoice_id)->first();
                if ($invoice === null || ! in_array($invoice->status, self::PAID_STATUSES, true)) {
                    $reward->status = 'cancelled';
                    $reward->save();
                    continue;
                }

                $applicableRewards[] = $reward;
                $rewardDays += (int) $reward->reward_days;
            }

            if ($rewardDays === 0) {
                return;
            }

            $before = (string) $inviter->class_expire;
            $beforeTimestamp = max(
                strtotime($before) ?: 0,
                self::effectiveExpiryTimestamp($activeOrder, $activeContent)
            );
            $after = date('Y-m-d H:i:s', $beforeTimestamp + $rewardDays * 86400);
            $inviter->class_expire = $after;
            $inviter->save();

            foreach ($applicableRewards as $reward) {
                $reward->status = 'applied';
                $reward->applied_order_id = $activeOrder->id;
                $reward->expiry_before = $before;
                $reward->expiry_after = $after;
                $reward->apply_time = time();
                $reward->save();
            }
        });
    }

    public static function effectiveExpiryTimestamp(Order $order, object $content): int
    {
        $baseDays = (int) ($content->time ?? 0);
        $rewardDays = (int) (new InviteSubscriptionReward())
            ->where('applied_order_id', $order->id)
            ->where('status', 'applied')
            ->sum('reward_days');

        return (int) $order->update_time + ($baseDays + $rewardDays) * 86400;
    }
}
