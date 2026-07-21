<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    private const INDEX_NAME = 'idx_user_unpaid_delete_at';

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072105;
    }

    public function down(): int
    {
        $this->revert(DB::getPdo());

        return 2026072104;
    }

    public function apply(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if (! $this->hasColumn($pdo, $driver)) {
            $definition = $driver === 'sqlite'
                ? 'TEXT NULL'
                : 'DATETIME NULL AFTER `reg_date`';
            $pdo->exec('ALTER TABLE `user` ADD COLUMN `unpaid_delete_at` ' . $definition);
        }

        if (! $this->hasIndex($pdo, $driver)) {
            $pdo->exec(
                'CREATE INDEX `' . self::INDEX_NAME . '` ON `user` (`unpaid_delete_at`)'
            );
        }
    }

    public function revert(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($this->hasIndex($pdo, $driver)) {
            if ($driver === 'sqlite') {
                $pdo->exec('DROP INDEX `' . self::INDEX_NAME . '`');
            } else {
                $pdo->exec('ALTER TABLE `user` DROP INDEX `' . self::INDEX_NAME . '`');
            }
        }

        if ($this->hasColumn($pdo, $driver)) {
            $pdo->exec('ALTER TABLE `user` DROP COLUMN `unpaid_delete_at`');
        }
    }

    private function hasColumn(\PDO $pdo, string $driver): bool
    {
        if ($driver === 'sqlite') {
            $columns = $pdo->query('PRAGMA table_info(`user`)')->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($columns as $column) {
                if (($column['name'] ?? null) === 'unpaid_delete_at') {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'user'
               AND COLUMN_NAME = 'unpaid_delete_at'"
        );

        return (int) $statement->fetchColumn() > 0;
    }

    private function hasIndex(\PDO $pdo, string $driver): bool
    {
        if ($driver === 'sqlite') {
            $indexes = $pdo->query('PRAGMA index_list(`user`)')->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($indexes as $index) {
                if (($index['name'] ?? null) === self::INDEX_NAME) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->query(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'user'
               AND INDEX_NAME = '" . self::INDEX_NAME . "'"
        );

        return (int) $statement->fetchColumn() > 0;
    }
};
