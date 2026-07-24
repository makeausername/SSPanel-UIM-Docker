<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\UserPortExhaustedException;
use App\Models\Config;
use App\Models\User;
use Illuminate\Database\QueryException;
use RuntimeException;
use function array_key_first;
use function array_keys;
use function array_fill_keys;
use function array_slice;
use function count;
use function random_int;
use function range;
use function shuffle;
use function str_contains;
use function strtolower;

final class UserPortService
{
    private const MAX_ALLOCATION_ATTEMPTS = 12;

    public static function nextAvailable(): int
    {
        [$minPort, $maxPort] = self::range();
        $poolSize = $maxPort - $minPort + 1;
        $query = (new User())->whereBetween('port', [$minPort, $maxPort]);

        if ((int) $query->distinct()->count('port') >= $poolSize) {
            throw new UserPortExhaustedException('No user ports are available in the configured range.');
        }

        for ($attempt = 0; $attempt < 32; $attempt++) {
            $candidate = random_int($minPort, $maxPort);
            if (! (new User())->where('port', $candidate)->exists()) {
                return $candidate;
            }
        }

        $used = array_fill_keys(
            (new User())
                ->whereBetween('port', [$minPort, $maxPort])
                ->pluck('port')
                ->map(static fn ($port): int => (int) $port)
                ->all(),
            true
        );
        for ($candidate = $minPort; $candidate <= $maxPort; $candidate++) {
            if (! isset($used[$candidate])) {
                return $candidate;
            }
        }

        throw new UserPortExhaustedException('No user ports are available in the configured range.');
    }

    public static function assignAndSave(User $user): bool
    {
        for ($attempt = 0; $attempt < self::MAX_ALLOCATION_ATTEMPTS; $attempt++) {
            $user->port = self::nextAvailable();

            try {
                return $user->save();
            } catch (QueryException $e) {
                if (! self::isPortCollision($e)) {
                    throw $e;
                }
            }
        }

        throw new UserPortExhaustedException(
            'Unable to reserve a unique user port after repeated concurrent allocation conflicts.'
        );
    }

    public static function isAvailableForUser(int $port, int $userId): bool
    {
        [$minPort, $maxPort] = self::range();
        if ($port < $minPort || $port > $maxPort) {
            return false;
        }

        return ! (new User())
            ->where('id', '!=', $userId)
            ->where('port', $port)
            ->exists();
    }

    public static function reassignAll(): int
    {
        return DB::connection()->transaction(static function (): int {
            [$minPort, $maxPort] = self::range();
            $users = (new User())->orderBy('id')->lockForUpdate()->get();
            $userCount = count($users);

            if ($userCount === 0) {
                return 0;
            }

            $poolSize = $maxPort - $minPort + 1;
            if ($userCount > $poolSize) {
                throw new UserPortExhaustedException(
                    'The configured user port range is smaller than the existing user count.'
                );
            }

            $targets = range($minPort, $maxPort);
            shuffle($targets);
            $targets = array_slice($targets, 0, $userCount);

            $usersById = [];
            $current = [];
            $occupied = [];
            $pending = [];
            foreach ($users as $index => $user) {
                $userId = (int) $user->id;
                $port = (int) $user->port;
                if (isset($occupied[$port])) {
                    throw new RuntimeException('Duplicate user ports must be repaired before resetting them.');
                }

                $usersById[$userId] = $user;
                $current[$userId] = $port;
                $occupied[$port] = $userId;
                $pending[$userId] = $targets[$index];
            }

            self::avoidNoOpReset($pending, $current, $minPort, $maxPort);

            while ($pending !== []) {
                $madeProgress = false;
                foreach ($pending as $userId => $targetPort) {
                    $occupant = $occupied[$targetPort] ?? null;
                    if ($occupant !== null && $occupant !== $userId) {
                        continue;
                    }

                    self::moveUserPort($usersById[$userId], $current, $occupied, $targetPort);
                    unset($pending[$userId]);
                    $madeProgress = true;
                }

                if ($madeProgress) {
                    continue;
                }

                if (isset($occupied[0])) {
                    throw new RuntimeException('Port zero is unexpectedly occupied and cannot be used during reset.');
                }

                $cycleUserId = array_key_first($pending);
                self::moveUserPort($usersById[$cycleUserId], $current, $occupied, 0);
            }

            return $userCount;
        });
    }

    /**
     * @return array{0:int,1:int}
     */
    private static function range(): array
    {
        $minPort = (int) Config::obtain('min_port');
        $maxPort = (int) Config::obtain('max_port');

        if ($minPort <= 0 || $minPort >= 65535 || $maxPort <= 0 || $maxPort > 65535 || $minPort > $maxPort) {
            throw new UserPortExhaustedException('The configured user port range is invalid.');
        }

        return [$minPort, $maxPort];
    }

    private static function isPortCollision(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'user_port_unique')
            || str_contains($message, 'user.port');
    }

    /**
     * @param array<int,int> $pending
     * @param array<int,int> $current
     */
    private static function avoidNoOpReset(array &$pending, array $current, int $minPort, int $maxPort): void
    {
        $changed = false;
        foreach ($pending as $userId => $targetPort) {
            if ($current[$userId] !== $targetPort) {
                $changed = true;
                break;
            }
        }

        if ($changed) {
            return;
        }

        $userIds = array_keys($pending);
        if (count($userIds) > 1) {
            $firstTarget = $pending[$userIds[0]];
            for ($index = 0, $lastIndex = count($userIds) - 1; $index < $lastIndex; $index++) {
                $pending[$userIds[$index]] = $pending[$userIds[$index + 1]];
            }
            $pending[$userIds[$lastIndex]] = $firstTarget;

            return;
        }

        $userId = $userIds[0];
        if ($minPort !== $maxPort) {
            $pending[$userId] = $current[$userId] === $minPort ? $minPort + 1 : $minPort;
        }
    }

    /**
     * @param array<int,int> $current
     * @param array<int,int> $occupied
     */
    private static function moveUserPort(
        User $user,
        array &$current,
        array &$occupied,
        int $targetPort
    ): void {
        $userId = (int) $user->id;
        $oldPort = $current[$userId];
        if ($oldPort === $targetPort) {
            return;
        }

        $user->port = $targetPort;
        if (! $user->save()) {
            throw new RuntimeException('Unable to save a reassigned user port.');
        }

        unset($occupied[$oldPort]);
        $occupied[$targetPort] = $userId;
        $current[$userId] = $targetPort;
    }
}
