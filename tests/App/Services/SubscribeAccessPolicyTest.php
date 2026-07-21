<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PHPUnit\Framework\TestCase;

final class SubscribeAccessPolicyTest extends TestCase
{
    public function testAwaitingPlanPurchaseReceivesNoNodes(): void
    {
        $user = new User();
        $user->forceFill([
            'is_admin' => 0,
            'is_banned' => 0,
            'class' => 65535,
            'class_expire' => '2099-01-01 00:00:00',
            'transfer_enable' => 1_000_000,
            'u' => 0,
            'd' => 0,
            'unpaid_delete_at' => '2099-01-04 00:00:00',
        ]);

        $this->assertCount(0, Subscribe::getUserNodes($user));
    }
}
