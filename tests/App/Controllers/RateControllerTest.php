<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\User\RateController;
use App\Models\Node;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RateControllerTest extends TestCase
{
    public function testFixedRateNodeReturnsFullDayChartData(): void
    {
        $node = new Node();
        $node->name = 'Hong Kong A';
        $node->traffic_rate = 2;
        $node->is_dynamic_rate = 0;

        $data = $this->buildRateData($node);

        $this->assertSame('Hong Kong A', $data['msg']);
        $this->assertSame(array_fill(0, 24, 2.0), $data['data']);
    }

    public function testDynamicRateNodeReturnsFullDayChartData(): void
    {
        $node = new Node();
        $node->name = 'Dynamic Node';
        $node->is_dynamic_rate = 1;
        $node->dynamic_rate_type = 1;
        $node->dynamic_rate_config = json_encode([
            'max_rate' => 3,
            'max_rate_time' => 22,
            'min_rate' => 0.5,
            'min_rate_time' => 3,
        ], JSON_THROW_ON_ERROR);

        $data = $this->buildRateData($node);

        $this->assertCount(24, $data['data']);
        $this->assertSame(0.5, $data['data'][3]);
        $this->assertSame(3.0, $data['data'][22]);
    }

    public function testRateTemplateDrawsInitialServerData(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../resources/views/tabler/user/rate.tpl');

        $this->assertIsString($template);
        $this->assertStringContainsString('const initialChartData = {$initial_chart};', $template);
        $this->assertStringContainsString('drawChart(initialChartData);', $template);
        $this->assertStringNotContainsString('data: []', $template);
    }

    /**
     * @return array{msg: string, data: array<int, float>}
     */
    private function buildRateData(Node $node): array
    {
        $reflection = new ReflectionClass(RateController::class);
        $controller = $reflection->newInstanceWithoutConstructor();
        $method = $reflection->getMethod('buildRateData');

        return $method->invoke($controller, $node);
    }
}
