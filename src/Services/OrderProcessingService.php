<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\User;
use App\Models\UserMoneyLog;
use App\Utils\Tools;
use DateTime;
use function bcadd;
use function bccomp;
use function date;
use function in_array;
use function is_numeric;
use function is_object;
use function json_decode;
use function strtotime;
use function time;

final class OrderProcessingService
{
    public function processTabp(): void
    {
        $userIds = (new Order())->where('product_type', 'tabp')
            ->whereIn('status', ['activated', 'pending_activation'])
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $rewardInviterId = DB::connection()->transaction(static function () use ($userId): ?int {
                $user = (new User())->where('id', $userId)->lockForUpdate()->first();
                if ($user === null) {
                    return null;
                }

                $activated = (new Order())->where('user_id', $userId)
                    ->where('status', 'activated')
                    ->where('product_type', 'tabp')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($activated !== null) {
                    $content = json_decode((string) $activated->product_content);
                    if (
                        is_object($content)
                        && InviteSubscriptionRewardService::effectiveExpiryTimestamp($activated, $content) < time()
                    ) {
                        $activated->status = 'expired';
                        $activated->update_time = time();
                        $activated->save();
                        $activated = null;
                    }
                }

                if ($activated !== null) {
                    return null;
                }

                $order = (new Order())->where('user_id', $userId)
                    ->where('status', 'pending_activation')
                    ->where('product_type', 'tabp')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();
                if ($order === null) {
                    return null;
                }

                $content = json_decode((string) $order->product_content);
                if (! self::hasNumericFields(
                    $content,
                    ['bandwidth', 'class', 'class_time', 'node_group', 'speed_limit', 'ip_limit']
                ) || (float) $content->bandwidth <= 0 || (int) $content->class_time <= 0) {
                    self::cancelFailedActivation($order, $user);

                    return null;
                }

                $user->u = 0;
                $user->d = 0;
                $user->transfer_today = 0;
                $user->transfer_enable = Tools::gbToB($content->bandwidth);
                $user->class = $content->class;
                $user->class_expire = (new DateTime())
                    ->modify('+' . $content->class_time . ' days')->format('Y-m-d H:i:s');
                $user->node_group = $content->node_group;
                $user->node_speedlimit = $content->speed_limit;
                $user->node_iplimit = $content->ip_limit;
                MonthlyPlanService::applyProductToUser($user, $content);
                UserAccessPolicy::markPlanPurchased($user);
                $user->save();

                $order->status = 'activated';
                $order->update_time = time();
                $order->save();

                return InviteSubscriptionRewardService::recordForActivatedOrder($order, $user, $content);
            });

            InviteSubscriptionRewardService::applyPendingForInviter((int) $userId);

            if ($rewardInviterId !== null && $rewardInviterId !== (int) $userId) {
                InviteSubscriptionRewardService::applyPendingForInviter($rewardInviterId);
            }
        }
    }

    public function processBandwidth(): void
    {
        $this->processFirstPerUser('bandwidth', static function (User $user, object $content): bool {
            if (! isset($content->bandwidth)
                || ! is_numeric($content->bandwidth)
                || (float) $content->bandwidth <= 0) {
                return false;
            }

            $user->transfer_enable += Tools::gbToB($content->bandwidth);

            return true;
        });
    }

    public function processTime(): void
    {
        $this->processFirstPerUser('time', static function (User $user, object $content): bool {
            if (! OrderEligibilityService::canPurchaseTimeProduct($user, $content)
                || ! isset($content->class_time, $content->node_group, $content->speed_limit, $content->ip_limit)
                || ! is_numeric($content->class_time)
                || ! is_numeric($content->node_group)
                || ! is_numeric($content->speed_limit)
                || ! is_numeric($content->ip_limit)
                || (int) $content->class_time <= 0) {
                return false;
            }

            $user->class = $content->class;
            $currentExpiry = strtotime((string) $user->class_expire);
            $baseTimestamp = max(time(), $currentExpiry === false ? 0 : $currentExpiry);
            $user->class_expire = date(
                'Y-m-d H:i:s',
                strtotime('+' . (int) $content->class_time . ' days', $baseTimestamp)
            );
            $user->node_group = $content->node_group;
            $user->node_speedlimit = $content->speed_limit;
            $user->node_iplimit = $content->ip_limit;

            return true;
        });
    }

    public function processTopups(): void
    {
        $ids = (new Order())->where('status', 'pending_activation')
            ->where('product_type', 'topup')->orderBy('id')->pluck('id');

        foreach ($ids as $orderId) {
            DB::connection()->transaction(static function () use ($orderId): void {
                $order = (new Order())->where('id', $orderId)->lockForUpdate()->first();
                if ($order === null || $order->status !== 'pending_activation') {
                    return;
                }

                $user = (new User())->where('id', $order->user_id)->lockForUpdate()->first();
                $content = json_decode((string) $order->product_content);
                if ($user === null) {
                    return;
                }

                if (! self::hasNumericFields($content, ['amount']) || (float) $content->amount <= 0) {
                    self::cancelFailedActivation($order, $user);

                    return;
                }

                $amount = InvoiceAccountingService::money($content->amount);
                $before = InvoiceAccountingService::money($user->money);
                $after = bcadd($before, $amount, 2);
                $user->money = $after;
                $user->save();

                $order->status = 'activated';
                $order->update_time = time();
                $order->save();

                (new UserMoneyLog())->add(
                    (int) $user->id,
                    (float) $before,
                    (float) $after,
                    (float) $amount,
                    "充值订单 #{$order->id}"
                );
            });
        }
    }

    public function processPending(): void
    {
        $ids = (new Order())->where('status', 'pending_payment')->orderBy('id')->pluck('id');

        foreach ($ids as $orderId) {
            DB::connection()->transaction(static function () use ($orderId): void {
                $invoice = (new Invoice())->where('order_id', $orderId)->lockForUpdate()->first();
                if ($invoice === null) {
                    return;
                }

                $order = (new Order())->where('id', $orderId)->lockForUpdate()->first();
                if ($order === null || $order->status !== 'pending_payment') {
                    return;
                }

                if (in_array($invoice->status, ['paid_gateway', 'paid_balance', 'paid_admin'], true)) {
                    $order->status = 'pending_activation';
                    $order->update_time = time();
                    $order->save();

                    return;
                }

                if ($invoice->status === 'partially_paid'
                    && $order->create_time + PendingOrderService::PARTIAL_PAYMENT_TTL_SECONDS < time()
                ) {
                    $user = (new User())->where('id', $order->user_id)->lockForUpdate()->first();
                    if ($user !== null) {
                        self::cancelFailedActivation($order, $user);
                    }

                    return;
                }

                if ($order->create_time + PendingOrderService::RESERVATION_TTL_SECONDS < time()
                    && $invoice->status !== 'partially_paid'
                ) {
                    OrderReservationService::release($order);
                    $order->status = 'cancelled';
                    $order->update_time = time();
                    $order->save();
                    $invoice->status = 'cancelled';
                    $invoice->update_time = time();
                    $invoice->save();
                }
            });
        }
    }

    private function processFirstPerUser(string $type, callable $apply): void
    {
        $userIds = (new Order())->where('status', 'pending_activation')
            ->where('product_type', $type)
            ->distinct()
            ->orderBy('user_id')
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            DB::connection()->transaction(static function () use ($userId, $type, $apply): void {
                $user = (new User())->where('id', $userId)->lockForUpdate()->first();
                $order = (new Order())->where('user_id', $userId)
                    ->where('status', 'pending_activation')
                    ->where('product_type', $type)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($user === null || $order === null) {
                    return;
                }

                $content = json_decode((string) $order->product_content);
                if (! is_object($content) || $apply($user, $content) === false) {
                    self::cancelFailedActivation($order, $user);
                    return;
                }

                $user->save();
                $order->status = 'activated';
                $order->update_time = time();
                $order->save();
            });
        }
    }

    private static function cancelFailedActivation(Order $order, User $user): void
    {
        $invoice = (new Invoice())->where('order_id', $order->id)->lockForUpdate()->first();

        OrderReservationService::release($order);
        $order->status = 'cancelled';
        $order->update_time = time();
        $order->save();

        if ($invoice === null) {
            return;
        }

        $refundable = InvoiceAccountingService::refundable($invoice);
        if (in_array($invoice->status, ['partially_paid', 'paid_gateway', 'paid_balance', 'paid_admin'], true)
            && bccomp($refundable, '0.00', 2) > 0) {
            if (! (new InvoiceRefundService())->refundLocked($invoice, $user)) {
                throw new \RuntimeException('Unable to refund a failed product activation.');
            }

            return;
        }

        if ($invoice->status !== 'refunded_balance') {
            $invoice->status = 'cancelled';
            $invoice->update_time = time();
            $invoice->save();
        }
    }

    private static function hasNumericFields(mixed $content, array $fields): bool
    {
        if (! is_object($content)) {
            return false;
        }

        foreach ($fields as $field) {
            if (! isset($content->{$field}) || ! is_numeric($content->{$field})) {
                return false;
            }
        }

        return true;
    }
}
