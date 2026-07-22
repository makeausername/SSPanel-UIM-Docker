<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072201;
    }

    public function down(): int
    {
        $this->revert(DB::getPdo());

        return 2026072200;
    }

    public function apply(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if (! $this->hasTable($pdo, $driver, 'user_referral')) {
            $this->createReferralTable($pdo, $driver);
        }

        if (! $this->hasTable($pdo, $driver, 'invite_subscription_reward')) {
            $this->createRewardTable($pdo, $driver);
        }

        $this->backfillReferrals($pdo, $driver);
    }

    public function revert(\PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS `invite_subscription_reward`');
        $pdo->exec('DROP TABLE IF EXISTS `user_referral`');
    }

    private function createReferralTable(\PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE `user_referral` (
                    `invited_user_id` INTEGER NOT NULL PRIMARY KEY,
                    `inviter_user_id` INTEGER NOT NULL,
                    `invite_code` VARCHAR(255) NOT NULL DEFAULT \'\',
                    `create_time` INTEGER NOT NULL DEFAULT 0
                )'
            );
        } else {
            $pdo->exec(
                'CREATE TABLE `user_referral` (
                    `invited_user_id` BIGINT(20) UNSIGNED NOT NULL,
                    `inviter_user_id` BIGINT(20) UNSIGNED NOT NULL,
                    `invite_code` VARCHAR(255) NOT NULL DEFAULT \'\',
                    `create_time` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`invited_user_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        $pdo->exec(
            'CREATE INDEX `idx_user_referral_inviter`
             ON `user_referral` (`inviter_user_id`)'
        );
    }

    private function createRewardTable(\PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE `invite_subscription_reward` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `inviter_user_id` INTEGER NOT NULL,
                    `invited_user_id` INTEGER NOT NULL,
                    `qualifying_order_id` INTEGER NOT NULL,
                    `invoice_id` INTEGER NOT NULL,
                    `applied_order_id` INTEGER NOT NULL DEFAULT 0,
                    `product_sku` VARCHAR(32) NOT NULL,
                    `reward_days` INTEGER NOT NULL,
                    `status` VARCHAR(16) NOT NULL DEFAULT \'pending\',
                    `expiry_before` TEXT NULL,
                    `expiry_after` TEXT NULL,
                    `create_time` INTEGER NOT NULL DEFAULT 0,
                    `apply_time` INTEGER NOT NULL DEFAULT 0,
                    UNIQUE (`invited_user_id`),
                    UNIQUE (`qualifying_order_id`),
                    UNIQUE (`invoice_id`)
                )'
            );
        } else {
            $pdo->exec(
                'CREATE TABLE `invite_subscription_reward` (
                    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `inviter_user_id` BIGINT(20) UNSIGNED NOT NULL,
                    `invited_user_id` BIGINT(20) UNSIGNED NOT NULL,
                    `qualifying_order_id` BIGINT(20) UNSIGNED NOT NULL,
                    `invoice_id` BIGINT(20) UNSIGNED NOT NULL,
                    `applied_order_id` BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
                    `product_sku` VARCHAR(32) NOT NULL,
                    `reward_days` SMALLINT(5) UNSIGNED NOT NULL,
                    `status` VARCHAR(16) NOT NULL DEFAULT \'pending\',
                    `expiry_before` DATETIME NULL,
                    `expiry_after` DATETIME NULL,
                    `create_time` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                    `apply_time` INT(11) UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq_invite_reward_invited_user` (`invited_user_id`),
                    UNIQUE KEY `uniq_invite_reward_order` (`qualifying_order_id`),
                    UNIQUE KEY `uniq_invite_reward_invoice` (`invoice_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        $pdo->exec(
            'CREATE INDEX `idx_invite_reward_inviter_status`
             ON `invite_subscription_reward` (`inviter_user_id`, `status`, `id`)'
        );
        $pdo->exec(
            'CREATE INDEX `idx_invite_reward_applied_order`
             ON `invite_subscription_reward` (`applied_order_id`, `status`)'
        );
    }

    private function backfillReferrals(\PDO $pdo, string $driver): void
    {
        if (! $this->hasTable($pdo, $driver, 'user')) {
            return;
        }

        $sql = $driver === 'sqlite'
            ? 'INSERT OR IGNORE INTO `user_referral`
               (`invited_user_id`, `inviter_user_id`, `invite_code`, `create_time`)
               SELECT `id`, `ref_by`, \'\', ? FROM `user` WHERE `ref_by` > 0'
            : 'INSERT IGNORE INTO `user_referral`
               (`invited_user_id`, `inviter_user_id`, `invite_code`, `create_time`)
               SELECT `id`, `ref_by`, \'\', ? FROM `user` WHERE `ref_by` > 0';
        $statement = $pdo->prepare($sql);
        $statement->execute([time()]);
    }

    private function hasTable(\PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $statement = $pdo->prepare(
                "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?"
            );
            $statement->execute([$table]);

            return (int) $statement->fetchColumn() > 0;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }
};
