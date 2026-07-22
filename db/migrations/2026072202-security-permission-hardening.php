<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        $pdo = DB::getPdo();
        $this->addAdminRole($pdo);
        $this->createClientSessions($pdo);
        $this->normalizeMfaDevices($pdo);

        return 2026072202;
    }

    public function down(): int
    {
        $pdo = DB::getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $pdo->exec('DROP TABLE IF EXISTS `client_sessions`');
        if ($this->hasTable($pdo, $driver, 'mfa_devices')
            && $this->hasIndex($pdo, $driver, 'mfa_devices', 'mfa_devices_user_rawid_unique')) {
            if ($driver === 'sqlite') {
                $pdo->exec('DROP INDEX `mfa_devices_user_rawid_unique`');
            } else {
                $pdo->exec('ALTER TABLE `mfa_devices` DROP INDEX `mfa_devices_user_rawid_unique`');
            }
        }

        if ($this->hasColumn($pdo, $driver, 'user', 'admin_role')) {
            $pdo->exec('ALTER TABLE `user` DROP COLUMN `admin_role`');
        }

        return 2026072201;
    }

    public function apply(\PDO $pdo): void
    {
        $this->addAdminRole($pdo);
        $this->createClientSessions($pdo);
        $this->normalizeMfaDevices($pdo);
    }

    private function addAdminRole(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (! $this->hasColumn($pdo, $driver, 'user', 'admin_role')) {
            $pdo->exec('ALTER TABLE `user` ADD COLUMN `admin_role` VARCHAR(32) DEFAULT NULL');
        }

        $pdo->exec("UPDATE `user` SET `admin_role` = 'administrator' WHERE `is_admin` = 1 AND (`admin_role` IS NULL OR `admin_role` = '')");
        $ownerCount = (int) $pdo->query(
            "SELECT COUNT(*) FROM `user`
             WHERE `is_admin` = 1 AND `is_banned` = 0 AND `admin_role` = 'owner'"
        )->fetchColumn();
        $ownerId = $ownerCount === 0
            ? $pdo->query(
                'SELECT `id` FROM `user`
                 WHERE `is_admin` = 1 AND `is_banned` = 0
                 ORDER BY `id` ASC LIMIT 1'
            )->fetchColumn()
            : false;
        if ($ownerId !== false) {
            $statement = $pdo->prepare("UPDATE `user` SET `admin_role` = 'owner' WHERE `id` = ?");
            $statement->execute([(int) $ownerId]);
        }
    }

    private function createClientSessions(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($this->hasTable($pdo, $driver, 'client_sessions')) {
            return;
        }

        if ($driver === 'sqlite') {
            $pdo->exec(
                'CREATE TABLE `client_sessions` (
                    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
                    `user_id` INTEGER NOT NULL,
                    `token_hash` VARCHAR(64) NOT NULL UNIQUE,
                    `name` VARCHAR(64) NOT NULL,
                    `expires_at` INTEGER NOT NULL,
                    `last_used_at` INTEGER DEFAULT NULL,
                    `revoked_at` INTEGER DEFAULT NULL,
                    `created_at` INTEGER NOT NULL
                )'
            );
        } else {
            $pdo->exec(
                'CREATE TABLE `client_sessions` (
                    `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                    `user_id` BIGINT(20) UNSIGNED NOT NULL,
                    `token_hash` VARCHAR(64) NOT NULL,
                    `name` VARCHAR(64) NOT NULL,
                    `expires_at` INT(11) UNSIGNED NOT NULL,
                    `last_used_at` INT(11) UNSIGNED DEFAULT NULL,
                    `revoked_at` INT(11) UNSIGNED DEFAULT NULL,
                    `created_at` INT(11) UNSIGNED NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `client_sessions_token_unique` (`token_hash`),
                    KEY `client_sessions_user_active` (`user_id`, `revoked_at`, `expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        if ($driver === 'sqlite') {
            $pdo->exec('CREATE INDEX `client_sessions_user_active` ON `client_sessions` (`user_id`, `revoked_at`, `expires_at`)');
        }
    }

    private function normalizeMfaDevices(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (! $this->hasTable($pdo, $driver, 'mfa_devices')) {
            return;
        }

        $pdo->exec('UPDATE `mfa_devices` SET `type` = LOWER(`type`) WHERE `type` IS NOT NULL');
        $duplicates = $pdo->query(
            "SELECT `userid`, `rawid`, MIN(`id`) AS `keep_id`
             FROM `mfa_devices`
             WHERE `rawid` IS NOT NULL AND `rawid` != ''
             GROUP BY `userid`, `rawid`
             HAVING COUNT(*) > 1"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $delete = $pdo->prepare('DELETE FROM `mfa_devices` WHERE `userid` = ? AND `rawid` = ? AND `id` != ?');
        foreach ($duplicates as $duplicate) {
            $delete->execute([
                (int) $duplicate['userid'],
                (string) $duplicate['rawid'],
                (int) $duplicate['keep_id'],
            ]);
        }

        if (! $this->hasIndex($pdo, $driver, 'mfa_devices', 'mfa_devices_user_rawid_unique')) {
            $pdo->exec(
                'CREATE UNIQUE INDEX `mfa_devices_user_rawid_unique`
                 ON `mfa_devices` (`userid`, `rawid`)'
            );
        }
    }

    private function hasTable(\PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
            $statement->execute([$table]);

            return $statement->fetchColumn() !== false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function hasColumn(\PDO $pdo, string $driver, string $table, string $column): bool
    {
        if ($driver === 'sqlite') {
            $columns = $pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($columns as $entry) {
                if (($entry['name'] ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function hasIndex(\PDO $pdo, string $driver, string $table, string $index): bool
    {
        if ($driver === 'sqlite') {
            $indexes = $pdo->query("PRAGMA index_list(`{$table}`)")->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($indexes as $entry) {
                if (($entry['name'] ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $statement->execute([$table, $index]);

        return (int) $statement->fetchColumn() > 0;
    }
};
