<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class NodeProfileUniquenessMigrationTest extends TestCase
{
    public function testMariaDbMetadataLookupsUsePreparableInformationSchemaQueries(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/db/migrations/2026072203-node-profile-uniqueness.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString('information_schema.TABLES', $source);
        $this->assertStringContainsString('information_schema.STATISTICS', $source);
        $this->assertStringNotContainsString('SHOW TABLES LIKE ?', $source);
        $this->assertStringNotContainsString('SHOW INDEX FROM `node_profiles` WHERE `Key_name` = ?', $source);
    }

    public function testMigrationDeduplicatesProfilesAndCreatesUniqueNodeIndex(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE `node_profiles` (`id` INTEGER PRIMARY KEY AUTOINCREMENT, `node_id` INTEGER NOT NULL)'
        );
        $pdo->exec('INSERT INTO `node_profiles` (`node_id`) VALUES (1), (1), (2)');
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072203-node-profile-uniqueness.php';

        $migration->apply($pdo);
        $migration->apply($pdo);

        $this->assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM `node_profiles`')->fetchColumn());
        $this->expectException(PDOException::class);
        $pdo->exec('INSERT INTO `node_profiles` (`node_id`) VALUES (1)');
    }
}
