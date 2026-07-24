<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AdminPermissionServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $capsule->schema()->create('user', static function ($table): void {
            $table->increments('id');
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_banned')->default(false);
            $table->string('admin_role')->nullable();
        });
    }

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

    public function testEnsureActiveOwnerPromotesTheFirstActiveAdministratorAndIsIdempotent(): void
    {
        User::query()->insert([
            ['id' => 1, 'is_admin' => 1, 'is_banned' => 1, 'admin_role' => 'administrator'],
            ['id' => 2, 'is_admin' => 1, 'is_banned' => 0, 'admin_role' => 'administrator'],
            ['id' => 3, 'is_admin' => 1, 'is_banned' => 0, 'admin_role' => null],
        ]);

        $owner = AdminPermissionService::ensureActiveOwner();
        $sameOwner = AdminPermissionService::ensureActiveOwner();

        $this->assertSame(2, (int) $owner?->id);
        $this->assertSame(2, (int) $sameOwner?->id);
        $this->assertSame(
            1,
            User::query()
                ->where('is_admin', 1)
                ->where('is_banned', 0)
                ->where('admin_role', 'owner')
                ->count()
        );
    }

    public function testEnsureActiveOwnerReturnsNullWhenNoActiveAdministratorExists(): void
    {
        User::query()->insert([
            'id' => 1,
            'is_admin' => 1,
            'is_banned' => 1,
            'admin_role' => 'administrator',
        ]);

        $this->assertNull(AdminPermissionService::ensureActiveOwner());
    }
}
