<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

class XNodeRealityMetadataMigrationTest extends TestCase
{
    public function testMigrationKeepsNewestRuntimeAndEnforcesNodeIdUniqueness(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('
            CREATE TABLE node_runtimes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                node_id INTEGER NOT NULL,
                public_key VARCHAR(255) DEFAULT NULL,
                updated_at INTEGER DEFAULT NULL
            );
            CREATE INDEX node_id ON node_runtimes (node_id);
        ');
        $pdo->exec("
            INSERT INTO node_runtimes (id, node_id, public_key, updated_at) VALUES
                (1, 10, 'older-by-time', 100),
                (2, 10, 'newer-by-time', 200),
                (3, 20, 'older-by-id', 300),
                (4, 20, 'newer-by-id', 300),
                (5, 30, 'only-row', NULL);
        ");

        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026071700-add_xnode_reality_metadata.php';
        $migration->apply($pdo);

        $rows = $pdo->query('
            SELECT id, node_id, public_key
            FROM node_runtimes
            ORDER BY node_id
        ')->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame([
            ['id' => 2, 'node_id' => 10, 'public_key' => 'newer-by-time'],
            ['id' => 4, 'node_id' => 20, 'public_key' => 'newer-by-id'],
            ['id' => 5, 'node_id' => 30, 'public_key' => 'only-row'],
        ], array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'node_id' => (int) $row['node_id'],
            'public_key' => $row['public_key'],
        ], $rows));

        $columns = $pdo->query('PRAGMA table_info(node_runtimes)')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertContains('reality_hash', array_column($columns, 'name'));

        $this->expectException(PDOException::class);
        $pdo->exec("INSERT INTO node_runtimes (node_id, public_key) VALUES (10, 'duplicate');");
    }

    public function testDownRemovesRealityHashAndRestoresNonUniqueNodeIdIndex(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('
            CREATE TABLE node_runtimes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                node_id INTEGER NOT NULL,
                updated_at INTEGER DEFAULT NULL
            );
            CREATE INDEX node_id ON node_runtimes (node_id);
        ');
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026071700-add_xnode_reality_metadata.php';

        $migration->apply($pdo);
        $migration->revert($pdo);

        $columns = $pdo->query('PRAGMA table_info(node_runtimes)')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertNotContains('reality_hash', array_column($columns, 'name'));

        $pdo->exec('INSERT INTO node_runtimes (node_id) VALUES (10), (10);');
        $this->assertSame(2, (int) $pdo->query(
            'SELECT COUNT(*) FROM node_runtimes WHERE node_id = 10'
        )->fetchColumn());
    }
}
