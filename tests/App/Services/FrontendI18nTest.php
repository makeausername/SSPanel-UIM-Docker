<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\TestCase;

final class FrontendI18nTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../../../app/predefine.php';
    }

    protected function setUp(): void
    {
        Locale::setCurrent(Locale::DEFAULT_LOCALE);
    }

    public function testTranslationLookup(): void
    {
        $this->assertSame('Language', FrontendI18n::trans('common.language', [], 'en-US'));
        $this->assertSame('语言', FrontendI18n::trans('common.language', [], 'zh-CN'));
        $this->assertSame(
            'Subscription Reward Records',
            FrontendI18n::trans('user.invite.reward_records', [], 'en-US')
        );
        $this->assertSame(
            '订阅奖励记录',
            FrontendI18n::trans('user.invite.reward_records', [], 'zh-CN')
        );
    }

    public function testMissingTranslationReturnsKey(): void
    {
        $this->assertSame('missing.key', FrontendI18n::trans('missing.key', [], 'en-US'));
    }
}
