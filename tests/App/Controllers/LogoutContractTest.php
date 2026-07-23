<?php

declare(strict_types=1);

namespace App\Controllers;

use PHPUnit\Framework\TestCase;

final class LogoutContractTest extends TestCase
{
    public function testUserLogoutRouteUsesGet(): void
    {
        $routes = file_get_contents(__DIR__ . '/../../../app/routes.php');

        $this->assertIsString($routes);
        $this->assertStringContainsString(
            "\$group->get('/logout', App\\Controllers\\UserController::class . ':logout');",
            $routes
        );
        $this->assertStringNotContainsString(
            "\$group->post('/logout', App\\Controllers\\UserController::class . ':logout');",
            $routes
        );
    }

    public function testUserAndAdminHeadersUseLogoutLinks(): void
    {
        $templates = [
            file_get_contents(__DIR__ . '/../../../resources/views/tabler/user/header.tpl'),
            file_get_contents(__DIR__ . '/../../../resources/views/tabler/admin/header.tpl'),
        ];

        foreach ($templates as $template) {
            $this->assertIsString($template);
            $this->assertStringContainsString('<a href="/user/logout" class="dropdown-item">', $template);
            $this->assertStringNotContainsString('<form method="post" action="/user/logout"', $template);
        }
    }
}
