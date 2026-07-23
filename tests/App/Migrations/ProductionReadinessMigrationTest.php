<?php

declare(strict_types=1);

namespace App\Migrations;

use PDO;
use PHPUnit\Framework\TestCase;

final class ProductionReadinessMigrationTest extends TestCase
{
    private const DAILY_CHECKPOINTS = [
        'last_daily_reset_user_bandwidth_time',
        'last_daily_clean_db_time',
        'last_daily_reset_node_bandwidth_time',
        'last_daily_traffic_report_time',
        'last_daily_detect_inactive_user_time',
        'last_daily_remove_inactive_access_time',
        'last_daily_diary_notification_time',
        'last_daily_reset_today_bandwidth_time',
        'last_daily_job_notification_time',
    ];

    public function testMigrationInheritsLegacyDailyCompletionWithoutOverwritingNewProgress(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->createSchema($pdo);
        $this->insertConfig($pdo, 'last_daily_job_time', '1721700000', 'cron');

        foreach (self::DAILY_CHECKPOINTS as $item) {
            $this->insertConfig($pdo, $item, '0', 'cron');
        }
        $pdo->exec(
            "UPDATE `config` SET `value` = '1721701234'
             WHERE `item` = 'last_daily_clean_db_time'"
        );

        $migration = require dirname(__DIR__, 3)
            . '/db/migrations/2026072301-fix_production_readiness.php';
        $migration->apply($pdo);
        $migration->apply($pdo);

        foreach (self::DAILY_CHECKPOINTS as $item) {
            $expected = $item === 'last_daily_clean_db_time' ? '1721701234' : '1721700000';
            self::assertSame($expected, $this->configValue($pdo, $item));
        }

        self::assertSame('', $this->configValue($pdo, 'paypal_webhook_id'));
        self::assertSame(1, (int) $pdo->query(
            "SELECT COUNT(*) FROM `config` WHERE `item` = 'paypal_webhook_id'"
        )->fetchColumn());

        $migration->revert($pdo);
        self::assertSame(0, (int) $pdo->query(
            "SELECT COUNT(*) FROM `config` WHERE `item` = 'paypal_webhook_id'"
        )->fetchColumn());
        self::assertSame('1721700000', $this->configValue(
            $pdo,
            'last_daily_reset_user_bandwidth_time'
        ));
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
    }

    private function insertConfig(PDO $pdo, string $item, string $value, string $class): void
    {
        $insert = $pdo->prepare(
            'INSERT INTO `config` (`item`, `value`, `class`, `is_public`, `type`, `default`, `mark`)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$item, $value, $class, 0, 'int', '0', $item]);
    }

    private function configValue(PDO $pdo, string $item): string
    {
        $select = $pdo->prepare('SELECT `value` FROM `config` WHERE `item` = ?');
        $select->execute([$item]);

        return (string) $select->fetchColumn();
    }
}
