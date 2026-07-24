<?php

declare(strict_types=1);

namespace App\Controllers;

use PHPUnit\Framework\TestCase;

final class AndroidGuideContractTest extends TestCase
{
    public function testDashboardAndroidLinksResolveToPublishedAssetsAndGuide(): void
    {
        $root = dirname(__DIR__, 3);
        $routes = file_get_contents($root . '/app/routes.php');
        $dashboard = file_get_contents($root . '/resources/views/tabler/user/index.tpl');
        $controller = file_get_contents($root . '/src/Controllers/User/DocsController.php');

        self::assertIsString($routes);
        self::assertIsString($dashboard);
        self::assertIsString($controller);
        self::assertStringContainsString(
            "\$app->get('/docs/android', App\\Controllers\\User\\DocsController::class . ':androidRedirect');",
            $routes
        );
        self::assertStringContainsString(
            "\$group->get('/docs/android', App\\Controllers\\User\\DocsController::class . ':android');",
            $routes
        );
        self::assertStringContainsString(
            "value='/downloads/eziplc-android.apk'",
            $dashboard
        );
        self::assertStringContainsString(
            "value='/docs/android'",
            $dashboard
        );
        self::assertStringContainsString(
            "return \$response->withRedirect('/user/docs/android');",
            $controller
        );
        self::assertStringContainsString(
            "fetch('user/docs/android.tpl')",
            $controller
        );
    }

    public function testPublishedApkMatchesItsChecksum(): void
    {
        $downloadDirectory = dirname(__DIR__, 3) . '/public/downloads';
        $apk = $downloadDirectory . '/eziplc-android.apk';
        $checksumFile = $apk . '.sha256';

        self::assertFileExists($apk);
        self::assertFileExists($checksumFile);

        $checksumLine = trim((string) file_get_contents($checksumFile));
        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}  eziplc-android\.apk$/',
            $checksumLine
        );

        [$expectedHash] = preg_split('/\s+/', $checksumLine);
        self::assertSame($expectedHash, hash_file('sha256', $apk));
    }

    public function testGuideDocumentsSimpleInstallLoginSyncAndConnectFlow(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/tabler/user/docs/android.tpl'
        );

        self::assertIsString($template);
        self::assertStringContainsString(
            'href="/downloads/eziplc-android.apk" download',
            $template
        );
        self::assertStringContainsString(
            'href="/downloads/eziplc-android.apk.sha256"',
            $template
        );
        self::assertStringContainsString('EzIPLC 0.1.3', $template);
        self::assertStringContainsString(
            "{trans key='docs.android.login_once_note'}",
            $template
        );
        self::assertStringContainsString(
            "{trans key='docs.android.auto_sync_note'}",
            $template
        );
        self::assertStringContainsString(
            "{trans key='docs.android.vpn_body'}",
            $template
        );
        self::assertStringContainsString(
            "{trans key='docs.android.troubleshooting_title'}",
            $template
        );
    }

    public function testEveryAndroidGuideTranslationKeyExistsInBothLocales(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents(
            $root . '/resources/views/tabler/user/docs/android.tpl'
        );
        $zhLocale = require $root . '/resources/locale/frontend/zh_CN.php';
        $enLocale = require $root . '/resources/locale/frontend/en_US.php';

        self::assertIsString($template);
        preg_match_all('/docs\.android\.([a-z_]+)/', $template, $matches);

        $usedKeys = array_values(array_unique($matches[1]));
        $zhKeys = array_keys($zhLocale['docs']['android']);
        $enKeys = array_keys($enLocale['docs']['android']);
        sort($usedKeys);
        sort($zhKeys);
        sort($enKeys);

        self::assertSame($usedKeys, $zhKeys);
        self::assertSame($usedKeys, $enKeys);
    }
}
