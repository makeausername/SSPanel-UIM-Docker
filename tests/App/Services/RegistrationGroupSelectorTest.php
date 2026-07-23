<?php

declare(strict_types=1);

namespace App\Services;

use PHPUnit\Framework\TestCase;

final class RegistrationGroupSelectorTest extends TestCase
{
    public function testSelectPreservesMultiDigitGroupId(): void
    {
        self::assertSame(2048, RegistrationGroupSelector::select('2048'));
    }

    public function testSelectReturnsOnlyConfiguredGroups(): void
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            self::assertContains(
                RegistrationGroupSelector::select('10,20,300'),
                [10, 20, 300]
            );
        }
    }

    public function testParseNormalizesWhitespaceDuplicatesAndInvalidValues(): void
    {
        self::assertSame(
            [10, 20, 0],
            RegistrationGroupSelector::parse(' 10,20,10,-1,invalid,70000,0 ')
        );
    }

    public function testEmptyOrInvalidConfigurationFallsBackToDefaultGroup(): void
    {
        self::assertSame(0, RegistrationGroupSelector::select(''));
        self::assertSame(0, RegistrationGroupSelector::select('invalid,-1,70000'));
    }
}
