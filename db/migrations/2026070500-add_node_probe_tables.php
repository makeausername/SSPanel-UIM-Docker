<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        DB::getPdo()->exec("
            CREATE TABLE IF NOT EXISTS `node_probe_results` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `node_id` bigint(20) unsigned NOT NULL,
                `probe_region` varchar(64) NOT NULL,
                `probe_provider` varchar(64) DEFAULT NULL,
                `probe_location` varchar(128) DEFAULT NULL,
                `probe_type` varchar(32) NOT NULL,
                `target_host` varchar(255) NOT NULL,
                `target_port` int(10) unsigned NOT NULL DEFAULT 443,
                `status` varchar(32) NOT NULL,
                `latency_ms` int(10) unsigned DEFAULT NULL,
                `error` varchar(512) DEFAULT NULL,
                `checked_at` int(11) unsigned NOT NULL,
                `created_at` int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                KEY `node_region_checked` (`node_id`, `probe_region`, `checked_at`),
                KEY `node_status_checked` (`node_id`, `status`, `checked_at`),
                KEY `checked_at` (`checked_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        DB::getPdo()->exec("
            CREATE TABLE IF NOT EXISTS `node_probe_states` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `node_id` bigint(20) unsigned NOT NULL,
                `status` varchar(32) NOT NULL,
                `previous_status` varchar(32) DEFAULT NULL,
                `probe_region` varchar(64) DEFAULT NULL,
                `probe_provider` varchar(64) DEFAULT NULL,
                `probe_location` varchar(128) DEFAULT NULL,
                `probe_type` varchar(32) DEFAULT NULL,
                `target_host` varchar(255) DEFAULT NULL,
                `target_port` int(10) unsigned DEFAULT 443,
                `latency_ms` int(10) unsigned DEFAULT NULL,
                `error` varchar(512) DEFAULT NULL,
                `last_checked_at` int(11) unsigned DEFAULT NULL,
                `last_changed_at` int(11) unsigned DEFAULT NULL,
                `last_notified_status` varchar(32) DEFAULT NULL,
                `last_notified_at` int(11) unsigned DEFAULT NULL,
                `created_at` int(11) unsigned NOT NULL,
                `updated_at` int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `node_id_unique` (`node_id`),
                KEY `status_checked` (`status`, `last_checked_at`),
                KEY `changed_at` (`last_changed_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        return 2026070500;
    }

    public function down(): int
    {
        DB::getPdo()->exec('DROP TABLE IF EXISTS `node_probe_states`;');
        DB::getPdo()->exec('DROP TABLE IF EXISTS `node_probe_results`;');

        return 2026070400;
    }
};
