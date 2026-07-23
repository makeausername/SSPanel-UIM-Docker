<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface;
use function is_array;
use function is_string;
use function strtolower;
use function trim;

final class LoginFormFallback
{
    private const FIELD = 'login_form';
    private const FLASH_KEY = 'auth_login_error';

    public static function isNative(ServerRequestInterface $request): bool
    {
        $body = $request->getParsedBody();

        return strtolower(trim($request->getHeaderLine('HX-Request'))) !== 'true'
            && is_array($body)
            && ($body[self::FIELD] ?? null) === '1';
    }

    public static function storeError(string $message): void
    {
        Locale::startSessionIfNeeded();
        $_SESSION[self::FLASH_KEY] = $message;
    }

    public static function pullError(): ?string
    {
        Locale::startSessionIfNeeded();
        $message = $_SESSION[self::FLASH_KEY] ?? null;
        unset($_SESSION[self::FLASH_KEY]);

        return is_string($message) ? $message : null;
    }
}
