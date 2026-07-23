<?php

declare(strict_types=1);

namespace App\Controllers;

use PHPUnit\Framework\TestCase;

final class AdminSaveRedirectContractTest extends TestCase
{
    public function testEntitySavePagesUseExplicitListDestinations(): void
    {
        $redirects = [
            'user/edit.tpl' => '/admin/user',
            'node/create.tpl' => '/admin/node',
            'node/edit.tpl' => '/admin/node',
            'product/create.tpl' => '/admin/product',
            'product/edit.tpl' => '/admin/product',
            'announcement/create.tpl' => '/admin/announcement',
            'announcement/edit.tpl' => '/admin/announcement',
            'docs/create.tpl' => '/admin/docs',
            'docs/edit.tpl' => '/admin/docs',
        ];

        foreach ($redirects as $templatePath => $destination) {
            $template = file_get_contents(
                __DIR__ . '/../../../resources/views/tabler/admin/' . $templatePath
            );

            $this->assertIsString($template, $templatePath);
            $this->assertStringContainsString(
                "redirectAfterSuccess('{$destination}',",
                $template,
                $templatePath
            );
            $this->assertStringNotContainsString(
                'document.referrer',
                $template,
                $templatePath
            );
        }
    }

    public function testXNodeCreateKeepsItsInstallCommandDestination(): void
    {
        $template = file_get_contents(
            __DIR__ . '/../../../resources/views/tabler/admin/node/create.tpl'
        );

        $this->assertIsString($template);
        $this->assertStringContainsString(
            "'/admin/node/' + data.node_id + '/edit?open_xnode_install=1'",
            $template
        );
    }

    public function testAdminRedirectRunsOnConfirmationOrConfiguredDelay(): void
    {
        $footer = file_get_contents(
            __DIR__ . '/../../../resources/views/tabler/admin/footer.tpl'
        );

        $this->assertIsString($footer);
        $this->assertStringContainsString(
            '<button type="button" id="success-confirm"',
            $footer
        );
        $this->assertStringContainsString(
            "successConfirm.addEventListener('click', redirect, { once: true });",
            $footer
        );
        $this->assertStringContainsString(
            'window.setTimeout(redirect, Math.max(0, Number(delay) || 0));',
            $footer
        );
        $this->assertStringContainsString('window.location.assign(target);', $footer);
        $this->assertStringNotContainsString('href=""', $footer);
    }

    public function testPublicFeedbackDialogButtonsDoNotNavigate(): void
    {
        $footer = file_get_contents(
            __DIR__ . '/../../../resources/views/tabler/footer.tpl'
        );

        $this->assertIsString($footer);
        $this->assertStringContainsString(
            '<button type="button" id="success-confirm"',
            $footer
        );
        $this->assertStringNotContainsString('href=""', $footer);
    }
}
