<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class SecurityPermissionHardeningMigrationTest extends TestCase
{
    public function testMariaDbMetadataLookupsUsePreparableInformationSchemaQueries(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/db/migrations/2026072202-security-permission-hardening.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString('information_schema.TABLES', $source);
        $this->assertStringContainsString('information_schema.COLUMNS', $source);
        $this->assertStringContainsString('information_schema.STATISTICS', $source);
        $this->assertStringNotContainsString('SHOW TABLES LIKE ?', $source);
        $this->assertStringNotContainsString('SHOW COLUMNS FROM `{$table}` LIKE ?', $source);
        $this->assertStringNotContainsString('SHOW INDEX FROM `{$table}` WHERE `Key_name` = ?', $source);
    }

    public function testMigrationCreatesSessionsOwnerRoleAndUniqueMfaCredentials(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE `user` (`id` INTEGER PRIMARY KEY, `is_admin` INTEGER NOT NULL DEFAULT 0, `is_banned` INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec('CREATE TABLE `mfa_devices` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `userid` INTEGER NOT NULL, `rawid` VARCHAR(255), `type` VARCHAR(50))');
        $pdo->exec('INSERT INTO `user` (`id`, `is_admin`) VALUES (1, 1), (2, 1), (3, 0)');
        $pdo->exec("INSERT INTO `mfa_devices` (`userid`, `rawid`, `type`) VALUES (1, 'TOTP', 'TOTP'), (1, 'TOTP', 'totp')");
        $migration = require dirname(__DIR__, 3)
            . '/db/migrations/2026072202-security-permission-hardening.php';

        $migration->apply($pdo);
        $migration->apply($pdo);

        $this->assertSame('owner', $pdo->query('SELECT `admin_role` FROM `user` WHERE `id` = 1')->fetchColumn());
        $this->assertSame('administrator', $pdo->query('SELECT `admin_role` FROM `user` WHERE `id` = 2')->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM `mfa_devices` WHERE `userid` = 1 AND `type` = 'totp'")->fetchColumn());
        $this->assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'client_sessions'")->fetchColumn());

        $pdo->exec("UPDATE `user` SET `admin_role` = 'administrator'");
        $pdo->exec("UPDATE `user` SET `admin_role` = 'owner' WHERE `id` = 2");
        $migration->apply($pdo);
        $this->assertSame('administrator', $pdo->query('SELECT `admin_role` FROM `user` WHERE `id` = 1')->fetchColumn());
        $this->assertSame('owner', $pdo->query('SELECT `admin_role` FROM `user` WHERE `id` = 2')->fetchColumn());

        $this->expectException(PDOException::class);
        $pdo->exec("INSERT INTO `mfa_devices` (`userid`, `rawid`, `type`) VALUES (1, 'TOTP', 'totp')");
    }
}
