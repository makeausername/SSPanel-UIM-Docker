<?php

declare(strict_types=1);

namespace App\Services;

use function array_key_exists;
use function array_keys;
use function explode;
use function headers_sent;
use function in_array;
use function is_string;
use function ltrim;
use function parse_url;
use function preg_match;
use function rtrim;
use function session_start;
use function session_status;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function trim;
use function usort;
use const PHP_SESSION_NONE;
use const PHP_URL_FRAGMENT;
use const PHP_URL_HOST;
use const PHP_URL_PATH;
use const PHP_URL_QUERY;
use const PHP_URL_SCHEME;

final class Locale
{
    public const DEFAULT_LOCALE = 'zh-CN';
    public const SESSION_KEY = 'frontend_locale';
    public const COOKIE_KEY = 'frontend_locale';

    private const RESOURCE_LOCALES = [
        'zh-CN' => 'zh_CN',
        'en-US' => 'en_US',
    ];

    private static string $current = self::DEFAULT_LOCALE;

    public static function supportedLocales(): array
    {
        return array_keys(self::RESOURCE_LOCALES);
    }

    public static function isSupported(string $locale): bool
    {
        return array_key_exists($locale, self::RESOURCE_LOCALES);
    }

    public static function normalize(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }

        $locale = trim($locale);

        if ($locale === '') {
            return null;
        }

        $locale = str_replace('_', '-', $locale);
        $locale = strtolower($locale);

        return match ($locale) {
            'zh', 'zh-cn', 'zh-hans', 'zh-hans-cn', 'zh-sg' => 'zh-CN',
            'en', 'en-us', 'en-gb', 'en-ca', 'en-au' => 'en-US',
            default => null,
        };
    }

    public static function resourceName(?string $locale = null): string
    {
        $locale = $locale === null ? self::$current : self::normalize($locale);

        if ($locale === null || ! self::isSupported($locale)) {
            return self::RESOURCE_LOCALES[self::DEFAULT_LOCALE];
        }

        return self::RESOURCE_LOCALES[$locale];
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function setCurrent(?string $locale): string
    {
        $locale = self::normalize($locale);

        if ($locale === null || ! self::isSupported($locale)) {
            $locale = self::DEFAULT_LOCALE;
        }

        self::$current = $locale;

        return self::$current;
    }

    public static function startSessionIfNeeded(): void
    {
        if (session_status() === PHP_SESSION_NONE && ! headers_sent()) {
            session_start();
        }
    }

    public static function detect(
        string $path,
        array $session = [],
        array $cookies = [],
        string $acceptLanguage = ''
    ): string {
        if (self::isAdminPath($path)) {
            return self::DEFAULT_LOCALE;
        }

        $sessionLocale = self::readLocaleValue($session[self::SESSION_KEY] ?? null);

        if ($sessionLocale !== null) {
            return $sessionLocale;
        }

        $cookieLocale = self::readLocaleValue($cookies[self::COOKIE_KEY] ?? null);

        if ($cookieLocale !== null) {
            return $cookieLocale;
        }

        return self::fromAcceptLanguage($acceptLanguage) ?? self::DEFAULT_LOCALE;
    }

    public static function fromAcceptLanguage(string $acceptLanguage): ?string
    {
        $candidates = [];

        foreach (explode(',', $acceptLanguage) as $index => $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $pieces = explode(';', $part);
            $tag = trim($pieces[0]);
            $quality = 1.0;

            foreach ($pieces as $piece) {
                $piece = trim($piece);

                if (preg_match('/^q=([0-9.]+)$/', $piece, $matches) === 1) {
                    $quality = (float) $matches[1];
                }
            }

            if ($quality <= 0) {
                continue;
            }

            $candidates[] = [
                'index' => $index,
                'locale' => self::normalize($tag),
                'quality' => $quality,
            ];
        }

        usort($candidates, static function (array $left, array $right): int {
            if ($left['quality'] === $right['quality']) {
                return $left['index'] <=> $right['index'];
            }

            return $left['quality'] < $right['quality'] ? 1 : -1;
        });

        foreach ($candidates as $candidate) {
            if ($candidate['locale'] !== null) {
                return $candidate['locale'];
            }
        }

        return null;
    }

    public static function isAdminPath(string $path): bool
    {
        return $path === '/admin' || str_starts_with($path, '/admin/');
    }

    public static function isFrontendPath(string $path): bool
    {
        if (in_array($path, ['/', '/tos', '/staff', '/404', '/405', '/500', '/locale'], true)) {
            return true;
        }

        return str_starts_with($path, '/auth/') ||
            str_starts_with($path, '/oauth/') ||
            str_starts_with($path, '/password/') ||
            $path === '/user' ||
            str_starts_with($path, '/user/');
    }

    public static function sanitizeRedirect(?string $target, string $currentHost): ?string
    {
        if ($target === null) {
            return null;
        }

        $target = trim($target);

        if ($target === '') {
            return null;
        }

        if (str_starts_with($target, '/')) {
            if (str_starts_with($target, '//') || str_contains($target, '\\')) {
                return null;
            }

            return $target;
        }

        $host = parse_url($target, PHP_URL_HOST);

        if (! is_string($host) || strtolower($host) !== strtolower($currentHost)) {
            return null;
        }

        $scheme = parse_url($target, PHP_URL_SCHEME);

        if (is_string($scheme) && ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        $path = parse_url($target, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            $path = '/';
        }

        $path = '/' . ltrim($path, '/');
        $query = parse_url($target, PHP_URL_QUERY);
        $fragment = parse_url($target, PHP_URL_FRAGMENT);

        if (is_string($query) && $query !== '') {
            $path .= '?' . $query;
        }

        if (is_string($fragment) && $fragment !== '') {
            $path .= '#' . $fragment;
        }

        return rtrim($path, '/') === '' ? '/' : $path;
    }

    private static function readLocaleValue(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $locale = self::normalize($value);

        return $locale !== null && self::isSupported($locale) ? $locale : null;
    }
}
