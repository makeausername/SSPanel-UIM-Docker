<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;
use App\Services\MonthlyPlanService;
use function json_encode;
use function time;

return new class() implements MigrationInterface {
    private const MANAGED_BY = 'eziplc_monthly_shop_v1';

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072100;
    }

    public function down(): int
    {
        $this->revert(DB::getPdo());

        return 2026071700;
    }

    public function apply(\PDO $pdo): void
    {
        $now = time();
        $find = $pdo->prepare(
            'SELECT `id`, `create_time` FROM `product` WHERE `name` = ? LIMIT 1'
        );
        $insert = $pdo->prepare('
            INSERT INTO `product`
                (`type`, `name`, `price`, `content`, `limit`, `status`, `create_time`, `update_time`, `sale_count`, `stock`)
            VALUES (?, ?, ?, ?, ?, 1, ?, ?, 0, -1)
        ');
        $update = $pdo->prepare('
            UPDATE `product`
            SET `type` = ?, `price` = ?, `content` = ?, `limit` = ?, `status` = 1,
                `update_time` = ?, `stock` = -1
            WHERE `id` = ?
        ');

        foreach ($this->products() as $product) {
            $find->execute([$product['name']]);
            $existing = $find->fetch(\PDO::FETCH_ASSOC);
            $content = json_encode($product['content'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $limit = json_encode($product['limit'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            if ($existing === false) {
                $insert->execute([
                    $product['type'],
                    $product['name'],
                    $product['price'],
                    $content,
                    $limit,
                    $now,
                    $now,
                ]);

                continue;
            }

            $update->execute([
                $product['type'],
                $product['price'],
                $content,
                $limit,
                $now,
                (int) $existing['id'],
            ]);
        }
    }

    public function revert(\PDO $pdo): void
    {
        $disable = $pdo->prepare('UPDATE `product` SET `status` = 0, `update_time` = ? WHERE `name` = ?');

        foreach ($this->products() as $product) {
            $disable->execute([time(), $product['name']]);
        }
    }

    public function products(): array
    {
        $plans = [
            ['mini', 'Mini / 迷你套餐', 100, '300.00', false],
            ['lite', 'Lite / 轻量套餐', 300, '450.00', false],
            ['basic', 'Basic / 基础套餐', 500, '600.00', false],
            ['standard', 'Standard / 标准套餐', 1000, '900.00', false],
            ['pro', 'Pro / 专业套餐', 1500, '1200.00', false],
            ['ultra', 'Ultra / 超级套餐', 2100, '1500.00', false],
            [
                'unlimited',
                'Unlimited / 无限套餐',
                MonthlyPlanService::UNLIMITED_BANDWIDTH_GB,
                '1800.00',
                true,
            ],
        ];
        $products = [];

        foreach ($plans as [$sku, $name, $bandwidth, $price, $unlimited]) {
            $products[] = [
                'type' => 'tabp',
                'name' => $name,
                'price' => $price,
                'content' => [
                    'time' => 365,
                    'bandwidth' => $bandwidth,
                    'class' => MonthlyPlanService::ALL_NODES_CLASS,
                    'class_time' => 365,
                    'node_group' => 0,
                    'speed_limit' => '0',
                    'ip_limit' => '0',
                    'monthly_plan' => true,
                    'billing_cycle' => 'annual',
                    'auto_reset_day' => MonthlyPlanService::RESET_DAY,
                    'auto_reset_bandwidth' => $bandwidth,
                    'unlimited_bandwidth' => $unlimited,
                    'managed_by' => self::MANAGED_BY,
                    'sku' => $sku,
                ],
                'limit' => $this->defaultLimit(),
            ];
        }

        foreach ([1, 10, 50, 100, 500] as $bandwidth) {
            $products[] = [
                'type' => 'bandwidth',
                'name' => $bandwidth . ' GB 当月加油包 / Current-month Add-on',
                'price' => $bandwidth . '.00',
                'content' => [
                    'bandwidth' => $bandwidth,
                    'current_month_only' => true,
                    'managed_by' => self::MANAGED_BY,
                    'sku' => 'addon-' . $bandwidth . 'gb',
                ],
                'limit' => $this->defaultLimit(),
            ];
        }

        return $products;
    }

    private function defaultLimit(): array
    {
        return [
            'class_required' => '',
            'node_group_required' => '',
            'new_user_required' => 0,
        ];
    }
};
