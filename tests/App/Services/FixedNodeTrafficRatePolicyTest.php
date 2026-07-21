<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Node;
use PHPUnit\Framework\TestCase;
use function json_decode;

class FixedNodeTrafficRatePolicyTest extends TestCase
{
    public function testApplyAlwaysDisablesDynamicRateAndPreservesTrafficRate(): void
    {
        $node = new Node();
        $node->traffic_rate = 1.5;
        $node->is_dynamic_rate = 1;
        $node->dynamic_rate_type = 1;
        $node->dynamic_rate_config = '{"max_rate":4}';

        FixedNodeTrafficRatePolicy::apply($node);

        $this->assertSame(1.5, (float) $node->traffic_rate);
        $this->assertSame(0, $node->is_dynamic_rate);
        $this->assertSame(0, $node->dynamic_rate_type);
        $this->assertSame(
            FixedNodeTrafficRatePolicy::compatibilityConfig(1.5),
            json_decode($node->dynamic_rate_config, true, 512, JSON_THROW_ON_ERROR)
        );
    }
}
