<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072402;
    }

    public function down(): int
    {
        $pdo = DB::getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($this->hasColumn($pdo, $driver)) {
            $pdo->exec('ALTER TABLE `node` DROP COLUMN `country_code`');
        }

        return 2026072401;
    }

    public function apply(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($this->hasColumn($pdo, $driver)) {
            return;
        }

        $definition = $driver === 'sqlite'
            ? "CHAR(2) NOT NULL DEFAULT ''"
            : "char(2) NOT NULL DEFAULT '' COMMENT 'ISO 3166-1 alpha-2 country or region code'";

        $pdo->exec('ALTER TABLE `node` ADD COLUMN `country_code` ' . $definition);
    }

    private function hasColumn(\PDO $pdo, string $driver): bool
    {
        if ($driver === 'sqlite') {
            foreach ($pdo->query('PRAGMA table_info(`node`)')->fetchAll(\PDO::FETCH_ASSOC) as $column) {
                if (($column['name'] ?? null) === 'country_code') {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute(['node', 'country_code']);

        return (int) $statement->fetchColumn() > 0;
    }
};
