<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminPermissionServiceTest extends TestCase
{
    #[DataProvider('permissionCases')]
    public function testRolePermissionMatrix(string $role, string $method, string $path, bool $expected): void
    {
        $user = new User();
        $user->admin_role = $role;

        $this->assertSame($expected, AdminPermissionService::allows($user, $method, $path));
    }

    public static function permissionCases(): array
    {
        return [
            ['owner', 'DELETE', '/admin/user/1', true],
            ['administrator', 'PUT', '/admin/setting/site', true],
            ['support', 'POST', '/admin/ticket/1', true],
            ['support', 'POST', '/admin/user/ajax', true],
            ['support', 'PUT', '/admin/user/1', false],
            ['finance', 'POST', '/admin/invoice/1/mark_paid', true],
            ['finance', 'DELETE', '/admin/node/1', false],
            ['node', 'PUT', '/admin/node/1', true],
            ['node', 'POST', '/admin/order/search', false],
            ['read_only', 'GET', '/admin/setting', true],
            ['read_only', 'POST', '/admin/order/ajax', true],
            ['read_only', 'POST', '/admin/future/ajax', false],
            ['read_only', 'POST', '/admin/order/export', false],
            ['read_only', 'POST', '/admin/setting/site', false],
        ];
    }

    public function testLegacyEmptyRoleFallsBackToAdministratorButUnknownRoleIsReadOnly(): void
    {
        $user = new User();
        $user->admin_role = null;

        $this->assertSame('administrator', AdminPermissionService::role($user));
        $this->assertTrue(AdminPermissionService::allows($user, 'DELETE', '/admin/user/1'));

        $user->admin_role = 'corrupted-role';
        $this->assertSame('read_only', AdminPermissionService::role($user));
        $this->assertFalse(AdminPermissionService::allows($user, 'DELETE', '/admin/user/1'));
    }

    public function testOnlyOwnerCanUpdateAnotherAdminAccount(): void
    {
        $owner = new User();
        $owner->id = 1;
        $owner->admin_role = 'owner';

        $administrator = new User();
        $administrator->id = 2;
        $administrator->admin_role = 'administrator';

        $otherAdministrator = new User();
        $otherAdministrator->id = 3;
        $otherAdministrator->is_admin = 1;
        $otherAdministrator->admin_role = 'administrator';

        $ordinaryUser = new User();
        $ordinaryUser->id = 4;
        $ordinaryUser->is_admin = 0;

        $this->assertTrue(AdminPermissionService::canUpdateUser($owner, $otherAdministrator));
        $this->assertFalse(AdminPermissionService::canUpdateUser($administrator, $otherAdministrator));
        $this->assertTrue(AdminPermissionService::canUpdateUser($administrator, $administrator));
        $this->assertTrue(AdminPermissionService::canUpdateUser($administrator, $ordinaryUser));
    }
}
