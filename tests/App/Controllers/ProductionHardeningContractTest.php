<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Gateway\PayPal;
use App\Services\Payment;
use PHPUnit\Framework\TestCase;

final class ProductionHardeningContractTest extends TestCase
{
    public function testDisabledGatewaysRemainResolvableForCallbacksAndReturns(): void
    {
        self::assertSame('\\' . PayPal::class, Payment::getPaymentByName('paypal', false));
        self::assertNull(Payment::getPaymentByName('does-not-exist', false));
    }

    public function testPayPalApprovalCapturesBeforeRedirecting(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/tabler/gateway/paypal.tpl'
        );

        self::assertIsString($template);
        self::assertStringContainsString('actions.order.capture()', $template);
        self::assertStringContainsString("window.location.assign('/user/invoice/", $template);
        self::assertStringNotContainsString('setTimeout(location.href =', $template);
    }

    public function testUserControlledFeedbackUsesTextContent(): void
    {
        foreach ([
            'resources/views/tabler/footer.tpl',
            'resources/views/tabler/admin/footer.tpl',
            'resources/views/tabler/auth/login.tpl',
            'resources/views/tabler/auth/mfa.tpl',
        ] as $relativePath) {
            $template = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);

            self::assertIsString($template);
            self::assertStringNotContainsString('.innerHTML = res.msg', $template);
            self::assertStringNotContainsString('.innerHTML = verificationJSON.msg', $template);
        }
    }

    public function testRichTextAndLegacyDetectLogTemplatesEscapeStoredContent(): void
    {
        $docTemplate = file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/tabler/admin/docs/edit.tpl'
        );
        $detectTemplate = file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/tabler/user/detect/log.tpl'
        );
        $detectController = file_get_contents(
            dirname(__DIR__, 3) . '/src/Controllers/User/DetectLogController.php'
        );

        self::assertStringContainsString(
            '{$doc->content|escape:\'html\'}',
            (string) $docTemplate
        );
        self::assertStringNotContainsString(
            '<textarea id="tinymce">{$doc->content}</textarea>',
            (string) $docTemplate
        );
        self::assertStringContainsString(
            '{$log->rule->regex|escape:\'html\'}',
            (string) $detectTemplate
        );
        self::assertStringContainsString('{if $log->rule !== null}', (string) $detectTemplate);
        self::assertStringContainsString('if ($rule !== null)', (string) $detectController);
    }

    public function testNodeNamesAreValidatedAndEscapedAtUserFacingSinks(): void
    {
        $controller = file_get_contents(
            dirname(__DIR__, 3) . '/src/Controllers/Admin/NodeController.php'
        );
        $serverTemplate = file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/tabler/user/server.tpl'
        );
        $rateTemplate = file_get_contents(
            dirname(__DIR__, 3) . '/resources/views/tabler/user/rate.tpl'
        );

        self::assertStringContainsString('normalizeNodeName', (string) $controller);
        self::assertStringContainsString('JSON_HEX_TAG', (string) $controller);
        self::assertStringContainsString("|escape:'html'", (string) $serverTemplate);
        self::assertStringContainsString("|escape:'html'", (string) $rateTemplate);
    }

    public function testCliAndWebDatabaseBootFailuresHaveNonSuccessContracts(): void
    {
        $db = file_get_contents(dirname(__DIR__, 3) . '/src/Services/DB.php');
        $web = file_get_contents(dirname(__DIR__, 3) . '/public/index.php');
        $cli = file_get_contents(dirname(__DIR__, 3) . '/xcat');

        self::assertStringContainsString('throw new RuntimeException', (string) $db);
        self::assertStringContainsString('http_response_code(503)', (string) $web);
        self::assertStringContainsString('exit(1)', (string) $web);
        self::assertStringContainsString('exit(1)', (string) $cli);
    }

    public function testRemainingProductionGuardsArePresent(): void
    {
        $smogate = file_get_contents(
            dirname(__DIR__, 3) . '/src/Services/Gateway/Smogate.php'
        );
        $cronService = file_get_contents(
            dirname(__DIR__, 3) . '/src/Services/Cron.php'
        );
        $cronCommand = file_get_contents(
            dirname(__DIR__, 3) . '/src/Command/Cron.php'
        );
        $routes = file_get_contents(dirname(__DIR__, 3) . '/app/routes.php');

        self::assertStringNotContainsString("\$status !== '' &&", (string) $smogate);
        self::assertStringContainsString('(int) $user->transfer_enable > 0', (string) $cronService);
        self::assertStringContainsString('last_daily_traffic_report_time', (string) $cronCommand);
        self::assertStringContainsString('RequestBodyLimit(256 * 1024)', (string) $routes);
    }
}
