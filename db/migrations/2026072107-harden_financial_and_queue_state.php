<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    private const CONFIGS = [
        'node_report_retention_days' => ['14', 'int', 'Node report receipt retention in days'],
        'node_probe_retention_days' => ['30', 'int', 'Node probe result retention in days'],
        'email_dead_retention_days' => ['30', 'int', 'Failed email retention in days'],
    ];

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072107;
    }

    public function down(): int
    {
        $this->revert(DB::getPdo());

        return 2026072106;
    }

    public function apply(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $this->addColumn($pdo, $driver, 'invoice', 'original_price', 'DECIMAL(12,2) NULL');
        $this->addColumn($pdo, $driver, 'invoice', 'paid_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
        $this->addColumn($pdo, $driver, 'invoice', 'refunded_amount', 'DECIMAL(12,2) NOT NULL DEFAULT 0');

        $this->addColumn($pdo, $driver, 'paylist', 'expected_provider_amount', 'DECIMAL(20,8) NULL');
        $this->addColumn($pdo, $driver, 'paylist', 'expected_provider_currency', 'VARCHAR(16) NULL');
        $this->addColumn($pdo, $driver, 'paylist', 'provider_transaction_id', 'VARCHAR(255) NULL');

        if (! $this->hasIndex($pdo, $driver, 'paylist', 'paylist_provider_transaction')) {
            $pdo->exec(
                'CREATE UNIQUE INDEX `paylist_provider_transaction`
                 ON `paylist` (`gateway`, `provider_transaction_id`)'
            );
        }

        $this->addColumn($pdo, $driver, 'email_queue', 'status', "VARCHAR(16) NOT NULL DEFAULT 'pending'");
        $this->addColumn($pdo, $driver, 'email_queue', 'attempts', 'INT NOT NULL DEFAULT 0');
        $this->addColumn($pdo, $driver, 'email_queue', 'next_attempt_at', 'INT NOT NULL DEFAULT 0');
        $this->addColumn($pdo, $driver, 'email_queue', 'locked_at', 'INT NULL');
        $this->addColumn($pdo, $driver, 'email_queue', 'lock_token', 'VARCHAR(64) NULL');
        $this->addColumn($pdo, $driver, 'email_queue', 'last_error', 'VARCHAR(512) NULL');
        $this->addColumn($pdo, $driver, 'email_queue', 'sent_at', 'INT NULL');

        if (! $this->hasIndex($pdo, $driver, 'email_queue', 'email_queue_ready')) {
            $pdo->exec(
                'CREATE INDEX `email_queue_ready` ON `email_queue` (`status`, `next_attempt_at`, `id`)'
            );
        }

        $pdo->exec('UPDATE `email_queue` SET `next_attempt_at` = `time` WHERE `next_attempt_at` = 0');
        $this->backfillInvoices($pdo);
        $this->seedConfigs($pdo);
        $pdo->exec("UPDATE `config` SET `is_public` = 0 WHERE `item` = 'cryptomus_api_key'");
    }

    public function revert(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($this->hasIndex($pdo, $driver, 'email_queue', 'email_queue_ready')) {
            $driver === 'sqlite'
                ? $pdo->exec('DROP INDEX `email_queue_ready`')
                : $pdo->exec('ALTER TABLE `email_queue` DROP INDEX `email_queue_ready`');
        }

        if ($this->hasIndex($pdo, $driver, 'paylist', 'paylist_provider_transaction')) {
            $driver === 'sqlite'
                ? $pdo->exec('DROP INDEX `paylist_provider_transaction`')
                : $pdo->exec('ALTER TABLE `paylist` DROP INDEX `paylist_provider_transaction`');
        }

        foreach (['sent_at', 'last_error', 'lock_token', 'locked_at', 'next_attempt_at', 'attempts', 'status'] as $column) {
            $this->dropColumn($pdo, $driver, 'email_queue', $column);
        }
        foreach (['provider_transaction_id', 'expected_provider_currency', 'expected_provider_amount'] as $column) {
            $this->dropColumn($pdo, $driver, 'paylist', $column);
        }
        foreach (['refunded_amount', 'paid_amount', 'original_price'] as $column) {
            $this->dropColumn($pdo, $driver, 'invoice', $column);
        }

        $delete = $pdo->prepare('DELETE FROM `config` WHERE `item` = ?');
        foreach (array_keys(self::CONFIGS) as $item) {
            $delete->execute([$item]);
        }
    }

    private function backfillInvoices(\PDO $pdo): void
    {
        $rows = $pdo->query(
            'SELECT `id`, `price`, `status`, `content` FROM `invoice` WHERE `original_price` IS NULL'
        )->fetchAll(\PDO::FETCH_ASSOC);
        $update = $pdo->prepare(
            'UPDATE `invoice`
             SET `original_price` = ?, `paid_amount` = ?, `refunded_amount` = ?
             WHERE `id` = ?'
        );

        foreach ($rows as $row) {
            $current = $this->money($row['price'] ?? 0);
            $partial = '0.00';
            $content = json_decode((string) ($row['content'] ?? ''), true);

            if (is_array($content)) {
                foreach ($content as $entry) {
                    if (! is_array($entry) || ! in_array(
                        (string) ($entry['name'] ?? ''),
                        ['Gateway partial payment', '余额部分支付'],
                        true
                    )) {
                        continue;
                    }

                    $amount = ltrim((string) ($entry['price'] ?? '0'), '-');
                    if (is_numeric($amount)) {
                        $partial = bcadd($partial, $this->money($amount), 2);
                    }
                }
            }

            $original = bcadd($current, $partial, 2);
            $status = (string) ($row['status'] ?? 'unpaid');
            $paid = match ($status) {
                'partially_paid' => $partial,
                'paid_gateway', 'paid_balance', 'paid_admin', 'refunded_balance' => $original,
                default => '0.00',
            };
            $refunded = $status === 'refunded_balance' ? $paid : '0.00';
            $update->execute([$original, $paid, $refunded, $row['id']]);
        }
    }

    private function seedConfigs(\PDO $pdo): void
    {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM `config` WHERE `item` = ?');
        $insert = $pdo->prepare(
            'INSERT INTO `config` (`item`, `value`, `class`, `is_public`, `type`, `default`, `mark`)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        foreach (self::CONFIGS as $item => [$value, $type, $mark]) {
            $exists->execute([$item]);
            if ((int) $exists->fetchColumn() === 0) {
                $insert->execute([$item, $value, 'cron', 0, $type, $value, $mark]);
            }
        }
    }

    private function addColumn(\PDO $pdo, string $driver, string $table, string $column, string $definition): void
    {
        if (! $this->hasColumn($pdo, $driver, $table, $column)) {
            $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    private function dropColumn(\PDO $pdo, string $driver, string $table, string $column): void
    {
        if ($this->hasColumn($pdo, $driver, $table, $column)) {
            $pdo->exec("ALTER TABLE `{$table}` DROP COLUMN `{$column}`");
        }
    }

    private function hasColumn(\PDO $pdo, string $driver, string $table, string $column): bool
    {
        if ($driver === 'sqlite') {
            foreach ($pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll(\PDO::FETCH_ASSOC) as $entry) {
                if (($entry['name'] ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function hasIndex(\PDO $pdo, string $driver, string $table, string $index): bool
    {
        if ($driver === 'sqlite') {
            foreach ($pdo->query("PRAGMA index_list(`{$table}`)")->fetchAll(\PDO::FETCH_ASSOC) as $entry) {
                if (($entry['name'] ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $statement->execute([$table, $index]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function money(mixed $amount): string
    {
        return bcadd((string) $amount, '0.00', 2);
    }
};
