<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026071700;
    }

    public function down(): int
    {
        $this->revert(DB::getPdo());

        return 2026070500;
    }

    public function apply(\PDO $pdo): void
    {
        $this->removeDuplicateRuntimeRows($pdo);

        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $pdo->exec('ALTER TABLE `node_runtimes` ADD COLUMN `reality_hash` varchar(64) DEFAULT NULL;');

        if ($driver === 'sqlite') {
            $pdo->exec('DROP INDEX `node_id`;');
            $pdo->exec('CREATE UNIQUE INDEX `node_id_unique` ON `node_runtimes` (`node_id`);');

            return;
        }

        $pdo->exec('
            ALTER TABLE `node_runtimes`
                DROP INDEX `node_id`,
                ADD UNIQUE KEY `node_id_unique` (`node_id`);
        ');
    }

    public function revert(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $pdo->exec('DROP INDEX `node_id_unique`;');
            $pdo->exec('CREATE INDEX `node_id` ON `node_runtimes` (`node_id`);');
            $pdo->exec('ALTER TABLE `node_runtimes` DROP COLUMN `reality_hash`;');

            return;
        }

        $pdo->exec('
            ALTER TABLE `node_runtimes`
                DROP INDEX `node_id_unique`,
                ADD KEY `node_id` (`node_id`),
                DROP COLUMN `reality_hash`;
        ');
    }

    public function removeDuplicateRuntimeRows(\PDO $pdo): void
    {
        $ownsTransaction = ! $pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $rows = $pdo->query('
                SELECT `id`, `node_id`, `updated_at`
                FROM `node_runtimes`
                ORDER BY `node_id` ASC, `updated_at` DESC, `id` DESC
            ')->fetchAll(\PDO::FETCH_ASSOC);
            $delete = $pdo->prepare('DELETE FROM `node_runtimes` WHERE `id` = ?');
            $lastNodeId = null;

            foreach ($rows as $row) {
                $nodeId = (string) $row['node_id'];
                if ($lastNodeId !== $nodeId) {
                    $lastNodeId = $nodeId;
                    continue;
                }

                $delete->execute([(int) $row['id']]);
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }
};
