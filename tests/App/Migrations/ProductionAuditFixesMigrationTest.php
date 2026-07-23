<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PHPUnit\Framework\TestCase;

final class ProductionAuditFixesMigrationTest extends TestCase
{
    public function testMigrationRepairsDiscountedInvoicesAndSeedsDailyCheckpointsIdempotently(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);

        $pdo->exec(
            "INSERT INTO `order` (`id`, `price`) VALUES (10, 8.00), (11, 10.00), (12, 8.00)"
        );
        $pdo->exec(
            "INSERT INTO `invoice`
                (`id`, `order_id`, `type`, `price`, `original_price`, `paid_amount`,
                 `refunded_amount`, `status`, `update_time`)
             VALUES
                (1, 10, 'product', 2.00, 10.00, 8.00, 0.00, 'partially_paid', 0),
                (2, 11, 'product', 6.00, 10.00, 4.00, 0.00, 'partially_paid', 0),
                (3, 12, 'product', 10.00, 10.00, 10.00, 9.00, 'paid_admin', 0)"
        );
        $pdo->exec(
            "INSERT INTO `paylist` (`invoice_id`, `status`) VALUES (1, 1)"
        );

        $migration = require dirname(__DIR__, 3)
            . '/db/migrations/2026072300-fix_production_audit_findings.php';
        $migration->apply($pdo);
        $migration->apply($pdo);

        $gatewayInvoice = $pdo->query('SELECT * FROM `invoice` WHERE `id` = 1')
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame('8', self::decimal($gatewayInvoice['original_price']));
        self::assertSame('8', self::decimal($gatewayInvoice['paid_amount']));
        self::assertSame('8', self::decimal($gatewayInvoice['price']));
        self::assertSame('paid_gateway', $gatewayInvoice['status']);

        $partialInvoice = $pdo->query('SELECT * FROM `invoice` WHERE `id` = 2')
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame('10', self::decimal($partialInvoice['original_price']));
        self::assertSame('4', self::decimal($partialInvoice['paid_amount']));
        self::assertSame('6', self::decimal($partialInvoice['price']));
        self::assertSame('partially_paid', $partialInvoice['status']);

        $adminInvoice = $pdo->query('SELECT * FROM `invoice` WHERE `id` = 3')
            ->fetch(PDO::FETCH_ASSOC);
        self::assertSame('8', self::decimal($adminInvoice['original_price']));
        self::assertSame('8', self::decimal($adminInvoice['paid_amount']));
        self::assertSame('8', self::decimal($adminInvoice['refunded_amount']));
        self::assertSame('paid_admin', $adminInvoice['status']);

        self::assertSame(9, (int) $pdo->query(
            "SELECT COUNT(*) FROM `config` WHERE `class` = 'cron'"
        )->fetchColumn());

        $migration->revert($pdo);
        self::assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM `config`')->fetchColumn());
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE `config` (
                `item` TEXT NOT NULL,
                `value` TEXT NOT NULL,
                `class` TEXT NOT NULL,
                `is_public` INTEGER NOT NULL,
                `type` TEXT NOT NULL,
                `default` TEXT NOT NULL,
                `mark` TEXT NOT NULL
            )'
        );
        $pdo->exec('CREATE TABLE `order` (`id` INTEGER PRIMARY KEY, `price` DECIMAL(12,2))');
        $pdo->exec(
            'CREATE TABLE `invoice` (
                `id` INTEGER PRIMARY KEY,
                `order_id` INTEGER NOT NULL,
                `type` TEXT NOT NULL,
                `price` DECIMAL(12,2) NOT NULL,
                `original_price` DECIMAL(12,2),
                `paid_amount` DECIMAL(12,2) NOT NULL,
                `refunded_amount` DECIMAL(12,2) NOT NULL,
                `status` TEXT NOT NULL,
                `update_time` INTEGER NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE `paylist` (
                `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                `invoice_id` INTEGER NOT NULL,
                `status` INTEGER NOT NULL
            )'
        );
    }

    private static function decimal(mixed $value): string
    {
        return (string) (float) $value;
    }
}
