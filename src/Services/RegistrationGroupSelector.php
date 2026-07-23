<?php

declare(strict_types=1);

namespace App\Services;

use function array_rand;
use function array_values;
use function explode;
use function preg_match;
use function trim;

final class RegistrationGroupSelector
{
    private const MAX_GROUP_ID = 65535;

    public static function select(mixed $configuredGroups): int
    {
        $groups = self::parse($configuredGroups);

        if ($groups === []) {
            return 0;
        }

        return $groups[array_rand($groups)];
    }

    /**
     * @return list<int>
     */
    public static function parse(mixed $configuredGroups): array
    {
        $groups = [];

        foreach (explode(',', (string) $configuredGroups) as $configuredGroup) {
            $configuredGroup = trim($configuredGroup);

            if ($configuredGroup === '' || preg_match('/^\d+$/', $configuredGroup) !== 1) {
                continue;
            }

            $groupId = (int) $configuredGroup;
            if ($groupId > self::MAX_GROUP_ID) {
                continue;
            }

            $groups[$groupId] = $groupId;
        }

        return array_values($groups);
    }
}
