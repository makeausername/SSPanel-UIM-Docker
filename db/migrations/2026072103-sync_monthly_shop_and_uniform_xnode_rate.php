<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072103;
    }

    public function down(): int
    {
        // Product and node values cannot be reconstructed safely after synchronization.
        return 2026072102;
    }

    public function apply(\PDO $pdo): void
    {
        $shopMigration = require __DIR__ . '/2026072100-seed_monthly_shop_products.php';
        $shopMigration->apply($pdo);

        $nodePolicyMigration = require __DIR__ . '/2026072102-apply_xnode_profit_policy.php';
        $nodePolicyMigration->apply($pdo);
    }
};
