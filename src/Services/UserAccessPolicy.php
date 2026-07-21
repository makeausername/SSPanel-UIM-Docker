<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use function date;
use function strtotime;
use function time;

final class UserAccessPolicy
{
    public const PURCHASE_GRACE_PERIOD_SECONDS = 3 * 86400;

    public static function applyRegistrationPolicy(
        User $user,
        bool $createdByAdmin,
        ?int $registeredAt = null
    ): void {
        $user->unpaid_delete_at = $createdByAdmin
            ? null
            : date(
                'Y-m-d H:i:s',
                ($registeredAt ?? time()) + self::PURCHASE_GRACE_PERIOD_SECONDS
            );
    }

    public static function markPlanPurchased(User $user): void
    {
        $user->unpaid_delete_at = null;
    }

    public static function isAwaitingPlanPurchase(User $user): bool
    {
        $deleteAt = $user->getAttribute('unpaid_delete_at');

        return $deleteAt !== null && $deleteAt !== '';
    }

    public static function hasActivePlan(User $user): bool
    {
        if ((int) $user->is_banned !== 0) {
            return false;
        }

        if ((int) $user->is_admin === 1) {
            return true;
        }

        return ! self::isAwaitingPlanPurchase($user)
            && (int) $user->class > 0
            && strtotime((string) $user->class_expire) > time();
    }

    public static function canUseNodes(User $user): bool
    {
        return self::hasActivePlan($user)
            && (
                (int) $user->is_admin === 1
                || (int) $user->transfer_enable > (int) $user->u + (int) $user->d
            );
    }
}
