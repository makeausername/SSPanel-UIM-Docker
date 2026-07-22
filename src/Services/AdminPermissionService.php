<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use function in_array;
use function str_ends_with;
use function str_starts_with;
use function strtolower;
use function strtoupper;

final class AdminPermissionService
{
    public const ROLES = ['owner', 'administrator', 'support', 'finance', 'node', 'read_only'];

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
            || ($method === 'POST' && (str_ends_with($path, '/ajax') || str_ends_with($path, '/search')));
    }
}
