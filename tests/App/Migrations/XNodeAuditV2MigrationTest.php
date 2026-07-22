<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PHPUnit\Framework\TestCase;

final class XNodeAuditV2MigrationTest extends TestCase
{
    public function testMariaDbMetadataLookupsUsePreparableInformationSchemaQueries(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/db/migrations/2026072200-add_xnode_audit_v2.php'
        );

        $this->assertIsString($source);
        $this->assertStringContainsString('information_schema.TABLES', $source);
        $this->assertStringContainsString('information_schema.COLUMNS', $source);
        $this->assertStringContainsString('information_schema.STATISTICS', $source);
        $this->assertStringNotContainsString('SHOW TABLES LIKE ?', $source);
        $this->assertStringNotContainsString('SHOW COLUMNS FROM `{$table}` LIKE ?', $source);
        $this->assertStringNotContainsString('SHOW INDEX FROM `{$table}` WHERE `Key_name` = ?', $source);
    }

    public function testMigrationSeedsManagedRulesAndComplaintDomainsIdempotently(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE node_runtimes (id INTEGER PRIMARY KEY AUTOINCREMENT, node_id INTEGER NOT NULL)');

        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072200-add_xnode_audit_v2.php';
        $migration->apply($pdo);
        $migration->apply($pdo);

        $this->assertSame(4, (int) $pdo->query('SELECT COUNT(*) FROM xnode_audit_rules')->fetchColumn());
        $this->assertSame(35, (int) $pdo->query('SELECT COUNT(*) FROM xnode_audit_rule_patterns')->fetchColumn());
        $this->assertSame(32, (int) $pdo->query(
            "SELECT COUNT(*) FROM xnode_audit_rule_patterns p
             JOIN xnode_audit_rules r ON r.id = p.rule_id
             WHERE r.managed_key = 'user-complaint-domains-20260722'"
        )->fetchColumn());
        $this->assertSame(0, (int) $pdo->query(
            "SELECT COUNT(*) FROM xnode_audit_rule_patterns WHERE pattern LIKE 'www.%'"
        )->fetchColumn());

        $columns = $pdo->query('PRAGMA table_info(node_runtimes)')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertContains('audit_hash', array_column($columns, 'name'));
        $this->assertContains('audit_status', array_column($columns, 'name'));
    }
}
