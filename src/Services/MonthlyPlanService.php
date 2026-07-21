<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Utils\Tools;
use function property_exists;
use function strtotime;
use function time;

final class MonthlyPlanService
{
    public const ALL_NODES_CLASS = 65535;
    public const RESET_DAY = 1;
    public const UNLIMITED_BANDWIDTH_GB = 8589934591;

    public static function applyProductToUser(User $user, object $content): void
    {
        if (! property_exists($content, 'monthly_plan') || $content->monthly_plan !== true) {
            self::clearUser($user);

            return;
        }

        $user->auto_reset_day = (int) ($content->auto_reset_day ?? self::RESET_DAY);
        $user->auto_reset_bandwidth = (float) ($content->auto_reset_bandwidth ?? $content->bandwidth);
    }

    public static function clearUser(User $user): void
    {
        $user->auto_reset_day = 0;
        $user->auto_reset_bandwidth = 0;
    }

    public static function canBuyCurrentMonthAddon(User $user): bool
    {
        return (int) $user->class > 0
            && strtotime((string) $user->class_expire) > time()
            && (int) $user->auto_reset_day === self::RESET_DAY
            && (float) $user->auto_reset_bandwidth > 0;
    }

    public static function resetUserBandwidth(User $user): void
    {
        $user->u = 0;
        $user->d = 0;
        $user->transfer_today = 0;
        $user->transfer_enable = Tools::gbToB($user->auto_reset_bandwidth);
    }
}
