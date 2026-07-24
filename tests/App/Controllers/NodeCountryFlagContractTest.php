<?php

declare(strict_types=1);

namespace App\Controllers;

use PHPUnit\Framework\TestCase;

final class NodeCountryFlagContractTest extends TestCase
{
    public function testAdminFormsPersistValidatedCountryCodes(): void
    {
        $controller = $this->read('src/Controllers/Admin/NodeController.php');
        $create = $this->read('resources/views/tabler/admin/node/create.tpl');
        $edit = $this->read('resources/views/tabler/admin/node/edit.tpl');

        self::assertStringContainsString("'country_code'", $controller);
        self::assertSame(2, substr_count($controller, 'NodeCountryService::normalize'));
        self::assertStringContainsString('NodeCountryService::commonOptions()', $controller);
        foreach ([$create, $edit] as $template) {
            self::assertStringContainsString('id="country_code"', $template);
            self::assertStringContainsString('maxlength="2"', $template);
            self::assertStringContainsString('ISO 3166-1', $template);
        }
    }

    public function testUserNodeCardsRenderOnlyNormalizedTablerFlags(): void
    {
        $controller = $this->read('src/Controllers/User/ServerController.php');
        $header = $this->read('resources/views/tabler/user/header.tpl');
        $template = $this->read('resources/views/tabler/user/server.tpl');

        self::assertStringContainsString('NodeCountryService::flagCode', $controller);
        self::assertStringContainsString(
            '@tabler/core@1.4.0/dist/css/tabler-flags.min.css',
            $header
        );
        self::assertStringContainsString(
            'flag-country-{$server[\'country_code\']}',
            $template
        );
        self::assertStringContainsString(
            '{$server[\'name\']|escape:\'html\'}',
            $template
        );
    }

    private function read(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        self::assertIsString($contents);

        return $contents;
    }
}
