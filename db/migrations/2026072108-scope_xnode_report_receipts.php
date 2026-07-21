<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072108;
    }

    public function down(): int
    {
        $this->revert(DB::getPdo());

        return 2026072107;
    }

    public function apply(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($this->hasIndex($pdo, $driver, 'report_id_unique')) {
            $driver === 'sqlite'
                ? $pdo->exec('DROP INDEX `report_id_unique`')
                : $pdo->exec('ALTER TABLE `node_report_receipts` DROP INDEX `report_id_unique`');
        }

        if (! $this->hasIndex($pdo, $driver, 'node_type_report_unique')) {
            $pdo->exec(
                'CREATE UNIQUE INDEX `node_type_report_unique`
                 ON `node_report_receipts` (`node_id`, `report_type`, `report_id`)'
            );
        }
    }

    public function revert(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($this->hasIndex($pdo, $driver, 'node_type_report_unique')) {
            $driver === 'sqlite'
                ? $pdo->exec('DROP INDEX `node_type_report_unique`')
                : $pdo->exec('ALTER TABLE `node_report_receipts` DROP INDEX `node_type_report_unique`');
        }

        if (! $this->hasIndex($pdo, $driver, 'report_id_unique')) {
            $pdo->exec('CREATE UNIQUE INDEX `report_id_unique` ON `node_report_receipts` (`report_id`)');
        }
    }

    private function hasIndex(\PDO $pdo, string $driver, string $index): bool
    {
        if ($driver === 'sqlite') {
            $indexes = $pdo->query("PRAGMA index_list('node_report_receipts')")->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($indexes as $candidate) {
                if (($candidate['name'] ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare('SHOW INDEX FROM `node_report_receipts` WHERE `Key_name` = ?');
        $statement->execute([$index]);

        return $statement->fetchColumn() !== false;
    }
};
