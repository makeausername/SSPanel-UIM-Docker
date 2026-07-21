<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Services\XNodeNodePolicy;
use PDO;
use PHPUnit\Framework\TestCase;
use function json_decode;
use function json_encode;

class XNodeProfitPolicyMigrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createNodeTable();
        $this->createProductTable();
        $this->seedNodes();
        $this->seedProducts();
    }

    public function testMigrationAppliesUniformRateAndDisablesOnlyManagedUnlimitedPlan(): void
    {
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072102-apply_xnode_profit_policy.php';

        $migration->apply($this->pdo);
        $migration->apply($this->pdo);

        $this->assertNodePolicy(1);
        $this->assertNodePolicy(2);
        $this->assertNodePolicy(3);

        $legacy = $this->findNode(4);
        $this->assertSame(2.0, (float) $legacy['traffic_rate']);
        $this->assertSame(654321, (int) $legacy['node_bandwidth']);

        $this->assertSame(0, $this->productStatus(1));
        $this->assertSame(1, $this->productStatus(2));
        $this->assertSame(1, $this->productStatus(3));
    }

    private function createNodeTable(): void
    {
        $this->pdo->exec('
            CREATE TABLE node (
                id INTEGER PRIMARY KEY,
                name TEXT NOT NULL,
                sort INTEGER NOT NULL,
                custom_config TEXT NOT NULL,
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
    }

    private function createProductTable(): void
    {
        $this->pdo->exec('
            CREATE TABLE product (
                id INTEGER PRIMARY KEY,
                content TEXT NOT NULL,
                status INTEGER NOT NULL,
                update_time INTEGER NOT NULL
            )
        ');
    }

    private function seedNodes(): void
    {
        $legacyProfile = '{"xnode":{"enabled":true,"billing_profile":"hkg_as3_pro_micro","profit_policy_version":2}}';
        $insert = $this->pdo->prepare(
            'INSERT INTO node VALUES (?, ?, ?, ?, 2, 1, 1, ?, 10, 9, 100, ?, 500, 15)'
        );
        $insert->execute([1, 'LAX.AS3.Pro.MICRO', 15, '{}', '{"max_rate":3}', 123456]);
        $insert->execute([2, 'HKG.AS3.Pro.MEDIUM', 15, '{}', '{"max_rate":3}', 223456]);
        $insert->execute([3, 'Custom Hong Kong', 15, $legacyProfile, '{"max_rate":3}', 323456]);
        $insert->execute([4, 'Legacy Trojan', 14, '{}', '{"max_rate":3}', 654321]);
    }

    private function seedProducts(): void
    {
        $managedUnlimited = json_encode([
            'managed_by' => 'eziplc_monthly_shop_v1',
            'sku' => 'unlimited',
        ], JSON_THROW_ON_ERROR);
        $customUnlimited = json_encode([
            'managed_by' => 'custom',
            'sku' => 'unlimited',
        ], JSON_THROW_ON_ERROR);
        $managedUltra = json_encode([
            'managed_by' => 'eziplc_monthly_shop_v1',
            'sku' => 'ultra',
        ], JSON_THROW_ON_ERROR);
        $insert = $this->pdo->prepare('INSERT INTO product VALUES (?, ?, 1, 1)');
        $insert->execute([1, $managedUnlimited]);
        $insert->execute([2, $customUnlimited]);
        $insert->execute([3, $managedUltra]);
    }

    private function assertNodePolicy(int $id): void
    {
        $node = $this->findNode($id);
        $config = json_decode($node['custom_config'], true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(2.0, (float) $node['traffic_rate']);
        $this->assertSame(0, (int) $node['is_dynamic_rate']);
        $this->assertSame(0, (int) $node['node_class']);
        $this->assertSame(0, (int) $node['node_group']);
        $this->assertSame(0, (int) $node['node_speedlimit']);
        $this->assertSame(0, (int) $node['node_bandwidth_limit']);
        $this->assertSame(1, (int) $node['bandwidthlimit_resetday']);
        $this->assertArrayNotHasKey('billing_profile', $config['xnode'] ?? []);
        $this->assertArrayNotHasKey('profit_policy_version', $config['xnode'] ?? []);
    }

    private function findNode(int $id): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM node WHERE id = ?');
        $statement->execute([$id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);

        return $row;
    }

    private function productStatus(int $id): int
    {
        $statement = $this->pdo->prepare('SELECT status FROM product WHERE id = ?');
        $statement->execute([$id]);

        return (int) $statement->fetchColumn();
    }
}
