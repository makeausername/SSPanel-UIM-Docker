<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class UserPortUniquenessMigrationTest extends TestCase
{
    public function testMigrationRepairsInvalidAndDuplicatePortsBeforeAddingUniqueIndex(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE `config` (`item` TEXT PRIMARY KEY, `value` TEXT)');
        $pdo->exec("INSERT INTO `config` VALUES ('min_port', '10000'), ('max_port', '10002')");
        $pdo->exec('CREATE TABLE `user` (`id` INTEGER PRIMARY KEY, `port` INTEGER NOT NULL)');
        $pdo->exec('INSERT INTO `user` VALUES (1, 10000), (2, 10000), (3, 0)');
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072400-enforce-unique-user-ports.php';

        $migration->apply($pdo);
        $migration->apply($pdo);

        $ports = $pdo->query('SELECT `port` FROM `user` ORDER BY `id`')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame([10000, 10001, 10002], $ports);
        $this->expectException(PDOException::class);
        $pdo->exec('INSERT INTO `user` VALUES (4, 10000)');
    }

    public function testMigrationFailsInsteadOfWritingPortZeroWhenPoolIsTooSmall(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE `config` (`item` TEXT PRIMARY KEY, `value` TEXT)');
        $pdo->exec("INSERT INTO `config` VALUES ('min_port', '10000'), ('max_port', '10000')");
        $pdo->exec('CREATE TABLE `user` (`id` INTEGER PRIMARY KEY, `port` INTEGER NOT NULL)');
        $pdo->exec('INSERT INTO `user` VALUES (1, 10000), (2, 0)');
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072400-enforce-unique-user-ports.php';

        $this->expectException(\RuntimeException::class);
        $migration->apply($pdo);
    }
}
