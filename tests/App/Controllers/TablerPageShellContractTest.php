<?php

declare(strict_types=1);

namespace Tests\App\Controllers;

use PHPUnit\Framework\TestCase;

final class TablerPageShellContractTest extends TestCase
{
    public function testAllSharedPageShellsRemoveDesktopScrollbarOffset(): void
    {
        foreach ([
            'resources/views/tabler/header.tpl',
            'resources/views/tabler/user/header.tpl',
            'resources/views/tabler/admin/header.tpl',
        ] as $relativePath) {
            $template = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);

            self::assertNotFalse($template, sprintf('Unable to read %s', $relativePath));
            self::assertMatchesRegularExpression(
                '/@media\s*\(min-width:\s*992px\)\s*\{\s*:root\s*\{\s*margin-left:\s*0;\s*\}\s*\}/s',
                $template,
                sprintf('%s must neutralize Tabler desktop scrollbar offset', $relativePath)
            );
        }
    }
}
