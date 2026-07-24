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
