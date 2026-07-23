<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Translator;

#[CoversClass(I18n::class)]
final class I18nTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../../../app/predefine.php';
    }

    public function testTrans(): void
    {
        // exsisting locale
        $key = 'lang_name';
        $lang = 'en_US';
        $expectedTranslation = 'English(Simplified)';

        $translation = I18n::trans($key, $lang);

        $this->assertSame($expectedTranslation, $translation);
        $this->assertSame('Fixed Invite Subscription Rewards', I18n::trans('bot.invite_reward_title', $lang));
        $this->assertSame('固定邀请订阅奖励', I18n::trans('bot.invite_reward_title', 'zh_CN'));
        // non-existing locale
        $key = 'non_existent_key';

        $translation = I18n::trans($key, $lang);

        $this->assertSame($key, $translation);
    }

    public function testGetLocaleList(): void
    {
        $expectedLocales = ['en_US', 'ja_JP', 'zh_CN', 'zh_TW'];

        $locales = I18n::getLocaleList();

        $this->assertSame($expectedLocales, $locales);
    }

    public function testGetTranslatorr(): void
    {
        $lang = 'en_US';

        $translator = I18n::getTranslator($lang);

        $this->assertInstanceOf(Translator::class, $translator);
        $this->assertSame($lang, $translator->getLocale());
    }
}
