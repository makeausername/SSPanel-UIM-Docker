<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Services\XNodeNodePolicy;
use PDO;
use PHPUnit\Framework\TestCase;
use function json_decode;

class XNodeNodePolicyMigrationTest extends TestCase
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
                sort INTEGER NOT NULL,
                traffic_rate REAL NOT NULL,
                is_dynamic_rate INTEGER NOT NULL,
                dynamic_rate_type INTEGER NOT NULL,
                dynamic_rate_config TEXT NOT NULL,
                node_class INTEGER NOT NULL,
                node_group INTEGER NOT NULL,
                node_speedlimit INTEGER NOT NULL,
                node_bandwidth INTEGER NOT NULL,
                node_bandwidth_limit INTEGER NOT NULL,
                bandwidthlimit_resetday INTEGER NOT NULL
            )
        ');
        $this->pdo->exec("
            INSERT INTO node VALUES
                (1, 15, 2, 1, 1, '{\"max_rate\":3}', 10, 9, 100, 123456, 500, 15),
                (2, 14, 2, 1, 1, '{\"max_rate\":3}', 10, 9, 100, 654321, 500, 15)
        ");
    }

    public function testMigrationNormalizesOnlyExistingXNodeRowsIdempotently(): void
    {
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072101-normalize_xnode_node_policy.php';

        $migration->apply($this->pdo);
        $migration->apply($this->pdo);

        $xnode = $this->findNode(1);
        $legacy = $this->findNode(2);

        $this->assertSame(1.0, (float) $xnode['traffic_rate']);
        $this->assertSame(0, (int) $xnode['is_dynamic_rate']);
        $this->assertSame(0, (int) $xnode['dynamic_rate_type']);
        $this->assertSame(XNodeNodePolicy::dynamicRateConfig(), json_decode(
            $xnode['dynamic_rate_config'],
            true,
            512,
            JSON_THROW_ON_ERROR
        ));
        $this->assertSame(0, (int) $xnode['node_class']);
        $this->assertSame(0, (int) $xnode['node_group']);
        $this->assertSame(0, (int) $xnode['node_speedlimit']);
        $this->assertSame(0, (int) $xnode['node_bandwidth_limit']);
        $this->assertSame(1, (int) $xnode['bandwidthlimit_resetday']);
        $this->assertSame(123456, (int) $xnode['node_bandwidth']);

        $this->assertSame(2.0, (float) $legacy['traffic_rate']);
        $this->assertSame(1, (int) $legacy['is_dynamic_rate']);
        $this->assertSame(10, (int) $legacy['node_class']);
        $this->assertSame(654321, (int) $legacy['node_bandwidth']);
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
