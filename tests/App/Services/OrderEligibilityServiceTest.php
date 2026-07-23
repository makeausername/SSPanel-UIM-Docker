<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PHPUnit\Framework\TestCase;

final class OrderEligibilityServiceTest extends TestCase
{
    public function testTimeProductsRequireTheCurrentActiveClass(): void
    {
        $user = new User();
        $user->class = 2;
        $user->class_expire = '2099-01-01 00:00:00';

        $this->assertTrue(OrderEligibilityService::canPurchaseTimeProduct($user, (object) ['class' => 2]));
        $this->assertFalse(OrderEligibilityService::canPurchaseTimeProduct($user, (object) ['class' => 3]));

        $user->class = 0;
        $this->assertTrue(OrderEligibilityService::canPurchaseTimeProduct($user, (object) ['class' => 3]));
        $this->assertFalse(OrderEligibilityService::canPurchaseTimeProduct($user, (object) []));
        $this->assertFalse(OrderEligibilityService::canPurchaseTimeProduct($user, (object) ['class' => 'invalid']));

        $user->class = 2;
        $user->class_expire = '2000-01-01 00:00:00';
        $this->assertTrue(OrderEligibilityService::canPurchaseTimeProduct($user, (object) ['class' => 3]));
    }
}
