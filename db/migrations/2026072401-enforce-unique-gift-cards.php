<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    private const INDEX = 'gift_card_card_unique';

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072401;
    }

    public function down(): int
    {
        $pdo = DB::getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($this->hasIndex($pdo, $driver)) {
            $driver === 'sqlite'
                ? $pdo->exec('DROP INDEX `' . self::INDEX . '`')
                : $pdo->exec('ALTER TABLE `gift_card` DROP INDEX `' . self::INDEX . '`');
        }

        return 2026072400;
    }

    public function apply(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($this->hasIndex($pdo, $driver)) {
            return;
        }

        $rows = $pdo->query('SELECT `id`, `card` FROM `gift_card` ORDER BY `id`')->fetchAll(\PDO::FETCH_ASSOC);
        $used = [];
        $update = $pdo->prepare('UPDATE `gift_card` SET `card` = ? WHERE `id` = ?');
        $hasEarlierEquivalent = $pdo->prepare(
            'SELECT COUNT(*) FROM `gift_card` WHERE `id` < ? AND `card` = ?'
        );
        $codeExists = $pdo->prepare('SELECT COUNT(*) FROM `gift_card` WHERE `card` = ?');
        foreach ($rows as $row) {
            $rowId = (int) $row['id'];
            $card = (string) $row['card'];
            $hasEarlierEquivalent->execute([$rowId, $card]);
            $key = strtolower(rtrim($card));
            if (
                $key !== ''
                && strlen($card) <= 64
                && ! isset($used[$key])
                && (int) $hasEarlierEquivalent->fetchColumn() === 0
            ) {
                $used[$key] = true;
                continue;
            }

            do {
                $card = bin2hex(random_bytes(18));
                $codeExists->execute([$card]);
            } while (isset($used[$card]) || (int) $codeExists->fetchColumn() > 0);

            $update->execute([$card, $rowId]);
            $used[$card] = true;
        }

        if ($driver !== 'sqlite') {
            $pdo->exec(
                "ALTER TABLE `gift_card`
                 MODIFY COLUMN `card` varchar(64) NOT NULL DEFAULT '' COMMENT '卡号'"
            );
        }
        $pdo->exec('CREATE UNIQUE INDEX `' . self::INDEX . '` ON `gift_card` (`card`)');
    }

    private function hasIndex(\PDO $pdo, string $driver): bool
    {
        if ($driver === 'sqlite') {
            foreach ($pdo->query("PRAGMA index_list('gift_card')")->fetchAll(\PDO::FETCH_ASSOC) as $index) {
                if (($index['name'] ?? null) === self::INDEX) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
        );
        $statement->execute(['gift_card', self::INDEX]);

        return (int) $statement->fetchColumn() > 0;
    }
};
