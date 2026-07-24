<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\TestCase;

final class NodeCountryServiceTest extends TestCase
{
    public function testCountryCodesAreNormalizedForStorageAndFlagClasses(): void
    {
        self::assertSame('SG', NodeCountryService::normalize(' sg '));
        self::assertSame('HK', NodeCountryService::normalize('hk'));
        self::assertSame('', NodeCountryService::normalize(''));
        self::assertSame('sg', NodeCountryService::flagCode('SG'));
        self::assertSame('', NodeCountryService::flagCode(''));
    }

    public function testUnsupportedOrUnsafeValuesAreRejected(): void
    {
        self::assertNull(NodeCountryService::normalize('ZZ'));
        self::assertNull(NodeCountryService::normalize('SG-A'));
        self::assertNull(NodeCountryService::normalize('<script>'));
        self::assertNull(NodeCountryService::normalize(null));
        self::assertSame('', NodeCountryService::flagCode('<script>'));
    }

    public function testEverySuggestedCountryIsSupported(): void
    {
        $options = NodeCountryService::commonOptions();

        self::assertSame('新加坡', $options['SG']);
        self::assertSame('中国香港', $options['HK']);
        self::assertSame('中国台湾', $options['TW']);
        foreach (array_keys($options) as $code) {
            self::assertSame($code, NodeCountryService::normalize($code));
        }
    }
}
