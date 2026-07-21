<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PHPUnit\Framework\TestCase;
use function date;

class MonthlyPlanServiceTest extends TestCase
{
    public function testMonthlyPlanSetsResetBaselineAndAddonEligibility(): void
    {
        $user = new User();
        $user->class = 1;
        $user->class_expire = date('Y-m-d H:i:s', time() + 86400);
        $content = (object) [
            'monthly_plan' => true,
            'auto_reset_day' => 1,
            'auto_reset_bandwidth' => 300,
            'bandwidth' => 300,
        ];

        MonthlyPlanService::applyProductToUser($user, $content);

        $this->assertSame(1, $user->auto_reset_day);
        $this->assertSame(300.0, $user->auto_reset_bandwidth);
        $this->assertTrue(MonthlyPlanService::canBuyCurrentMonthAddon($user));
    }

    public function testMonthlyResetDiscardsAddonsAndRestoresBaseQuota(): void
    {
        $user = new User();
        $user->u = 123;
        $user->d = 456;
        $user->transfer_today = 789;
        $user->transfer_enable = 999999999999;
        $user->auto_reset_bandwidth = 100;

        MonthlyPlanService::resetUserBandwidth($user);

        $this->assertSame(0, $user->u);
        $this->assertSame(0, $user->d);
        $this->assertSame(0, $user->transfer_today);
        $this->assertSame(107374182400, $user->transfer_enable);
    }

    public function testExpiredOrNonMonthlyUsersCannotBuyCurrentMonthAddon(): void
    {
        $user = new User();
        $user->class = 1;
        $user->class_expire = date('Y-m-d H:i:s', time() - 1);
        $user->auto_reset_day = 1;
        $user->auto_reset_bandwidth = 100;

        $this->assertFalse(MonthlyPlanService::canBuyCurrentMonthAddon($user));

        MonthlyPlanService::clearUser($user);

        $this->assertSame(0, $user->auto_reset_day);
        $this->assertSame(0, $user->auto_reset_bandwidth);
    }
}
