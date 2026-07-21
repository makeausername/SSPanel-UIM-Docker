<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;
use App\Services\XNodeNodePolicy;

return new class() implements MigrationInterface {
    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072101;
    }

    public function down(): int
    {
        // Per-node values cannot be reconstructed safely after normalization.
        return 2026072100;
    }

    public function apply(\PDO $pdo): void
    {
        $values = XNodeNodePolicy::databaseValues();
        $statement = $pdo->prepare('
            UPDATE `node`
            SET `traffic_rate` = ?,
                `is_dynamic_rate` = ?,
                `dynamic_rate_type` = ?,
                `dynamic_rate_config` = ?,
                `node_class` = ?,
                `node_group` = ?,
                `node_speedlimit` = ?,
                `node_bandwidth_limit` = ?,
                `bandwidthlimit_resetday` = ?
            WHERE `sort` = ?
        ');
        $statement->execute([
            $values['traffic_rate'],
            $values['is_dynamic_rate'],
            $values['dynamic_rate_type'],
            $values['dynamic_rate_config'],
            $values['node_class'],
            $values['node_group'],
            $values['node_speedlimit'],
            $values['node_bandwidth_limit'],
            $values['bandwidthlimit_resetday'],
            XNodeNodePolicy::SORT,
        ]);
    }
};
