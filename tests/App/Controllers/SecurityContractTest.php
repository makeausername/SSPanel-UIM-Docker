<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Admin\ProductController;
use App\Controllers\OAuthController;
use PHPUnit\Framework\TestCase;
use const BASE_PATH;

final class SecurityContractTest extends TestCase
{
    public function testPaymentPurchaseIsPostOnly(): void
    {
        $routes = file_get_contents(BASE_PATH . '/app/routes.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString("post('/payment/purchase/{type}'", $routes);
        $this->assertStringNotContainsString("get('/payment/purchase/{type}'", $routes);
    }

    public function testProductNamesArePlainTextOnly(): void
    {
        $this->assertTrue(ProductController::isValidProductName('Mini / 迷你套餐'));
        $this->assertFalse(ProductController::isValidProductName(''));
        $this->assertFalse(ProductController::isValidProductName('<img src=x onerror=alert(1)>'));
        $this->assertFalse(ProductController::isValidProductName("safe\nscript"));
    }

    public function testDiscordGuildMembershipUsesTheAuthenticatedDiscordIdentity(): void
    {
        $this->assertSame(
            'https://discord.com/api/guilds/guild-id/members/discord-user-id',
            OAuthController::discordGuildMemberUrl('guild-id', 'discord-user-id')
        );
    }
}
