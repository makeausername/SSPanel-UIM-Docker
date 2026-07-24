<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PHPUnit\Framework\TestCase;

final class NodeCountryCodeMigrationTest extends TestCase
{
    public function testMigrationAddsCountryCodeWithSafeDefaultIdempotently(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE `node` (`id` INTEGER PRIMARY KEY, `name` TEXT NOT NULL)');
        $pdo->exec("INSERT INTO `node` VALUES (1, 'Singapore SG-A')");
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072402-add_node_country_code.php';

        $migration->apply($pdo);
        $migration->apply($pdo);

        $columns = $pdo->query('PRAGMA table_info(`node`)')->fetchAll(PDO::FETCH_ASSOC);
        $countryColumns = array_values(array_filter(
            $columns,
            static fn (array $column): bool => $column['name'] === 'country_code'
        ));
        self::assertCount(1, $countryColumns);
        self::assertSame('', $pdo->query(
            'SELECT `country_code` FROM `node` WHERE `id` = 1'
        )->fetchColumn());

        $pdo->exec("UPDATE `node` SET `country_code` = 'SG' WHERE `id` = 1");
        self::assertSame('SG', $pdo->query(
            'SELECT `country_code` FROM `node` WHERE `id` = 1'
        )->fetchColumn());
    }
}
