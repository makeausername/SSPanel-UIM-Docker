<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Admin\SystemController;
use PHPUnit\Framework\TestCase;

final class SystemControllerTest extends TestCase
{
    public function testRemoteVersionNormalizationRejectsFailuresAndUnexpectedBodies(): void
    {
        $this->assertNull(SystemController::normalizeLatestVersion(false));
        $this->assertNull(SystemController::normalizeLatestVersion(''));
        $this->assertNull(SystemController::normalizeLatestVersion('<html>upstream error</html>'));
        $this->assertSame('25.1.0', SystemController::normalizeLatestVersion(" 25.1.0\n"));
        $this->assertSame('2026.7.24-rc.1', SystemController::normalizeLatestVersion('2026.7.24-rc.1'));
    }
}
