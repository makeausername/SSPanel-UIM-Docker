<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PHPUnit\Framework\TestCase;

final class CronJobCheckpointsMigrationTest extends TestCase
{
    public function testMigrationCreatesCheckpointsIdempotentlyAndCanRevert(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
        $migration = require dirname(__DIR__, 3) . '/db/migrations/2026072106-add_cron_job_checkpoints.php';

        $migration->apply($pdo);
        $migration->apply($pdo);

        $this->assertSame(5, (int) $pdo->query(
            "SELECT COUNT(*) FROM `config` WHERE `class` = 'cron'"
        )->fetchColumn());

        $migration->revert($pdo);

        $this->assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM `config`')->fetchColumn());
    }
}
