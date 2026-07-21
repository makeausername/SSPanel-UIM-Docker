<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use function json_encode;

final class FixedNodeTrafficRatePolicy
{
    public static function apply(Node $node): void
    {
        foreach (self::databaseValues((float) $node->traffic_rate) as $field => $value) {
            $node->{$field} = $value;
        }
    }

    public static function databaseValues(float $trafficRate): array
    {
        return [
            'is_dynamic_rate' => 0,
            'dynamic_rate_type' => 0,
            'dynamic_rate_config' => json_encode(
                self::compatibilityConfig($trafficRate),
                JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            ),
        ];
    }

    public static function compatibilityConfig(float $trafficRate): array
    {
        return [
            'max_rate' => $trafficRate,
            'max_rate_time' => 22,
            'min_rate' => $trafficRate,
            'min_rate_time' => 3,
        ];
    }
}
