<?php

declare(strict_types=1);

namespace App\Controllers;

use PHPUnit\Framework\TestCase;

final class ShadowrocketGuideContractTest extends TestCase
{
    public function testDashboardLinksToAuthenticatedShadowrocketGuide(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 3) . '/app/routes.php');
        $dashboard = file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/tabler/user/index.tpl'
        );

        self::assertIsString($routes);
        self::assertIsString($dashboard);
        self::assertStringContainsString(
            "\$group->get('/docs/shadowrocket', App\\Controllers\\User\\DocsController::class . ':shadowrocket');",
            $routes
        );
        self::assertStringContainsString(
            "\$app->get('/docs/shadowrocket', App\\Controllers\\User\\DocsController::class . ':shadowrocketRedirect');",
            $routes
        );
        self::assertStringContainsString(
            "value='/user/docs/shadowrocket'",
            $dashboard
        );
        self::assertStringNotContainsString(
            "value='/docs/shadowrocket'",
            $dashboard
        );
    }

    public function testLegacyShadowrocketUrlRedirectsToCanonicalGuide(): void
    {
        $controller = file_get_contents(
            dirname(__DIR__, 3) . '/src/Controllers/User/DocsController.php'
        );

        self::assertIsString($controller);
        self::assertStringContainsString(
            "return \$response->withRedirect('/user/docs/shadowrocket');",
            $controller
        );
    }

    public function testGuideUsesPrivateV2raySubscriptionAndLocalizedContent(): void
    {
        $controller = file_get_contents(
            dirname(__DIR__, 3) . '/src/Controllers/User/DocsController.php'
        );
        $template = file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/tabler/user/docs/shadowrocket.tpl'
        );
        $zhLocale = file_get_contents(
            dirname(__DIR__, 3) . '/resources/locale/frontend/zh_CN.php'
        );
        $enLocale = file_get_contents(
            dirname(__DIR__, 3) . '/resources/locale/frontend/en_US.php'
        );

        self::assertIsString($controller);
        self::assertIsString($template);
        self::assertIsString($zhLocale);
        self::assertIsString($enLocale);
        self::assertStringContainsString(
            "Subscribe::getUniversalSubLink(\$this->user) . '/v2ray'",
            $controller
        );
        self::assertStringContainsString(
            "fetch('user/docs/shadowrocket.tpl')",
            $controller
        );
        self::assertStringContainsString(
            'data-clipboard-text="{$subscriptionUrl|escape:\'html\'}"',
            $template
        );
        self::assertStringContainsString(
            "{trans key='docs.shadowrocket.import_title'}",
            $template
        );
        self::assertStringContainsString("'shadowrocket' => [", $zhLocale);
        self::assertStringContainsString("'shadowrocket' => [", $enLocale);
        self::assertStringContainsString("'type_label' => 'Subscribe'", $zhLocale);
        self::assertStringContainsString("'type_label' => 'Subscribe'", $enLocale);
    }

    public function testEveryGuideTranslationKeyExistsInBothLocales(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/tabler/user/docs/shadowrocket.tpl'
        );
        $zhLocale = require dirname(__DIR__, 3) . '/resources/locale/frontend/zh_CN.php';
        $enLocale = require dirname(__DIR__, 3) . '/resources/locale/frontend/en_US.php';

        self::assertIsString($template);
        preg_match_all('/docs\.shadowrocket\.([a-z_]+)/', $template, $matches);

        $usedKeys = array_values(array_unique($matches[1]));
        $zhKeys = array_keys($zhLocale['docs']['shadowrocket']);
        $enKeys = array_keys($enLocale['docs']['shadowrocket']);
        sort($usedKeys);
        sort($zhKeys);
        sort($enKeys);

        self::assertSame($usedKeys, $zhKeys);
        self::assertSame($usedKeys, $enKeys);
    }
}
