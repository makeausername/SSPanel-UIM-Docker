<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\TestCase;

final class LocaleTest extends TestCase
{
    protected function setUp(): void
    {
        Locale::setCurrent(Locale::DEFAULT_LOCALE);
    }

    public function testSupportedLocales(): void
    {
        $this->assertSame(['zh-CN', 'en-US'], Locale::supportedLocales());
        $this->assertTrue(Locale::isSupported('zh-CN'));
        $this->assertTrue(Locale::isSupported('en-US'));
        $this->assertFalse(Locale::isSupported('zh_CN'));
    }

    public function testDetectPrioritizesSession(): void
    {
        $locale = Locale::detect(
            '/auth/login',
            [Locale::SESSION_KEY => 'en-US'],
            [Locale::COOKIE_KEY => 'zh-CN'],
            'zh-CN,zh;q=0.9'
        );

        $this->assertSame('en-US', $locale);
    }

    public function testDetectUsesCookieAfterInvalidSession(): void
    {
        $locale = Locale::detect(
            '/auth/login',
            [Locale::SESSION_KEY => 'fr-FR'],
            [Locale::COOKIE_KEY => 'en-US'],
            'zh-CN,zh;q=0.9'
        );

        $this->assertSame('en-US', $locale);
    }

    public function testDetectUsesAcceptLanguage(): void
    {
        $locale = Locale::detect('/auth/login', [], [], 'fr-FR, en-US;q=0.8, zh-CN;q=0.5');

        $this->assertSame('en-US', $locale);
    }

    public function testDetectFallsBackToChinese(): void
    {
        $locale = Locale::detect('/auth/login', [], [], 'fr-FR, de-DE;q=0.8');

        $this->assertSame('zh-CN', $locale);
    }

    public function testAdminPathAlwaysUsesChinese(): void
    {
        $locale = Locale::detect(
            '/admin/user',
            [Locale::SESSION_KEY => 'en-US'],
            [Locale::COOKIE_KEY => 'en-US'],
            'en-US,en;q=0.9'
        );

        $this->assertSame('zh-CN', $locale);
    }

    public function testFrontendPathClassification(): void
    {
        $this->assertTrue(Locale::isFrontendPath('/auth/login'));
        $this->assertTrue(Locale::isFrontendPath('/user/payment/return/stripe'));
        $this->assertFalse(Locale::isFrontendPath('/payment/notify/stripe'));
        $this->assertFalse(Locale::isFrontendPath('/sub/token/clash'));
        $this->assertFalse(Locale::isFrontendPath('/mod_mu/users'));
    }

    public function testInvalidLocaleFallsBackToChinese(): void
    {
        $this->assertSame('zh-CN', Locale::setCurrent('fr-FR'));
        $this->assertSame('zh_CN', Locale::resourceName('fr-FR'));
    }

    public function testSanitizeRedirect(): void
    {
        $this->assertSame('/user?tab=profile', Locale::sanitizeRedirect('/user?tab=profile', 'example.com'));
        $this->assertSame('/auth/login', Locale::sanitizeRedirect('https://example.com/auth/login', 'example.com'));
        $this->assertNull(Locale::sanitizeRedirect('https://evil.example/auth/login', 'example.com'));
        $this->assertNull(Locale::sanitizeRedirect('//evil.example/auth/login', 'example.com'));
        $this->assertNull(Locale::sanitizeRedirect('javascript://example.com/%0aalert(1)', 'example.com'));
    }
}
