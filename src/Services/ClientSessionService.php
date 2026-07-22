<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClientSession;
use App\Models\User;
use function bin2hex;
use function hash;
use function random_bytes;
use function substr;
use function time;
use function trim;

final class ClientSessionService
{
    private const TOKEN_PREFIX = 'ecs_';
    private const DEFAULT_TTL = 2592000;

    /** @return array{token: string, expires_at: int} */
    public function issue(int $userId, string $name = 'windows-client'): array
    {
        $token = self::TOKEN_PREFIX . bin2hex(random_bytes(32));
        $now = time();
        $expiresAt = $now + self::DEFAULT_TTL;

        $name = trim($name) === '' ? 'windows-client' : trim($name);

        (new ClientSession())->insert([
            'user_id' => $userId,
            'token_hash' => $this->hashToken($token),
            'name' => substr($name, 0, 64),
            'expires_at' => $expiresAt,
            'last_used_at' => null,
            'revoked_at' => null,
            'created_at' => $now,
        ]);

        return ['token' => $token, 'expires_at' => $expiresAt];
    }

    public function authenticate(string $token): ?User
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $session = (new ClientSession())
            ->where('token_hash', $this->hashToken($token))
            ->whereNull('revoked_at')
            ->where('expires_at', '>', time())
            ->first();

        if ($session === null) {
            return null;
        }

        $user = (new User())->find((int) $session->user_id);
        if ($user === null || (int) $user->is_banned === 1) {
            $session->revoked_at = time();
            $session->save();

            return null;
        }

        $session->last_used_at = time();
        $session->save();

        return $user;
    }

    public function revoke(string $token): void
    {
        $token = trim($token);
        if ($token === '') {
            return;
        }

        (new ClientSession())
            ->where('token_hash', $this->hashToken($token))
            ->whereNull('revoked_at')
            ->update(['revoked_at' => time()]);
    }

    public function revokeAllForUser(int $userId): void
    {
        (new ClientSession())
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => time()]);
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
