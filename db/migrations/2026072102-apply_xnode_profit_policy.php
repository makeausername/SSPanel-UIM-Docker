<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;
use App\Services\XNodeNodePolicy;
use function is_array;
use function json_decode;
use function time;

return new class() implements MigrationInterface {
    private const MANAGED_BY = 'eziplc_monthly_shop_v1';

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072102;
    }

    public function down(): int
    {
        // Per-node billing values and disabled product state cannot be reconstructed safely.
        return 2026072101;
    }

    public function apply(\PDO $pdo): void
    {
        $this->normalizeNodes($pdo);
        $this->disableUnlimitedPlan($pdo);
    }

    private function normalizeNodes(\PDO $pdo): void
    {
        $nodes = $pdo->prepare(
            'SELECT `id`, `name`, `custom_config` FROM `node` WHERE `sort` = ?'
        );
        $nodes->execute([XNodeNodePolicy::SORT]);
        $update = $pdo->prepare('
            UPDATE `node`
            SET `traffic_rate` = ?,
                `is_dynamic_rate` = ?,
                `dynamic_rate_type` = ?,
                `dynamic_rate_config` = ?,
                `node_class` = ?,
                `node_group` = ?,
                `node_speedlimit` = ?,
                `node_bandwidth_limit` = ?,
                `bandwidthlimit_resetday` = ?,
                `custom_config` = ?
            WHERE `id` = ?
        ');

        while ($node = $nodes->fetch(\PDO::FETCH_ASSOC)) {
            $customConfig = (string) ($node['custom_config'] ?? '{}');
            $profile = XNodeNodePolicy::resolveProfile(
                XNodeNodePolicy::profileFromCustomConfig($customConfig),
                (string) ($node['name'] ?? '')
            );
            $values = XNodeNodePolicy::databaseValues($profile);
            $update->execute([
                $values['traffic_rate'],
                $values['is_dynamic_rate'],
                $values['dynamic_rate_type'],
                $values['dynamic_rate_config'],
                $values['node_class'],
                $values['node_group'],
                $values['node_speedlimit'],
                $values['node_bandwidth_limit'],
                $values['bandwidthlimit_resetday'],
                XNodeNodePolicy::customConfigWithProfile($customConfig, $profile),
                (int) $node['id'],
            ]);
        }
    }

    private function disableUnlimitedPlan(\PDO $pdo): void
    {
        $products = $pdo->query('SELECT `id`, `content` FROM `product`');
        $disable = $pdo->prepare('UPDATE `product` SET `status` = 0, `update_time` = ? WHERE `id` = ?');

        while ($product = $products->fetch(\PDO::FETCH_ASSOC)) {
            $content = json_decode((string) ($product['content'] ?? ''), true);

            if (! is_array($content)
                || ($content['managed_by'] ?? null) !== self::MANAGED_BY
                || ($content['sku'] ?? null) !== 'unlimited'
            ) {
                continue;
            }

            $disable->execute([time(), (int) $product['id']]);
        }
    }
};
