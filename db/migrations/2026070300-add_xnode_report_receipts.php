<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        DB::getPdo()->exec("
            CREATE TABLE IF NOT EXISTS `node_report_receipts` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `node_id` bigint(20) unsigned NOT NULL,
                `report_id` varchar(128) NOT NULL,
                `report_type` varchar(32) NOT NULL,
                `period_start` int(11) unsigned DEFAULT NULL,
                `period_end` int(11) unsigned DEFAULT NULL,
                `created_at` int(11) unsigned NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `report_id_unique` (`report_id`),
                KEY `node_type_created` (`node_id`, `report_type`, `created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");

        return 2026070300;
    }

    public function down(): int
    {
        DB::getPdo()->exec('DROP TABLE IF EXISTS `node_report_receipts`;');

        return 2026063000;
    }
};
