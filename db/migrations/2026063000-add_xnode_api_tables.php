<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        DB::getPdo()->exec("
            CREATE TABLE IF NOT EXISTS `node_profiles` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `node_id` bigint(20) unsigned NOT NULL,
                `profile_json` text DEFAULT NULL,
                `version` int(11) unsigned NOT NULL DEFAULT 1,
                `created_at` int(11) unsigned NOT NULL,
                `updated_at` int(11) unsigned DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `node_id` (`node_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `node_runtimes` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `node_id` bigint(20) unsigned NOT NULL,
                `agent_version` varchar(64) DEFAULT NULL,
                `core_version` varchar(64) DEFAULT NULL,
                `state` varchar(32) DEFAULT NULL,
                `public_key` varchar(255) DEFAULT NULL,
                `short_ids_json` text DEFAULT NULL,
                `capabilities_json` text DEFAULT NULL,
                `config_hash` varchar(128) DEFAULT NULL,
                `install_fingerprint` varchar(128) DEFAULT NULL,
                `last_seen` int(11) unsigned DEFAULT NULL,
                `last_error` text DEFAULT NULL,
                `created_at` int(11) unsigned NOT NULL,
                `updated_at` int(11) unsigned DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `node_id` (`node_id`),
                KEY `last_seen` (`last_seen`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `node_tokens` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `node_id` bigint(20) unsigned NOT NULL,
                `token_hash` varchar(255) NOT NULL,
                `token_type` varchar(32) NOT NULL DEFAULT 'node',
                `name` varchar(64) DEFAULT NULL,
                `last_used_at` int(11) unsigned DEFAULT NULL,
                `expires_at` int(11) unsigned DEFAULT NULL,
                `used_at` int(11) unsigned DEFAULT NULL,
                `revoked_at` int(11) unsigned DEFAULT NULL,
                `created_at` int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `token_hash` (`token_hash`),
                KEY `node_id` (`node_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        return 2026063000;
    }

    public function down(): int
    {
        DB::getPdo()->exec('
            DROP TABLE IF EXISTS `node_tokens`;
            DROP TABLE IF EXISTS `node_runtimes`;
            DROP TABLE IF EXISTS `node_profiles`;
        ');

        return 2025073100;
    }
};
