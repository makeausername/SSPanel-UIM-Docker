<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;
use App\Services\FixedNodeTrafficRatePolicy;

return new class() implements MigrationInterface {
    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072104;
    }

    public function down(): int
    {
        // Previous dynamic-rate settings cannot be reconstructed safely.
        return 2026072103;
    }

    public function apply(\PDO $pdo): void
    {
        $nodes = $pdo->query('SELECT `id`, `traffic_rate` FROM `node`')->fetchAll(\PDO::FETCH_ASSOC);
        $statement = $pdo->prepare(
            'UPDATE `node`
             SET `is_dynamic_rate` = ?, `dynamic_rate_type` = ?, `dynamic_rate_config` = ?
             WHERE `id` = ?'
        );

        foreach ($nodes as $node) {
            $values = FixedNodeTrafficRatePolicy::databaseValues((float) $node['traffic_rate']);
            $statement->execute([
                $values['is_dynamic_rate'],
                $values['dynamic_rate_type'],
                $values['dynamic_rate_config'],
                $node['id'],
            ]);
        }
    }
};
