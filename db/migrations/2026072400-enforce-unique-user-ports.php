<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    private const INDEX = 'user_port_unique';

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072400;
    }

    public function down(): int
    {
        $pdo = DB::getPdo();
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($this->hasIndex($pdo, $driver)) {
            $driver === 'sqlite'
                ? $pdo->exec('DROP INDEX `' . self::INDEX . '`')
                : $pdo->exec('ALTER TABLE `user` DROP INDEX `' . self::INDEX . '`');
        }

        return 2026072301;
    }

    public function apply(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if ($this->hasIndex($pdo, $driver)) {
            return;
        }

        [$minPort, $maxPort] = $this->portRange($pdo);
        $rows = $pdo->query('SELECT `id`, `port` FROM `user` ORDER BY `id`')->fetchAll(\PDO::FETCH_ASSOC);
        if (count($rows) > $maxPort - $minPort + 1) {
            throw new \RuntimeException('The configured user port range is smaller than the existing user count.');
        }

        $used = [];
        $needsPort = [];
        foreach ($rows as $row) {
            $port = (int) $row['port'];
            if ($port < $minPort || $port > $maxPort || isset($used[$port])) {
                $needsPort[] = (int) $row['id'];
                continue;
            }
            $used[$port] = true;
        }

        $nextPort = $minPort;
        $update = $pdo->prepare('UPDATE `user` SET `port` = ? WHERE `id` = ?');
        foreach ($needsPort as $userId) {
            while ($nextPort <= $maxPort && isset($used[$nextPort])) {
                $nextPort++;
            }
            if ($nextPort > $maxPort) {
                throw new \RuntimeException('No unique user port remains during migration.');
            }
            $update->execute([$nextPort, $userId]);
            $used[$nextPort] = true;
            $nextPort++;
        }

        $pdo->exec('CREATE UNIQUE INDEX `' . self::INDEX . '` ON `user` (`port`)');
    }

    /**
     * @return array{0:int,1:int}
     */
    private function portRange(\PDO $pdo): array
    {
        $statement = $pdo->prepare('SELECT `value` FROM `config` WHERE `item` = ? LIMIT 1');
        $statement->execute(['min_port']);
        $minPort = (int) ($statement->fetchColumn() ?: 10000);
        $statement->execute(['max_port']);
        $maxPort = (int) ($statement->fetchColumn() ?: 65535);

        if ($minPort <= 0 || $minPort >= 65535 || $maxPort <= 0 || $maxPort > 65535 || $minPort > $maxPort) {
            throw new \RuntimeException('The configured user port range is invalid.');
        }

        return [$minPort, $maxPort];
    }

    private function hasIndex(\PDO $pdo, string $driver): bool
    {
        if ($driver === 'sqlite') {
            foreach ($pdo->query("PRAGMA index_list('user')")->fetchAll(\PDO::FETCH_ASSOC) as $index) {
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
        $statement->execute(['user', self::INDEX]);

        return (int) $statement->fetchColumn() > 0;
    }
};
