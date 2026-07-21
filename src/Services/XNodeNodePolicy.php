<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use function json_encode;

final class XNodeNodePolicy
{
    public const SORT = 15;
    public const TRAFFIC_RATE = 1.0;
    public const RESET_DAY = 1;

    public static function appliesTo(int $sort): bool
    {
        return $sort === self::SORT;
    }

    public static function apply(Node $node): void
    {
        foreach (self::databaseValues() as $field => $value) {
            $node->{$field} = $value;
        }
    }

    public static function databaseValues(): array
    {
        return [
            'traffic_rate' => self::TRAFFIC_RATE,
            'is_dynamic_rate' => 0,
            'dynamic_rate_type' => 0,
            'dynamic_rate_config' => json_encode(
                self::dynamicRateConfig(),
                JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            ),
            'node_class' => 0,
            'node_group' => 0,
            'node_speedlimit' => 0,
            'node_bandwidth_limit' => 0,
            'bandwidthlimit_resetday' => self::RESET_DAY,
        ];
    }

    public static function dynamicRateConfig(): array
    {
        return [
            'max_rate' => self::TRAFFIC_RATE,
            'max_rate_time' => 22,
            'min_rate' => self::TRAFFIC_RATE,
            'min_rate_time' => 3,
        ];
    }
}
