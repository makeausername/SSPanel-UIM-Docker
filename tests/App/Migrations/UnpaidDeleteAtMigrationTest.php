<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PHPUnit\Framework\TestCase;

final class UnpaidDeleteAtMigrationTest extends TestCase
{
    public function testMigrationLeavesExistingUsersExemptAndIsIdempotent(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE `user` (`id` INTEGER PRIMARY KEY, `reg_date` TEXT NOT NULL)');
        $pdo->exec("INSERT INTO `user` (`id`, `reg_date`) VALUES (1, '2020-01-01 00:00:00')");
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072105-add_unpaid_delete_at.php';

        $migration->apply($pdo);
        $migration->apply($pdo);

        $row = $pdo->query('SELECT `unpaid_delete_at` FROM `user` WHERE `id` = 1')
            ->fetch(PDO::FETCH_ASSOC);
        $indexes = $pdo->query('PRAGMA index_list(`user`)')->fetchAll(PDO::FETCH_ASSOC);

        $this->assertNull($row['unpaid_delete_at']);
        $this->assertContains('idx_user_unpaid_delete_at', array_column($indexes, 'name'));
    }
}
