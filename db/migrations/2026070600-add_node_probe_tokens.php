<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        DB::getPdo()->exec("
            CREATE TABLE IF NOT EXISTS `node_probe_tokens` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `name` varchar(128) NOT NULL,
                `token_hash` varchar(128) NOT NULL,
                `probe_region` varchar(64) NOT NULL,
                `probe_provider` varchar(64) DEFAULT NULL,
                `probe_location` varchar(128) DEFAULT NULL,
                `allowed_node_ids_json` text DEFAULT NULL,
                `is_enabled` tinyint(1) NOT NULL DEFAULT 1,
                `expires_at` int(11) unsigned DEFAULT NULL,
                `last_used_at` int(11) unsigned DEFAULT NULL,
                `revoked_at` int(11) unsigned DEFAULT NULL,
                `created_at` int(11) unsigned NOT NULL,
                `updated_at` int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `token_hash_unique` (`token_hash`),
                KEY `enabled_region` (`is_enabled`, `probe_region`),
                KEY `expires_at` (`expires_at`),
                KEY `revoked_at` (`revoked_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        return 2026070600;
    }

    public function down(): int
    {
        DB::getPdo()->exec('DROP TABLE IF EXISTS `node_probe_tokens`;');

        return 2026070500;
    }
};
