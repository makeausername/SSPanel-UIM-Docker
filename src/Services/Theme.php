<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Utils\Cookie;
use function in_array;
use function time;

final class Theme
{
    public const COOKIE_KEY = 'theme_mode';
    public const MODE_AUTO = 'auto';
    public const MODE_DARK = 'dark';
    public const MODE_LIGHT = 'light';

    public static function current(User $user): string
    {
        $cookieMode = self::normalize(Cookie::get(self::COOKIE_KEY));

        if ($cookieMode !== null) {
            return $cookieMode;
        }

        if ($user->isLogin) {
            return self::fromUserThemeMode((int) $user->is_dark_mode);
        }

        return self::MODE_AUTO;
    }

    public static function toggle(User $user): string
    {
        return self::current($user) === self::MODE_DARK
            ? self::MODE_LIGHT
            : self::MODE_DARK;
    }

    public static function store(string $mode): void
    {
        $mode = self::normalize($mode) ?? self::MODE_LIGHT;
        $_COOKIE[self::COOKIE_KEY] = $mode;

        Cookie::set([self::COOKIE_KEY => $mode], time() + 31536000);
    }

    public static function fromUserThemeMode(int $themeMode): string
    {
        return match ($themeMode) {
            1 => self::MODE_DARK,
            2 => self::MODE_AUTO,
            default => self::MODE_LIGHT,
        };
    }

    private static function normalize(string $mode): ?string
    {
        return in_array($mode, [self::MODE_AUTO, self::MODE_DARK, self::MODE_LIGHT], true)
            ? $mode
            : null;
    }
}
