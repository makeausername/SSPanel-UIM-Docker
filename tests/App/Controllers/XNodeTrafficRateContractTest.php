<?php

declare(strict_types=1);

namespace App\Controllers;

use PHPUnit\Framework\TestCase;

final class XNodeTrafficRateContractTest extends TestCase
{
    public function testAdminFormsRenderAndSubmitTheXNodeTrafficRateDropdown(): void
    {
        $controller = $this->read('src/Controllers/Admin/NodeController.php');

        self::assertSame(
            2,
            substr_count(
                $controller,
                "assign('xnode_traffic_rate_options', XNodeNodePolicy::trafficRateOptions())"
            )
        );
        self::assertStringContainsString('XNodeNodePolicy::normalizeTrafficRate(', $controller);
        self::assertStringContainsString(
            'XNode 流量倍率无效，请选择 2、4、6、8 或 10 倍',
            $controller
        );

        foreach ([
            'resources/views/tabler/admin/node/create.tpl',
            'resources/views/tabler/admin/node/edit.tpl',
        ] as $relativePath) {
            $template = $this->read($relativePath);

            self::assertStringContainsString('id="traffic_rate" type="hidden"', $template);
            self::assertStringContainsString('id="standard_traffic_rate"', $template);
            self::assertStringContainsString('id="xnode_traffic_rate"', $template);
            self::assertStringContainsString(
                '{foreach $xnode_traffic_rate_options as $traffic_rate_option}',
                $template
            );
            self::assertStringContainsString(
                "isXNode ? $('#xnode_traffic_rate').val() : $('#standard_traffic_rate').val()",
                $template
            );
            self::assertStringNotContainsString("const trafficRate = '2';", $template);
        }
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        self::assertIsString($contents);

        return $contents;
    }
}
