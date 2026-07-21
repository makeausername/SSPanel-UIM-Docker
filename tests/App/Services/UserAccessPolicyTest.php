<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PHPUnit\Framework\TestCase;

final class UserAccessPolicyTest extends TestCase
{
    public function testPublicRegistrationGetsThreeDayDeadlineAndNoNodeAccess(): void
    {
        $user = $this->eligibleUser();

        UserAccessPolicy::applyRegistrationPolicy($user, false, 1_700_000_000);

        $this->assertSame(
            1_700_000_000 + UserAccessPolicy::PURCHASE_GRACE_PERIOD_SECONDS,
            strtotime((string) $user->unpaid_delete_at)
        );
        $this->assertTrue(UserAccessPolicy::isAwaitingPlanPurchase($user));
        $this->assertFalse(UserAccessPolicy::hasActivePlan($user));
        $this->assertFalse(UserAccessPolicy::canUseNodes($user));
    }

    public function testAdminCreatedUserIsExemptAndCanUseGrantedEntitlements(): void
    {
        $user = $this->eligibleUser();

        UserAccessPolicy::applyRegistrationPolicy($user, true, 1_700_000_000);

        $this->assertNull($user->unpaid_delete_at);
        $this->assertFalse(UserAccessPolicy::isAwaitingPlanPurchase($user));
        $this->assertTrue(UserAccessPolicy::hasActivePlan($user));
        $this->assertTrue(UserAccessPolicy::canUseNodes($user));
    }

    public function testPlanPurchaseClearsPendingDeadline(): void
    {
        $user = $this->eligibleUser();
        UserAccessPolicy::applyRegistrationPolicy($user, false);

        UserAccessPolicy::markPlanPurchased($user);

        $this->assertNull($user->unpaid_delete_at);
        $this->assertTrue(UserAccessPolicy::canUseNodes($user));
    }

    private function eligibleUser(): User
    {
        $user = new User();
        $user->forceFill([
            'is_admin' => 0,
            'is_banned' => 0,
            'class' => 1,
            'class_expire' => '2099-01-01 00:00:00',
            'transfer_enable' => 1000,
            'u' => 0,
            'd' => 0,
            'unpaid_delete_at' => null,
        ]);

        return $user;
    }
}
