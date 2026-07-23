<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;

final class PendingOrderService
{
    public const MAX_ACTIVE_ORDERS_PER_USER = 5;
    public const RESERVATION_TTL_SECONDS = 3600;
    public const PARTIAL_PAYMENT_TTL_SECONDS = 86400;

    public static function reusableProductInvoice(int $userId, int $productId): ?Invoice
    {
        $order = (new Order())
            ->where('user_id', $userId)
            ->where('product_id', $productId)
            ->whereIn('status', ['pending_payment', 'pending_activation'])
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        if ($order === null) {
            return null;
        }

        return (new Invoice())
            ->where('order_id', (int) $order->id)
            ->where('user_id', $userId)
            ->whereIn('status', [
                'unpaid',
                'partially_paid',
                'paid_gateway',
                'paid_balance',
                'paid_admin',
            ])
            ->lockForUpdate()
            ->first();
    }

    public static function limitReached(int $userId): bool
    {
        return (new Order())
            ->where('user_id', $userId)
            ->whereIn('status', ['pending_payment', 'pending_activation'])
            ->count() >= self::MAX_ACTIVE_ORDERS_PER_USER;
    }
}
