<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class GiftCardUniquenessMigrationTest extends TestCase
{
    public function testMigrationRepairsDuplicateAndInvalidCodesThenAddsUniqueIndex(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE `gift_card` (`id` INTEGER PRIMARY KEY, `card` TEXT COLLATE NOCASE NOT NULL)');
        $pdo->exec("INSERT INTO `gift_card` VALUES (1, 'Same'), (2, 'same'), (3, '')");
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072401-enforce-unique-gift-cards.php';

        $migration->apply($pdo);
        $migration->apply($pdo);

        $codes = $pdo->query('SELECT `card` FROM `gift_card` ORDER BY `id`')->fetchAll(PDO::FETCH_COLUMN);
        $this->assertSame('Same', $codes[0]);
        $this->assertCount(3, array_unique(array_map('strtolower', $codes)));
        $this->assertFalse(in_array('', $codes, true));

        $this->expectException(PDOException::class);
        $pdo->exec("INSERT INTO `gift_card` VALUES (4, 'same')");
    }
}
