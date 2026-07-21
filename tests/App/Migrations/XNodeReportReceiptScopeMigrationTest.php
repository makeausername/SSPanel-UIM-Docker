<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;

final class XNodeReportReceiptScopeMigrationTest extends TestCase
{
    public function testMigrationScopesReportIdsByNodeAndType(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE node_report_receipts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                node_id INTEGER NOT NULL,
                report_id TEXT NOT NULL,
                report_type TEXT NOT NULL
            )'
        );
        $pdo->exec('CREATE UNIQUE INDEX report_id_unique ON node_report_receipts (report_id)');

        $migration = require dirname(__DIR__, 3)
            . '/db/migrations/2026072108-scope_xnode_report_receipts.php';
        $migration->apply($pdo);
        $migration->apply($pdo);

        $pdo->exec("INSERT INTO node_report_receipts (node_id, report_id, report_type) VALUES (1, 'same', 'traffic')");
        $pdo->exec("INSERT INTO node_report_receipts (node_id, report_id, report_type) VALUES (2, 'same', 'traffic')");
        $pdo->exec("INSERT INTO node_report_receipts (node_id, report_id, report_type) VALUES (1, 'same', 'online')");

        $this->expectException(PDOException::class);
        $pdo->exec("INSERT INTO node_report_receipts (node_id, report_id, report_type) VALUES (1, 'same', 'traffic')");
    }
}
