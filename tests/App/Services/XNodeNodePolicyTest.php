<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use PHPUnit\Framework\TestCase;
use function json_decode;

class XNodeNodePolicyTest extends TestCase
{
    public function testPolicyAppliesSelectedProfitProfileAndNoNodeRestrictions(): void
    {
        $node = new Node();
        $node->name = 'HKG Alpha';
        $node->custom_config = '{"xnode":{"enabled":true}}';
        $node->node_bandwidth = 123456;
        $node->traffic_rate = 2;
        $node->is_dynamic_rate = 1;
        $node->dynamic_rate_type = 1;
        $node->dynamic_rate_config = '{"max_rate":3}';
        $node->node_class = 10;
        $node->node_group = 9;
        $node->node_speedlimit = 100;
        $node->node_bandwidth_limit = 500;
        $node->bandwidthlimit_resetday = 15;

        XNodeNodePolicy::apply($node, XNodeNodePolicy::PROFILE_HKG_MICRO);

        $this->assertSame(32.0, (float) $node->traffic_rate);
        $this->assertSame(0, (int) $node->is_dynamic_rate);
        $this->assertSame(0, (int) $node->dynamic_rate_type);
        $this->assertSame(XNodeNodePolicy::dynamicRateConfig(32.0), json_decode(
            (string) $node->dynamic_rate_config,
            true,
            512,
            JSON_THROW_ON_ERROR
        ));
        $this->assertSame(0, (int) $node->node_class);
        $this->assertSame(0, (int) $node->node_group);
        $this->assertSame(0, (int) $node->node_speedlimit);
        $this->assertSame(0, (int) $node->node_bandwidth_limit);
        $this->assertSame(1, (int) $node->bandwidthlimit_resetday);
        $customConfig = json_decode((string) $node->custom_config, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($customConfig['xnode']['enabled']);
        $this->assertSame(XNodeNodePolicy::PROFILE_HKG_MICRO, $customConfig['xnode']['billing_profile']);
        $this->assertSame(2, $customConfig['xnode']['profit_policy_version']);
        $this->assertSame('HKG Alpha', $node->name);
        $this->assertSame(123456, (int) $node->node_bandwidth);
    }

    public function testProfitProfilesUseConservativeRates(): void
    {
        $this->assertSame(5.0, XNodeNodePolicy::trafficRate(XNodeNodePolicy::PROFILE_LAX_MICRO));
        $this->assertSame(6.0, XNodeNodePolicy::trafficRate(XNodeNodePolicy::PROFILE_LAX_MEDIUM));
        $this->assertSame(32.0, XNodeNodePolicy::trafficRate(XNodeNodePolicy::PROFILE_HKG_MICRO));
        $this->assertSame(36.0, XNodeNodePolicy::trafficRate(XNodeNodePolicy::PROFILE_HKG_MEDIUM));

        foreach (XNodeNodePolicy::profiles() as $profile => $config) {
            $this->assertGreaterThanOrEqual(
                XNodeNodePolicy::TARGET_NET_MARGIN,
                XNodeNodePolicy::projectedNetMargin($profile)
            );
            $this->assertLessThan(
                XNodeNodePolicy::TARGET_NET_MARGIN,
                XNodeNodePolicy::projectedNetMargin($profile, 2.0)
            );
            $this->assertGreaterThanOrEqual(50.0, $config['projected_net_margin']);
        }
    }

    public function testProfileInferenceAndUnknownNodeFallbackAreProfitSafe(): void
    {
        $this->assertSame(
            XNodeNodePolicy::PROFILE_LAX_MEDIUM,
            XNodeNodePolicy::resolveProfile(null, 'LAX.AS3.Pro.MEDIUM')
        );
        $this->assertSame(
            XNodeNodePolicy::PROFILE_HKG_MICRO,
            XNodeNodePolicy::resolveProfile(null, '香港-A1')
        );
        $this->assertSame(
            XNodeNodePolicy::CONSERVATIVE_PROFILE,
            XNodeNodePolicy::resolveProfile(null, 'Unclassified node')
        );
    }

    public function testPolicyOnlyAppliesToXNodeSort(): void
    {
        $this->assertTrue(XNodeNodePolicy::appliesTo(15));
        $this->assertFalse(XNodeNodePolicy::appliesTo(14));
    }
}
