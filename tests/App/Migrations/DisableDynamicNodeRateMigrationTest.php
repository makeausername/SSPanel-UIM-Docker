<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Services\FixedNodeTrafficRatePolicy;
use PDO;
use PHPUnit\Framework\TestCase;
use function json_decode;

class DisableDynamicNodeRateMigrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('
            CREATE TABLE node (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                traffic_rate REAL NOT NULL,
                is_dynamic_rate INTEGER NOT NULL,
                dynamic_rate_type INTEGER NOT NULL,
                dynamic_rate_config TEXT NOT NULL
            )
        ');
        $this->pdo->exec("
            INSERT INTO node VALUES
                (1, 'XNode', 2, 1, 1, '{\"max_rate\":4}'),
                (2, 'Legacy', 1.5, 1, 0, '{\"min_rate\":0.5}')
        ");
    }

    public function testMigrationDisablesDynamicRateWithoutChangingFixedRates(): void
    {
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072104-disable_dynamic_node_rate.php';

        $migration->apply($this->pdo);
        $migration->apply($this->pdo);

        $this->assertFixedRate($this->findNode(1), 2.0, 'XNode');
        $this->assertFixedRate($this->findNode(2), 1.5, 'Legacy');
    }

    private function assertFixedRate(array $node, float $trafficRate, string $name): void
    {
        $this->assertSame($name, $node['name']);
        $this->assertSame($trafficRate, (float) $node['traffic_rate']);
        $this->assertSame(0, (int) $node['is_dynamic_rate']);
        $this->assertSame(0, (int) $node['dynamic_rate_type']);
        $this->assertSame(
            FixedNodeTrafficRatePolicy::compatibilityConfig($trafficRate),
            json_decode($node['dynamic_rate_config'], true, 512, JSON_THROW_ON_ERROR)
        );
    }

    private function findNode(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM node WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);

        return $row;
    }
}
