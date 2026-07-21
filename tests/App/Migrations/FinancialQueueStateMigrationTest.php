<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PHPUnit\Framework\TestCase;

final class FinancialQueueStateMigrationTest extends TestCase
{
    public function testMigrationBackfillsPartialInvoiceAndQueueState(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);
        $pdo->exec(
            "INSERT INTO invoice (id, price, status, content)
             VALUES (1, 6.00, 'paid_gateway',
             '[{\"name\":\"Gateway partial payment\",\"price\":\"-4.00\"}]')"
        );
        $pdo->exec(
            "INSERT INTO email_queue (id, to_email, subject, template, array, time)
             VALUES (1, 'user@example.com', 'subject', 'template', '{}', 123)"
        );
        $pdo->exec(
            "INSERT INTO config (item, value, class, is_public, type, `default`, mark)
             VALUES ('cryptomus_api_key', 'secret', 'billing', 1, 'string', '', '')"
        );

        $migration = require dirname(__DIR__, 3)
            . '/db/migrations/2026072107-harden_financial_and_queue_state.php';
        $migration->apply($pdo);
        $migration->apply($pdo);

        $invoice = $pdo->query('SELECT * FROM invoice WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $queue = $pdo->query('SELECT * FROM email_queue WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('10', (string) (float) $invoice['original_price']);
        $this->assertSame('10', (string) (float) $invoice['paid_amount']);
        $this->assertSame('pending', $queue['status']);
        $this->assertSame(123, (int) $queue['next_attempt_at']);
        $this->assertSame(0, (int) $pdo->query(
            "SELECT is_public FROM config WHERE item = 'cryptomus_api_key'"
        )->fetchColumn());
    }

    private function createSchema(PDO $pdo): void
    {
        $pdo->exec('CREATE TABLE invoice (id INTEGER PRIMARY KEY, price DECIMAL(12,2), status TEXT, content TEXT)');
        $pdo->exec(
            'CREATE TABLE paylist (
                id INTEGER PRIMARY KEY, gateway TEXT, total DECIMAL(12,2), invoice_id INTEGER,
                tradeno TEXT, userid INTEGER, status INTEGER, datetime INTEGER
            )'
        );
        $pdo->exec(
            'CREATE TABLE email_queue (
                id INTEGER PRIMARY KEY, to_email TEXT, subject TEXT, template TEXT, array TEXT, time INTEGER
            )'
        );
        $pdo->exec(
            'CREATE TABLE config (
                item TEXT, value TEXT, class TEXT, is_public INTEGER, type TEXT, `default` TEXT, mark TEXT
            )'
        );
    }
}
