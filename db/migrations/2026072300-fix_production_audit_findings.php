<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    private const DAILY_CHECKPOINTS = [
        'last_daily_reset_user_bandwidth_time' => 'Last successful daily user bandwidth reset',
        'last_daily_clean_db_time' => 'Last successful daily database cleanup',
        'last_daily_reset_node_bandwidth_time' => 'Last successful daily node bandwidth reset',
        'last_daily_traffic_report_time' => 'Last successful daily traffic report',
        'last_daily_detect_inactive_user_time' => 'Last successful daily inactive-user check',
        'last_daily_remove_inactive_access_time' => 'Last successful daily inactive access cleanup',
        'last_daily_diary_notification_time' => 'Last successful daily diary notification',
        'last_daily_reset_today_bandwidth_time' => 'Last successful daily traffic counter reset',
        'last_daily_job_notification_time' => 'Last successful daily job notification',
    ];

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072300;
    }

    public function down(): int
    {
        $this->revert(DB::getPdo());

        return 2026072203;
    }

    public function apply(\PDO $pdo): void
    {
        $this->repairProductInvoices($pdo);
        $this->seedDailyCheckpoints($pdo);
    }

    public function revert(\PDO $pdo): void
    {
        $delete = $pdo->prepare('DELETE FROM `config` WHERE `item` = ?');

        foreach (array_keys(self::DAILY_CHECKPOINTS) as $item) {
            $delete->execute([$item]);
        }

        // Corrected financial history is intentionally not made inconsistent again.
    }

    private function repairProductInvoices(\PDO $pdo): void
    {
        $rows = $pdo->query(
            'SELECT
                i.`id`,
                i.`status`,
                i.`paid_amount`,
                i.`refunded_amount`,
                o.`price` AS `order_price`,
                CASE WHEN EXISTS (
                    SELECT 1 FROM `paylist` p
                    WHERE p.`invoice_id` = i.`id` AND p.`status` = 1
                ) THEN 1 ELSE 0 END AS `has_gateway_payment`
             FROM `invoice` i
             INNER JOIN `order` o ON o.`id` = i.`order_id`
             WHERE i.`type` = \'product\''
        )->fetchAll(\PDO::FETCH_ASSOC);

        $update = $pdo->prepare(
            'UPDATE `invoice`
             SET `price` = ?,
                 `original_price` = ?,
                 `paid_amount` = ?,
                 `refunded_amount` = ?,
                 `status` = ?,
                 `update_time` = ?
             WHERE `id` = ?'
        );

        foreach ($rows as $row) {
            $due = $this->money($row['order_price'] ?? 0);
            $paid = $this->money($row['paid_amount'] ?? 0);
            if (bccomp($paid, $due, 2) > 0) {
                $paid = $due;
            }

            $refunded = $this->money($row['refunded_amount'] ?? 0);
            if (bccomp($refunded, $paid, 2) > 0) {
                $refunded = $paid;
            }

            $status = (string) ($row['status'] ?? 'unpaid');
            $remaining = bcsub($due, $paid, 2);
            if (bccomp($remaining, '0.00', 2) < 0) {
                $remaining = '0.00';
            }

            if ($status === 'partially_paid' && bccomp($remaining, '0.00', 2) === 0) {
                $status = (int) ($row['has_gateway_payment'] ?? 0) === 1
                    ? 'paid_gateway'
                    : 'paid_balance';
            }

            $price = $status === 'partially_paid' ? $remaining : $due;
            $update->execute([
                $price,
                $due,
                $paid,
                $refunded,
                $status,
                time(),
                $row['id'],
            ]);
        }
    }

    private function seedDailyCheckpoints(\PDO $pdo): void
    {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM `config` WHERE `item` = ?');
        $insert = $pdo->prepare(
            'INSERT INTO `config` (`item`, `value`, `class`, `is_public`, `type`, `default`, `mark`)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        foreach (self::DAILY_CHECKPOINTS as $item => $mark) {
            $exists->execute([$item]);
            if ((int) $exists->fetchColumn() === 0) {
                $insert->execute([$item, '0', 'cron', 0, 'int', '0', $mark]);
            }
        }
    }

    private function money(mixed $amount): string
    {
        return bcadd((string) $amount, '0.00', 2);
    }
};
