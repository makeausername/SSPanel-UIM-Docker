<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use PHPUnit\Framework\TestCase;
use function json_decode;

class XNodeNodePolicyTest extends TestCase
{
    public function testPolicyAppliesUniformTwoTimesRateAndNoNodeRestrictions(): void
    {
        $node = new Node();
        $node->name = 'HKG Alpha';
        $node->custom_config = '{"xnode":{"enabled":true,"billing_profile":"hkg_as3_pro_medium","profit_policy_version":2}}';
        $node->node_bandwidth = 123456;
        $node->traffic_rate = 36;
        $node->is_dynamic_rate = 1;
        $node->dynamic_rate_type = 1;
        $node->dynamic_rate_config = '{"max_rate":36}';
        $node->node_class = 10;
        $node->node_group = 9;
        $node->node_speedlimit = 100;
        $node->node_bandwidth_limit = 500;
        $node->bandwidthlimit_resetday = 15;

        XNodeNodePolicy::apply($node);

        $this->assertSame(2.0, (float) $node->traffic_rate);
        $this->assertSame(0, (int) $node->is_dynamic_rate);
        $this->assertSame(0, (int) $node->dynamic_rate_type);
        $this->assertSame(XNodeNodePolicy::dynamicRateConfig(), json_decode(
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
        $this->assertArrayNotHasKey('billing_profile', $customConfig['xnode']);
        $this->assertArrayNotHasKey('profit_policy_version', $customConfig['xnode']);
        $this->assertSame('HKG Alpha', $node->name);
        $this->assertSame(123456, (int) $node->node_bandwidth);
    }

    public function testInvalidCustomConfigIsPreservedInsteadOfDestroyed(): void
    {
        $this->assertSame(
            'legacy-non-json-value',
            XNodeNodePolicy::withoutLegacyProfitMetadata('legacy-non-json-value')
        );
    }

    public function testPolicyOnlyAppliesToXNodeSort(): void
    {
        $this->assertTrue(XNodeNodePolicy::appliesTo(15));
        $this->assertFalse(XNodeNodePolicy::appliesTo(14));
    }
}
