<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use InvalidArgumentException;
use function in_array;
use function is_array;
use function is_numeric;
use function json_decode;
use function json_encode;

final class XNodeNodePolicy
{
    public const SORT = 15;
    public const DEFAULT_TRAFFIC_RATE = 2.0;
    public const RESET_DAY = 1;

    private const TRAFFIC_RATE_OPTIONS = [
        2.0,
        4.0,
        6.0,
        8.0,
        10.0,
    ];

    public static function appliesTo(int $sort): bool
    {
        return $sort === self::SORT;
    }

    public static function apply(Node $node): void
    {
        $trafficRate = self::normalizeTrafficRate($node->traffic_rate);

        if ($trafficRate === null) {
            throw new InvalidArgumentException('XNode traffic rate must be one of 2, 4, 6, 8, or 10');
        }

        foreach (self::databaseValues($trafficRate) as $field => $value) {
            $node->{$field} = $value;
        }

        $node->custom_config = self::withoutLegacyProfitMetadata((string) $node->custom_config);
    }

    public static function trafficRateOptions(): array
    {
        return self::TRAFFIC_RATE_OPTIONS;
    }

    public static function normalizeTrafficRate(mixed $trafficRate): ?float
    {
        if (! is_numeric($trafficRate)) {
            return null;
        }

        $trafficRate = (float) $trafficRate;

        if (! in_array($trafficRate, self::TRAFFIC_RATE_OPTIONS, true)) {
            return null;
        }

        return $trafficRate;
    }

    public static function databaseValues(float $trafficRate = self::DEFAULT_TRAFFIC_RATE): array
    {
        $trafficRate = self::requireTrafficRate($trafficRate);

        return [
            'traffic_rate' => $trafficRate,
            'is_dynamic_rate' => 0,
            'dynamic_rate_type' => 0,
            'dynamic_rate_config' => json_encode(
                self::dynamicRateConfig($trafficRate),
                JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
            ),
            'node_class' => 0,
            'node_group' => 0,
            'node_speedlimit' => 0,
            'node_bandwidth_limit' => 0,
            'bandwidthlimit_resetday' => self::RESET_DAY,
        ];
    }

    public static function dynamicRateConfig(float $trafficRate = self::DEFAULT_TRAFFIC_RATE): array
    {
        $trafficRate = self::requireTrafficRate($trafficRate);

        return [
            'max_rate' => $trafficRate,
            'max_rate_time' => 22,
            'min_rate' => $trafficRate,
            'min_rate_time' => 3,
        ];
    }

    public static function withoutLegacyProfitMetadata(string $customConfig): string
    {
        $decoded = json_decode($customConfig, true);

        if (! is_array($decoded)) {
            return $customConfig;
        }

        if (is_array($decoded['xnode'] ?? null)) {
            unset(
                $decoded['xnode']['billing_profile'],
                $decoded['xnode']['profit_policy_version']
            );
        }

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function requireTrafficRate(mixed $trafficRate): float
    {
        $trafficRate = self::normalizeTrafficRate($trafficRate);

        if ($trafficRate === null) {
            throw new InvalidArgumentException('XNode traffic rate must be one of 2, 4, 6, 8, or 10');
        }

        return $trafficRate;
    }
}
