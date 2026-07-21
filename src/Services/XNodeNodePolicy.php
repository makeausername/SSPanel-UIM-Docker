<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use function is_array;
use function json_decode;
use function json_encode;

final class XNodeNodePolicy
{
    public const SORT = 15;
    public const TRAFFIC_RATE = 2.0;
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

        $node->custom_config = self::withoutLegacyProfitMetadata((string) $node->custom_config);
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
}
