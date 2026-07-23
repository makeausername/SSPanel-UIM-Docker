<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\TestCase;
use function is_array;
use function sort;
use const BASE_PATH;

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
        $this->assertSame('Loading...', FrontendI18n::trans('common.loading', [], 'en-US'));
        $this->assertSame('加载中...', FrontendI18n::trans('common.loading', [], 'zh-CN'));
        $this->assertSame(
            'Invite link reset successfully',
            FrontendI18n::trans('response.invite_reset_success', [], 'en-US')
        );
        $this->assertSame(
            'The security check failed. Refresh the page and try again.',
            FrontendI18n::trans('response.security.csrf_rejected', [], 'en-US')
        );
        $this->assertSame(
            '安全校验失败，请刷新页面后重试。',
            FrontendI18n::trans('response.security.csrf_rejected', [], 'zh-CN')
        );
    }

    public function testMissingTranslationReturnsKey(): void
    {
        $this->assertSame('missing.key', FrontendI18n::trans('missing.key', [], 'en-US'));
    }

    public function testFrontendLocalesHaveMatchingKeys(): void
    {
        $english = require BASE_PATH . '/resources/locale/frontend/en_US.php';
        $chinese = require BASE_PATH . '/resources/locale/frontend/zh_CN.php';
        $englishKeys = self::flattenKeys($english);
        $chineseKeys = self::flattenKeys($chinese);
        sort($englishKeys);
        sort($chineseKeys);

        $this->assertSame($englishKeys, $chineseKeys);
    }

    /**
     * @param array<string, mixed> $values
     *
     * @return list<string>
     */
    private static function flattenKeys(array $values, string $prefix = ''): array
    {
        $keys = [];
        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            $keys[] = $path;
            if (is_array($value)) {
                $keys = [...$keys, ...self::flattenKeys($value, $path)];
            }
        }

        return $keys;
    }
}
