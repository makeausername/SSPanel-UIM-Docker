<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\Tools;
use Redis;
use RuntimeException;
use function is_string;

final class OneTimeTokenService
{
    public static function consume(Redis $redis, string $key): string|false
    {
        $value = $redis->eval(
            'local value = redis.call("GET", KEYS[1]); if value then redis.call("DEL", KEYS[1]); end; return value',
            [$key],
            1
        );

        return is_string($value) ? $value : false;
    }

    public static function issueEmailCode(Redis $redis, string $email, int $ttl): string
    {
        if ($ttl <= 0) {
            throw new RuntimeException('Email verification code TTL must be positive.');
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $code = Tools::genRandomChar(6);
            if (! is_string($code)) {
                continue;
            }
            $created = $redis->eval(
                'if redis.call("EXISTS", KEYS[1]) == 0 then redis.call("SETEX", KEYS[1], ARGV[1], ARGV[2]); return 1; end; return 0',
                ['email_verify:' . $code, $ttl, $email],
                1
            );

            if ((int) $created === 1) {
                return $code;
            }
        }

        throw new RuntimeException('Unable to allocate a unique email verification code.');
    }
}
