<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\UserCoupon;

final class OrderReservationService
{
    public static function release(Order $order): void
    {
        if ((int) $order->product_id > 0) {
            $product = (new Product())->where('id', $order->product_id)->lockForUpdate()->first();

            if ($product !== null) {
                if ((int) $product->stock >= 0) {
                    $product->stock = (int) $product->stock + 1;
                }

                if ((int) $product->sale_count > 0) {
                    $product->sale_count = (int) $product->sale_count - 1;
                }

                $product->save();
            }
        }

        if ((string) $order->coupon !== '') {
            $coupon = (new UserCoupon())->where('code', $order->coupon)->lockForUpdate()->first();

            if ($coupon !== null && (int) $coupon->use_count > 0) {
                $coupon->use_count = (int) $coupon->use_count - 1;
                $coupon->save();
            }
        }
    }
}
