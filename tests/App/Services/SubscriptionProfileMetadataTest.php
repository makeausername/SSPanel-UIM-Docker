<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\TestCase;

final class SubscriptionProfileMetadataTest extends TestCase
{
    public function testProfileMetadataUsesEziplcForThirdPartyClients(): void
    {
        self::assertSame('EzIPLC', Subscribe::PROFILE_NAME);
        self::assertSame('base64:RXpJUExD', Subscribe::profileTitleHeader());
        self::assertSame(
            'attachment; filename=EzIPLC',
            Subscribe::contentDispositionHeader()
        );
    }

    public function testShadowrocketImportUrlCarriesExplicitEziplcRemark(): void
    {
        $subscriptionUrl = 'https://example.com/sub/private-token/v2ray';
        $importUrl = Subscribe::shadowrocketImportUrl($subscriptionUrl);
        $prefix = 'shadowrocket://add/sub://';
        $suffix = '?remark=EzIPLC';

        self::assertStringStartsWith($prefix, $importUrl);
        self::assertStringEndsWith($suffix, $importUrl);

        $payload = substr($importUrl, strlen($prefix), -strlen($suffix));
        self::assertSame(
            $subscriptionUrl . '?flag=shadowrocket',
            base64_decode($payload, true)
        );
    }

    public function testShadowrocketImportUrlPreservesAnExistingQueryString(): void
    {
        $subscriptionUrl = 'https://example.com/sub/private-token/v2ray?source=panel';
        $importUrl = Subscribe::shadowrocketImportUrl($subscriptionUrl);
        $prefix = 'shadowrocket://add/sub://';
        $suffix = '?remark=EzIPLC';
        $payload = substr($importUrl, strlen($prefix), -strlen($suffix));

        self::assertSame(
            $subscriptionUrl . '&flag=shadowrocket',
            base64_decode($payload, true)
        );
    }

    public function testProfileMetadataIsAppliedBeforeSubtypeSpecificHeaders(): void
    {
        $controller = file_get_contents(
            dirname(__DIR__, 3) . '/src/Controllers/SubController.php'
        );

        self::assertIsString($controller);
        $profileTitle = strpos($controller, "->withHeader('Profile-Title', \$sub_profile_title)");
        $contentDisposition = strpos(
            $controller,
            "->withHeader('Content-Disposition', \$sub_content_disposition)"
        );
        $clashBranch = strpos($controller, "if (\$subtype === 'clash')");

        self::assertIsInt($profileTitle);
        self::assertIsInt($contentDisposition);
        self::assertIsInt($clashBranch);
        self::assertLessThan($clashBranch, $profileTitle);
        self::assertLessThan($clashBranch, $contentDisposition);
    }

    public function testJsonSubscriptionUsesTheSameProfileName(): void
    {
        $jsonSubscription = file_get_contents(
            dirname(__DIR__, 3) . '/src/Services/Subscribe/Json.php'
        );

        self::assertIsString($jsonSubscription);
        self::assertStringContainsString(
            "'sub_name' => Subscribe::PROFILE_NAME",
            $jsonSubscription
        );
        self::assertStringNotContainsString(
            "'sub_name' => \$_ENV['appName']",
            $jsonSubscription
        );
    }
}
