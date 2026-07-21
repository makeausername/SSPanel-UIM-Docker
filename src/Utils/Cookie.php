<?php

declare(strict_types=1);

namespace App\Utils;

final class Cookie
{
    public static function set(array $arg, int $time): void
    {
        foreach ($arg as $key => $value) {
            setcookie($key, $value, [
                'expires' => $time,
                'path' => '/',
                'secure' => self::secure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function setWithDomain(array $arg, int $time, string $domain): void
    {
        foreach ($arg as $key => $value) {
            setcookie($key, $value, [
                'expires' => $time,
                'path' => '/',
                'domain' => $domain,
                'secure' => self::secure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function get(string $key): string
    {
        return $_COOKIE[$key] ?? '';
    }

    private static function secure(): bool
    {
        $secure = $_ENV['cookie_secure'] ?? true;

        if (is_bool($secure)) {
            return $secure;
        }

        if (is_string($secure)) {
            return ! in_array(strtolower(trim($secure)), ['', '0', 'false', 'no', 'off'], true);
        }

        return (bool) $secure;
    }
}
