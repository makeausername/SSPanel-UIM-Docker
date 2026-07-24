<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use function in_array;
use function str_starts_with;
use function strtolower;
use function strtoupper;

final class AdminPermissionService
{
    public const ROLES = ['owner', 'administrator', 'support', 'finance', 'node', 'read_only'];

    private const READ_POST_PATHS = [
        '/admin/announcement/ajax',
        '/admin/coupon/ajax',
        '/admin/detect/ajax',
        '/admin/detect/ban/ajax',
        '/admin/detect/log/ajax',
        '/admin/docs/ajax',
        '/admin/gateway/ajax',
        '/admin/giftcard/ajax',
        '/admin/invoice/ajax',
        '/admin/login/ajax',
        '/admin/money/ajax',
        '/admin/node/ajax',
        '/admin/online/ajax',
        '/admin/order/ajax',
        '/admin/order/search',
        '/admin/payback/ajax',
        '/admin/product/ajax',
        '/admin/subscribe/ajax',
        '/admin/syslog/ajax',
        '/admin/ticket/ajax',
        '/admin/user/ajax',
    ];

    public static function role(User $user): string
    {
        $role = strtolower((string) ($user->admin_role ?? ''));

        if ($role === '') {
            return 'administrator';
        }

        return in_array($role, self::ROLES, true) ? $role : 'read_only';
    }

    public static function isOwner(User $user): bool
    {
        return self::role($user) === 'owner';
    }

    public static function ensureActiveOwner(): ?User
    {
        return DB::connection()->transaction(static function (): ?User {
            $owner = (new User())
                ->where('is_admin', 1)
                ->where('is_banned', 0)
                ->where('admin_role', 'owner')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($owner !== null) {
                return $owner;
            }

            $admin = (new User())
                ->where('is_admin', 1)
                ->where('is_banned', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($admin === null) {
                return null;
            }

            $admin->admin_role = 'owner';
            $admin->save();

            return $admin;
        });
    }

    public static function canUpdateUser(User $actor, User $target): bool
    {
        if ((int) $target->is_admin !== 1) {
            return true;
        }

        return self::isOwner($actor) || (int) $actor->id === (int) $target->id;
    }

    public static function allows(User $user, string $method, string $path): bool
    {
        $role = self::role($user);
        $method = strtoupper($method);

        if (in_array($role, ['owner', 'administrator'], true)) {
            return true;
        }

        if ($path === '/admin' || $path === '/admin/') {
            return true;
        }

        if ($role === 'read_only') {
            return self::isReadOperation($method, $path);
        }

        return match ($role) {
            'support' => self::matches($path, ['/admin/ticket'])
                || (self::isReadOperation($method, $path)
                    && self::matches($path, ['/admin/user', '/admin/announcement', '/admin/docs'])),
            'finance' => self::matches($path, [
                '/admin/product',
                '/admin/order',
                '/admin/invoice',
                '/admin/gateway',
                '/admin/money',
                '/admin/payback',
                '/admin/coupon',
                '/admin/giftcard',
            ]),
            'node' => self::matches($path, [
                '/admin/node',
                '/admin/detect',
                '/admin/online',
                '/admin/subscribe',
            ]),
            default => false,
        };
    }

    private static function matches(string $path, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    private static function isReadOperation(string $method, string $path): bool
    {
        return in_array($method, ['GET', 'HEAD'], true)
            || ($method === 'POST' && in_array($path, self::READ_POST_PATHS, true));
    }
}
