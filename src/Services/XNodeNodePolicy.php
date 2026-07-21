<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function round;
use function str_contains;
use function strtoupper;

final class XNodeNodePolicy
{
    public const SORT = 15;
    public const RESET_DAY = 1;
    public const PROFILE_LAX_MICRO = 'lax_as3_pro_micro';
    public const PROFILE_LAX_MEDIUM = 'lax_as3_pro_medium';
    public const PROFILE_HKG_MICRO = 'hkg_as3_pro_micro';
    public const PROFILE_HKG_MEDIUM = 'hkg_as3_pro_medium';
    public const DEFAULT_PROFILE = self::PROFILE_LAX_MICRO;
    public const CONSERVATIVE_PROFILE = self::PROFILE_HKG_MEDIUM;
    public const BUDGET_USD_CNY = 7.0;
    public const OPERATING_COST_RATIO = 0.15;
    public const TARGET_NET_MARGIN = 0.50;
    public const WORST_PLAN_MONTHLY_REVENUE = 125.0;
    public const WORST_PLAN_MONTHLY_QUOTA_GB = 2100.0;

    private const PROFILES = [
        self::PROFILE_LAX_MICRO => [
            'label' => 'LAX AS3 Pro MICRO ($87.90 / 7000GB)',
            'traffic_rate' => 5.0,
            'monthly_cost_usd' => 87.90,
            'included_bidirectional_gb' => 7000.0,
        ],
        self::PROFILE_LAX_MEDIUM => [
            'label' => 'LAX AS3 Pro MEDIUM ($199.90 / 14000GB)',
            'traffic_rate' => 6.0,
            'monthly_cost_usd' => 199.90,
            'included_bidirectional_gb' => 14000.0,
        ],
        self::PROFILE_HKG_MICRO => [
            'label' => 'HKG AS3 Pro MICRO ($179.90 / 2000GB)',
            'traffic_rate' => 32.0,
            'monthly_cost_usd' => 179.90,
            'included_bidirectional_gb' => 2000.0,
        ],
        self::PROFILE_HKG_MEDIUM => [
            'label' => 'HKG AS3 Pro MEDIUM ($239.90 / 2500GB)',
            'traffic_rate' => 36.0,
            'monthly_cost_usd' => 239.90,
            'included_bidirectional_gb' => 2500.0,
        ],
    ];

    public static function appliesTo(int $sort): bool
    {
        return $sort === self::SORT;
    }

    public static function apply(Node $node, ?string $profile = null): void
    {
        $profile = self::resolveProfile(
            $profile ?? self::profileFromCustomConfig((string) $node->custom_config),
            (string) $node->name
        );

        foreach (self::databaseValues($profile) as $field => $value) {
            $node->{$field} = $value;
        }

        $node->custom_config = self::customConfigWithProfile((string) $node->custom_config, $profile);
    }

    public static function databaseValues(?string $profile = null): array
    {
        $profile = self::normalizeProfile($profile, self::DEFAULT_PROFILE);
        $trafficRate = self::trafficRate($profile);

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

    public static function dynamicRateConfig(float $trafficRate): array
    {
        return [
            'max_rate' => $trafficRate,
            'max_rate_time' => 22,
            'min_rate' => $trafficRate,
            'min_rate_time' => 3,
        ];
    }

    public static function profiles(): array
    {
        $profiles = self::PROFILES;

        foreach ($profiles as $profile => $config) {
            $profiles[$profile]['projected_net_margin'] = round(self::projectedNetMargin($profile) * 100, 1);
        }

        return $profiles;
    }

    public static function projectedNetMargin(string $profile, ?float $trafficRate = null): float
    {
        $profile = self::normalizeProfile($profile, self::CONSERVATIVE_PROFILE);
        $config = self::PROFILES[$profile];
        $trafficRate ??= $config['traffic_rate'];
        $monthlyCapacityRevenue = $config['included_bidirectional_gb']
            * $trafficRate
            / self::WORST_PLAN_MONTHLY_QUOTA_GB
            * self::WORST_PLAN_MONTHLY_REVENUE;
        $monthlyServerCost = $config['monthly_cost_usd'] * self::BUDGET_USD_CNY;

        return 1 - $monthlyServerCost / $monthlyCapacityRevenue - self::OPERATING_COST_RATIO;
    }

    public static function trafficRate(string $profile): float
    {
        $profile = self::normalizeProfile($profile, self::CONSERVATIVE_PROFILE);

        return self::PROFILES[$profile]['traffic_rate'];
    }

    public static function resolveProfile(?string $profile, string $nodeName = ''): string
    {
        if ($profile !== null && isset(self::PROFILES[$profile])) {
            return $profile;
        }

        return self::inferProfile($nodeName) ?? self::CONSERVATIVE_PROFILE;
    }

    public static function inferProfile(string $nodeName): ?string
    {
        $upperName = strtoupper($nodeName);
        $isMedium = str_contains($upperName, 'MEDIUM') || str_contains($nodeName, '中型');
        $isHkg = str_contains($upperName, 'HKG')
            || str_contains($upperName, 'HONG KONG')
            || str_contains($nodeName, '香港');
        $isLax = str_contains($upperName, 'LAX')
            || str_contains($upperName, 'LOS ANGELES')
            || str_contains($nodeName, '洛杉矶');

        if ($isHkg) {
            return $isMedium ? self::PROFILE_HKG_MEDIUM : self::PROFILE_HKG_MICRO;
        }

        if ($isLax) {
            return $isMedium ? self::PROFILE_LAX_MEDIUM : self::PROFILE_LAX_MICRO;
        }

        return null;
    }

    public static function profileFromCustomConfig(string $customConfig): ?string
    {
        $decoded = json_decode($customConfig, true);
        $profile = is_array($decoded) && is_array($decoded['xnode'] ?? null)
            ? ($decoded['xnode']['billing_profile'] ?? null)
            : null;

        return is_string($profile) && isset(self::PROFILES[$profile]) ? $profile : null;
    }

    public static function customConfigWithProfile(string $customConfig, string $profile): string
    {
        $profile = self::normalizeProfile($profile, self::CONSERVATIVE_PROFILE);
        $decoded = json_decode($customConfig, true);

        if (! is_array($decoded)) {
            $decoded = [];
        }

        if (! is_array($decoded['xnode'] ?? null)) {
            $decoded['xnode'] = [];
        }

        $decoded['xnode']['billing_profile'] = $profile;
        $decoded['xnode']['profit_policy_version'] = 2;

        return json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private static function normalizeProfile(?string $profile, string $fallback): string
    {
        return $profile !== null && isset(self::PROFILES[$profile]) ? $profile : $fallback;
    }
}
