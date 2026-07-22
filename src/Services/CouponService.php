<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use function array_map;
use function bccomp;
use function bcdiv;
use function bcmul;
use function bcsub;
use function explode;
use function in_array;
use function is_numeric;
use function json_decode;
use function property_exists;
use function time;
use function trim;

final class CouponService
{
    /**
     * @return array{valid: bool, message_key: string, discount?: string, total?: string}
     */
    public static function evaluate(UserCoupon $coupon, Product $product, User $user): array
    {
        if ((int) $coupon->expire_time !== 0 && (int) $coupon->expire_time < time()) {
            return self::invalid('response.coupon.not_found_or_expired');
        }

        $limit = json_decode((string) $coupon->limit);
        if (! is_object($limit)) {
            return self::invalid('response.coupon.config_invalid');
        }

        if ((int) ($limit->disabled ?? 0) === 1) {
            return self::invalid('response.coupon.disabled');
        }

        $productIds = trim((string) ($limit->product_id ?? ''));
        if ($productIds !== '') {
            $allowedProductIds = array_map('trim', explode(',', $productIds));
            if (! in_array((string) $product->id, $allowedProductIds, true)) {
                return self::invalid('response.coupon.not_applicable');
            }
        }

        $activeOrderQuery = (new Order())->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled');

        if ((int) ($limit->new_user ?? 0) === 1 && (clone $activeOrderQuery)->exists()) {
            return self::invalid('response.coupon.new_users_only');
        }

        $useLimit = (int) ($limit->use_time ?? -1);
        if ($useLimit > 0) {
            $userUseCount = (clone $activeOrderQuery)->where('coupon', $coupon->code)->count();
            if ($userUseCount >= $useLimit) {
                return self::invalid('response.coupon.usage_limit');
            }
        }

        $totalUseLimit = property_exists($limit, 'total_use_time')
            ? (int) $limit->total_use_time
            : -1;
        if ($totalUseLimit > 0 && (int) $coupon->use_count >= $totalUseLimit) {
            return self::invalid('response.coupon.usage_limit');
        }

        $content = json_decode((string) $coupon->content);
        $type = (string) ($content->type ?? '');
        $value = $content->value ?? null;
        if (! in_array($type, ['percentage', 'fixed'], true) || ! is_numeric($value)) {
            return self::invalid('response.coupon.config_invalid');
        }

        $price = InvoiceAccountingService::money($product->price);
        $value = InvoiceAccountingService::money($value);
        if (bccomp($value, '0.00', 2) <= 0) {
            return self::invalid('response.coupon.config_invalid');
        }

        if ($type === 'percentage') {
            if (bccomp($value, '100.00', 2) > 0) {
                return self::invalid('response.coupon.percentage_too_high');
            }

            $discount = bcdiv(bcmul($price, $value, 4), '100', 2);
        } else {
            if (bccomp($value, $price, 2) > 0) {
                return self::invalid('response.coupon.amount_too_high');
            }

            $discount = $value;
        }

        return [
            'valid' => true,
            'message_key' => 'response.coupon.available',
            'discount' => $discount,
            'total' => bcsub($price, $discount, 2),
        ];
    }

    /**
     * @return array{valid: false, message_key: string}
     */
    private static function invalid(string $messageKey): array
    {
        return [
            'valid' => false,
            'message_key' => $messageKey,
        ];
    }
}
