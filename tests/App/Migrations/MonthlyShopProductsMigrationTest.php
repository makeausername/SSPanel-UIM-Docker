<?php

declare(strict_types=1);

namespace App\Migrations;

use App\Services\MonthlyPlanService;
use PDO;
use PHPUnit\Framework\TestCase;
use function json_decode;

class MonthlyShopProductsMigrationTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec('
            CREATE TABLE product (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                price DECIMAL(12, 2) NOT NULL,
                content TEXT NOT NULL,
                "limit" TEXT NOT NULL,
                status INTEGER NOT NULL,
                create_time INTEGER NOT NULL,
                update_time INTEGER NOT NULL,
                sale_count INTEGER NOT NULL,
                stock INTEGER NOT NULL
            )
        ');
        $this->pdo->exec("
            INSERT INTO product
                (type, name, price, content, \"limit\", status, create_time, update_time, sale_count, stock)
            VALUES ('bandwidth', 'Existing custom product', 5, '{}', '{}', 1, 1, 1, 0, -1)
        ");
    }

    public function testMigrationCreatesExactBilingualPlansAndMonthlyAddonsIdempotently(): void
    {
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072100-seed_monthly_shop_products.php';

        $migration->apply($this->pdo);
        $migration->apply($this->pdo);

        $this->assertSame(12, (int) $this->pdo->query('SELECT COUNT(*) FROM product')->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query(
            "SELECT COUNT(*) FROM product WHERE name = 'Existing custom product'"
        )->fetchColumn());

        $expectedPlans = [
            'Mini / 迷你套餐' => [100, 300.0],
            'Lite / 轻量套餐' => [300, 450.0],
            'Basic / 基础套餐' => [500, 600.0],
            'Standard / 标准套餐' => [1000, 900.0],
            'Pro / 专业套餐' => [1500, 1200.0],
            'Ultra / 超级套餐' => [2100, 1500.0],
        ];

        foreach ($expectedPlans as $name => [$bandwidth, $price]) {
            $row = $this->findProduct($name);
            $content = json_decode($row['content'], true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('tabp', $row['type']);
            $this->assertSame($price, (float) $row['price']);
            $this->assertSame(365, $content['time']);
            $this->assertSame($bandwidth, $content['bandwidth']);
            $this->assertSame(MonthlyPlanService::ALL_NODES_CLASS, $content['class']);
            $this->assertSame($bandwidth, $content['auto_reset_bandwidth']);
            $this->assertSame(1, $content['auto_reset_day']);
            $this->assertTrue($content['monthly_plan']);
            $this->assertSame(0, $content['node_group']);
            $this->assertSame('0', $content['speed_limit']);
            $this->assertSame('0', $content['ip_limit']);
        }

        foreach ([1, 10, 50, 100, 500] as $bandwidth) {
            $row = $this->findProduct($bandwidth . ' GB 当月加油包 / Current-month Add-on');
            $content = json_decode($row['content'], true, 512, JSON_THROW_ON_ERROR);

            $this->assertSame('bandwidth', $row['type']);
            $this->assertSame((float) $bandwidth, (float) $row['price']);
            $this->assertSame($bandwidth, $content['bandwidth']);
            $this->assertTrue($content['current_month_only']);
        }
    }

    public function testDownDisablesOnlyManagedProducts(): void
    {
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072100-seed_monthly_shop_products.php';
        $migration->apply($this->pdo);

        $migration->revert($this->pdo);

        $this->assertSame(11, (int) $this->pdo->query(
            'SELECT COUNT(*) FROM product WHERE status = 0'
        )->fetchColumn());
        $this->assertSame(1, (int) $this->pdo->query(
            "SELECT status FROM product WHERE name = 'Existing custom product'"
        )->fetchColumn());
    }

    private function findProduct(string $name): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM product WHERE name = ?');
        $statement->execute([$name]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);

        return $row;
    }
}
