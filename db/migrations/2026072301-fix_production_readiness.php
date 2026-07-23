<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
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

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072301;
    }

    public function down(): int
    {
        $this->revert(DB::getPdo());

        return 2026072300;
    }

    public function apply(\PDO $pdo): void
    {
        $this->inheritLegacyDailyCheckpoint($pdo);
        $this->ensurePayPalWebhookSetting($pdo);
    }

    public function revert(\PDO $pdo): void
    {
        $delete = $pdo->prepare('DELETE FROM `config` WHERE `item` = ?');
        $delete->execute(['paypal_webhook_id']);

        // Inherited daily checkpoints represent completed work and must not be reset.
    }

    private function inheritLegacyDailyCheckpoint(\PDO $pdo): void
    {
        $select = $pdo->prepare('SELECT `value` FROM `config` WHERE `item` = ? LIMIT 1');
        $select->execute(['last_daily_job_time']);
        $legacyCheckpoint = (int) ($select->fetchColumn() ?: 0);

        if ($legacyCheckpoint <= 0) {
            return;
        }

        $update = $pdo->prepare(
            'UPDATE `config` SET `value` = ? WHERE `item` = ? AND `value` = ?'
        );

        foreach (self::DAILY_CHECKPOINTS as $item) {
            $update->execute([(string) $legacyCheckpoint, $item, '0']);
        }
    }

    private function ensurePayPalWebhookSetting(\PDO $pdo): void
    {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM `config` WHERE `item` = ?');
        $exists->execute(['paypal_webhook_id']);

        if ((int) $exists->fetchColumn() > 0) {
            return;
        }

        $insert = $pdo->prepare(
            'INSERT INTO `config` (`item`, `value`, `class`, `is_public`, `type`, `default`, `mark`)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            'paypal_webhook_id',
            '',
            'billing',
            0,
            'string',
            '',
            'PayPal Webhook ID',
        ]);
    }
};
