<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    private const INDEX = 'node_profiles_node_id_unique';

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072203;
    }

    public function down(): int
    {
        $pdo = DB::getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($this->hasIndex($pdo, $driver)) {
            if ($driver === 'sqlite') {
                $pdo->exec('DROP INDEX `' . self::INDEX . '`');
            } else {
                $pdo->exec('ALTER TABLE `node_profiles` DROP INDEX `' . self::INDEX . '`');
            }
        }

        return 2026072202;
    }

    public function apply(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (! $this->hasTable($pdo, $driver) || $this->hasIndex($pdo, $driver)) {
            return;
        }

        $duplicates = $pdo->query(
            'SELECT `node_id`, MAX(`id`) AS `keep_id` FROM `node_profiles` GROUP BY `node_id` HAVING COUNT(*) > 1'
        )->fetchAll(\PDO::FETCH_ASSOC);
        $delete = $pdo->prepare('DELETE FROM `node_profiles` WHERE `node_id` = ? AND `id` != ?');
        foreach ($duplicates as $duplicate) {
            $delete->execute([(int) $duplicate['node_id'], (int) $duplicate['keep_id']]);
        }

        $pdo->exec(
            'CREATE UNIQUE INDEX `' . self::INDEX . '` ON `node_profiles` (`node_id`)'
        );
    }

    private function hasTable(\PDO $pdo, string $driver): bool
    {
        if ($driver === 'sqlite') {
            $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'node_profiles'");
            $statement->execute();

            return $statement->fetchColumn() !== false;
        }

        $statement = $pdo->prepare('SHOW TABLES LIKE ?');
        $statement->execute(['node_profiles']);

        return $statement->fetchColumn() !== false;
    }

    private function hasIndex(\PDO $pdo, string $driver): bool
    {
        if ($driver === 'sqlite') {
            foreach ($pdo->query('PRAGMA index_list(`node_profiles`)')->fetchAll(\PDO::FETCH_ASSOC) as $index) {
                if (($index['name'] ?? null) === self::INDEX) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare('SHOW INDEX FROM `node_profiles` WHERE `Key_name` = ?');
        $statement->execute([self::INDEX]);

        return $statement->fetchColumn() !== false;
    }
};
