<?php

declare(strict_types=1);

namespace App\Controllers;

use PHPUnit\Framework\TestCase;

final class LoginTemplateTest extends TestCase
{
    public function testLoginSupportsNativeFormFallback(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../resources/views/tabler/auth/login.tpl');

        $this->assertIsString($template);
        $this->assertStringContainsString('<form id="login-form" method="post" action="/auth/login"', $template);
        $this->assertStringContainsString('name="login_form" value="1"', $template);
        $this->assertStringContainsString('name="email"', $template);
        $this->assertStringContainsString('name="password"', $template);
        $this->assertStringContainsString('name="remember_me" value="true"', $template);
        $this->assertStringContainsString('<button type="submit"', $template);
        $this->assertStringContainsString('{$login_error|escape:\'html\'}', $template);
        $this->assertStringContainsString('onsubmit="prepareCaptchaForm()"', $template);
    }

    public function testCaptchaProvidersSupportNativeFormFields(): void
    {
        $template = file_get_contents(__DIR__ . '/../../../resources/views/tabler/captcha/div.tpl');

        $this->assertIsString($template);
        $this->assertStringContainsString('name="turnstile"', $template);
        $this->assertStringContainsString('name="hcaptcha"', $template);
        $this->assertStringContainsString('name="recaptcha_enterprise"', $template);
        $this->assertStringContainsString('name="geetest[lot_number]"', $template);
        $this->assertStringContainsString('name="geetest[captcha_output]"', $template);
        $this->assertStringContainsString('name="geetest[pass_token]"', $template);
        $this->assertStringContainsString('name="geetest[gen_time]"', $template);
    }
}
