<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ClientApiControllerTest extends TestCase
{
    public function testExpiredClassZeroAccountIsBlockedEvenWithTraffic(): void
    {
        $usage = $this->buildUsage([
            'class' => 0,
            'class_expire' => '2020-01-01 00:00:00',
            'u' => 100,
            'd' => 200,
            'transfer_today' => 50,
            'transfer_enable' => 1000,
        ]);

        $this->assertTrue($usage['isExpired']);
        $this->assertFalse($usage['canConnect']);
        $this->assertSame(50, $usage['todayUsed']);
        $this->assertSame(250, $usage['pastUsed']);
        $this->assertSame(700, $usage['remaining']);
    }

    public function testExpiredPaidClassRemainsBlocked(): void
    {
        $usage = $this->buildUsage([
            'class' => 1,
            'class_expire' => '2020-01-01 00:00:00',
            'u' => 100,
            'd' => 200,
            'transfer_today' => 50,
            'transfer_enable' => 1000,
        ]);

        $this->assertTrue($usage['isExpired']);
        $this->assertFalse($usage['canConnect']);
    }

    public function testAdministratorUsageRemainsUnlimitedWithTrafficBreakdown(): void
    {
        $usage = $this->buildUsage([
            'is_admin' => 1,
            'class' => 0,
            'class_expire' => '1989-06-04 00:05:00',
            'u' => 400,
            'd' => 600,
            'transfer_today' => 250,
            'transfer_enable' => 0,
        ]);

        $this->assertTrue($usage['isUnlimited']);
        $this->assertTrue($usage['canConnect']);
        $this->assertSame(250, $usage['todayUsed']);
        $this->assertSame(750, $usage['pastUsed']);
        $this->assertSame(0, $usage['total']);
        $this->assertSame(0, $usage['remaining']);
    }

    public function testAwaitingPlanPurchaseCannotConnectDespiteGrantedEntitlements(): void
    {
        $usage = $this->buildUsage([
            'class' => 1,
            'class_expire' => '2099-01-01 00:00:00',
            'transfer_enable' => 1000,
            'unpaid_delete_at' => '2099-01-04 00:00:00',
        ]);

        $this->assertFalse($usage['isExpired']);
        $this->assertFalse($usage['canConnect']);
    }

    public function testTodayUsageCannotExceedCurrentPeriodUsage(): void
    {
        $usage = $this->buildUsage([
            'u' => 10,
            'd' => 20,
            'transfer_today' => 50,
            'transfer_enable' => 1000,
        ]);

        $this->assertSame(30, $usage['todayUsed']);
        $this->assertSame(0, $usage['pastUsed']);
        $this->assertSame(970, $usage['remaining']);
    }

    private function buildUsage(array $attributes): array
    {
        $user = new User();
        $user->forceFill(array_merge([
            'is_admin' => 0,
            'is_banned' => 0,
            'class' => 0,
            'class_expire' => '2099-01-01 00:00:00',
            'unpaid_delete_at' => null,
            'u' => 0,
            'd' => 0,
            'transfer_today' => 0,
            'transfer_enable' => 0,
        ], $attributes));

        $reflection = new ReflectionClass(ClientApiController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildUsagePayload');

        return $method->invoke($controller, $user);
    }
}
