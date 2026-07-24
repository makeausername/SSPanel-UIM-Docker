<?php

declare(strict_types=1);

namespace App\Controllers;

use PHPUnit\Framework\TestCase;

final class NodeServerCardContractTest extends TestCase
{
    public function testPaidOnlyNodeCardsDoNotRenderFreeRibbon(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/tabler/user/server.tpl'
        );

        self::assertIsString($template);
        self::assertStringNotContainsString("{trans key='node.free'}", $template);
        self::assertStringNotContainsString("\$server['class'] === 0", $template);
        self::assertStringContainsString("{if \$server['class'] > 0}", $template);
        self::assertStringContainsString(
            '<div class="ribbon bg-blue">LV. {$server[\'class\']}</div>',
            $template
        );
        self::assertStringContainsString('node-name-row', $template);
        self::assertStringContainsString('status-indicator', $template);
        self::assertStringContainsString('badges-list', $template);
        self::assertStringContainsString("{if \$user->class < \$server['class']}", $template);
    }
}
