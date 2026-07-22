<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    private const DEFAULT_RULES = [
        [
            'managed_key' => 'system-private-destinations',
            'name' => '阻止访问私有网络地址',
            'description' => '防止节点被用于访问内网、回环和其他私有地址。',
            'match_type' => 'ip_cidr',
            'network' => 'any',
            'action' => 'block',
            'severity' => 'high',
            'source' => 'system',
            'priority' => 10,
            'patterns' => ['geoip:private'],
        ],
        [
            'managed_key' => 'system-bittorrent',
            'name' => '阻止 BitTorrent 流量',
            'description' => '降低 P2P 下载、版权投诉和节点带宽滥用风险。',
            'match_type' => 'protocol',
            'network' => 'any',
            'action' => 'block',
            'severity' => 'high',
            'source' => 'system',
            'priority' => 20,
            'patterns' => ['bittorrent'],
        ],
        [
            'managed_key' => 'system-smtp-25',
            'name' => '阻止出站 SMTP 25 端口',
            'description' => '降低垃圾邮件投递和节点 IP 信誉受损风险。',
            'match_type' => 'port',
            'network' => 'tcp',
            'action' => 'block',
            'severity' => 'high',
            'source' => 'system',
            'priority' => 30,
            'patterns' => ['25'],
        ],
        [
            'managed_key' => 'user-complaint-domains-20260722',
            'name' => '用户提供的投诉域名清单',
            'description' => '按域名后缀阻止用户提供的投诉风险域名；已去除重复 www 前缀。',
            'match_type' => 'domain_suffix',
            'network' => 'any',
            'action' => 'block',
            'severity' => 'high',
            'source' => 'user_complaint',
            'priority' => 40,
            'patterns' => [
                'dafahao.com',
                'minghui.com',
                'dongtaiwang.com',
                'epochtimes.com',
                'ntdtv.com',
                'falundafa.com',
                'wujieliulan.com',
                'zhengjian.com',
                'dafahao.org',
                'minghui.org',
                'dongtaiwang.org',
                'epochtimes.org',
                'ntdtv.org',
                'falundafa.org',
                'wujieliulan.org',
                'zhengjian.org',
                'dafahao.net',
                'minghui.net',
                'dongtaiwang.net',
                'epochtimes.net',
                'ntdtv.net',
                'falundafa.net',
                'wujieliulan.net',
                'zhengjian.net',
                'dafahao.com.tw',
                'minghui.com.tw',
                'dongtaiwang.com.tw',
                'epochtimes.com.tw',
                'ntdtv.com.tw',
                'falundafa.com.tw',
                'wujieliulan.com.tw',
                'zhengjian.com.tw',
            ],
        ],
    ];

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072200;
    }

    public function down(): int
    {
        $this->revert(DB::getPdo());

        return 2026072108;
    }

    public function revert(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $pdo->exec('DROP TABLE IF EXISTS `xnode_audit_events`');
        $pdo->exec('DROP TABLE IF EXISTS `xnode_audit_rule_patterns`');
        $pdo->exec('DROP TABLE IF EXISTS `xnode_audit_rules`');
        if ($this->tableExists($pdo, $driver, 'node_runtimes')) {
            foreach (['audit_applied_at', 'audit_error', 'audit_status', 'audit_hash', 'audit_revision'] as $column) {
                if ($this->columnExists($pdo, $driver, 'node_runtimes', $column)) {
                    $pdo->exec("ALTER TABLE `node_runtimes` DROP COLUMN `{$column}`");
                }
            }
        }
    }

    public function apply(\PDO $pdo): void
    {
        $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->createTables($pdo, $driver);
        $this->addRuntimeColumns($pdo, $driver);
        $this->seedDefaults($pdo);
    }

    private function createTables(\PDO $pdo, string $driver): void
    {
        $autoIncrement = $driver === 'sqlite' ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'bigint(20) unsigned NOT NULL AUTO_INCREMENT PRIMARY KEY';
        $text = $driver === 'sqlite' ? 'TEXT' : 'varchar(255)';
        $engine = $driver === 'sqlite' ? '' : ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $pdo->exec("CREATE TABLE IF NOT EXISTS `xnode_audit_rules` (
            `id` {$autoIncrement},
            `managed_key` {$text} DEFAULT NULL,
            `name` {$text} NOT NULL,
            `description` TEXT DEFAULT NULL,
            `match_type` varchar(32) NOT NULL,
            `network` varchar(16) NOT NULL DEFAULT 'any',
            `action` varchar(16) NOT NULL DEFAULT 'block',
            `severity` varchar(16) NOT NULL DEFAULT 'medium',
            `enabled` tinyint(1) NOT NULL DEFAULT 1,
            `source` varchar(32) NOT NULL DEFAULT 'admin',
            `scope_type` varchar(16) NOT NULL DEFAULT 'all',
            `scope_value` int(11) DEFAULT NULL,
            `priority` int(11) NOT NULL DEFAULT 100,
            `revision` int(11) unsigned NOT NULL DEFAULT 1,
            `managed` tinyint(1) NOT NULL DEFAULT 0,
            `created_at` int(11) unsigned NOT NULL,
            `updated_at` int(11) unsigned NOT NULL
        ){$engine}");
        $this->createIndex($pdo, $driver, 'xnode_audit_rules', 'xnode_audit_rules_managed_key_unique', ['managed_key'], true);
        $this->createIndex($pdo, $driver, 'xnode_audit_rules', 'xnode_audit_rules_enabled_priority', ['enabled', 'priority'], false);

        $pdo->exec("CREATE TABLE IF NOT EXISTS `xnode_audit_rule_patterns` (
            `id` {$autoIncrement},
            `rule_id` bigint(20) unsigned NOT NULL,
            `pattern` {$text} NOT NULL,
            `created_at` int(11) unsigned NOT NULL
        ){$engine}");
        $this->createIndex($pdo, $driver, 'xnode_audit_rule_patterns', 'xnode_audit_rule_pattern_unique', ['rule_id', 'pattern'], true);

        $pdo->exec("CREATE TABLE IF NOT EXISTS `xnode_audit_events` (
            `id` {$autoIncrement},
            `event_key` varchar(128) NOT NULL,
            `node_id` bigint(20) unsigned NOT NULL,
            `user_id` bigint(20) unsigned NOT NULL,
            `rule_id` bigint(20) unsigned NOT NULL,
            `source_ip` varchar(64) DEFAULT NULL,
            `target_host` {$text} DEFAULT NULL,
            `target_port` int(11) unsigned DEFAULT NULL,
            `protocol` varchar(32) DEFAULT NULL,
            `action` varchar(16) NOT NULL DEFAULT 'block',
            `observed_at` int(11) unsigned NOT NULL,
            `created_at` int(11) unsigned NOT NULL,
            `processed` tinyint(1) NOT NULL DEFAULT 0
        ){$engine}");
        $this->createIndex($pdo, $driver, 'xnode_audit_events', 'xnode_audit_event_key_unique', ['event_key'], true);
        $this->createIndex($pdo, $driver, 'xnode_audit_events', 'xnode_audit_events_user_processed', ['user_id', 'processed'], false);
        $this->createIndex($pdo, $driver, 'xnode_audit_events', 'xnode_audit_events_node_observed', ['node_id', 'observed_at'], false);
    }

    private function addRuntimeColumns(\PDO $pdo, string $driver): void
    {
        if (! $this->tableExists($pdo, $driver, 'node_runtimes')) {
            return;
        }

        $columns = [
            'audit_revision' => 'varchar(64) DEFAULT NULL',
            'audit_hash' => 'varchar(128) DEFAULT NULL',
            'audit_status' => 'varchar(32) DEFAULT NULL',
            'audit_error' => 'text DEFAULT NULL',
            'audit_applied_at' => 'int(11) unsigned DEFAULT NULL',
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->columnExists($pdo, $driver, 'node_runtimes', $name)) {
                $pdo->exec("ALTER TABLE `node_runtimes` ADD COLUMN `{$name}` {$definition}");
            }
        }
    }

    private function seedDefaults(\PDO $pdo): void
    {
        $now = time();
        $find = $pdo->prepare('SELECT `id` FROM `xnode_audit_rules` WHERE `managed_key` = ? LIMIT 1');
        $insertRule = $pdo->prepare(
            'INSERT INTO `xnode_audit_rules`
             (`managed_key`, `name`, `description`, `match_type`, `network`, `action`, `severity`, `enabled`, `source`, `scope_type`, `scope_value`, `priority`, `revision`, `managed`, `created_at`, `updated_at`)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, \'all\', NULL, ?, 1, 1, ?, ?)'
        );
        $updateRule = $pdo->prepare(
            'UPDATE `xnode_audit_rules` SET `name` = ?, `description` = ?, `match_type` = ?, `network` = ?, `action` = ?, `severity` = ?, `source` = ?, `priority` = ?, `managed` = 1, `updated_at` = ? WHERE `id` = ?'
        );
        $deletePatterns = $pdo->prepare('DELETE FROM `xnode_audit_rule_patterns` WHERE `rule_id` = ?');
        $insertPattern = $pdo->prepare(
            'INSERT INTO `xnode_audit_rule_patterns` (`rule_id`, `pattern`, `created_at`) VALUES (?, ?, ?)'
        );

        foreach (self::DEFAULT_RULES as $rule) {
            $find->execute([$rule['managed_key']]);
            $ruleId = $find->fetchColumn();

            if ($ruleId === false) {
                $insertRule->execute([
                    $rule['managed_key'],
                    $rule['name'],
                    $rule['description'],
                    $rule['match_type'],
                    $rule['network'],
                    $rule['action'],
                    $rule['severity'],
                    $rule['source'],
                    $rule['priority'],
                    $now,
                    $now,
                ]);
                $ruleId = (int) $pdo->lastInsertId();
            } else {
                $ruleId = (int) $ruleId;
                $updateRule->execute([
                    $rule['name'],
                    $rule['description'],
                    $rule['match_type'],
                    $rule['network'],
                    $rule['action'],
                    $rule['severity'],
                    $rule['source'],
                    $rule['priority'],
                    $now,
                    $ruleId,
                ]);
            }

            $deletePatterns->execute([$ruleId]);
            foreach ($rule['patterns'] as $pattern) {
                $insertPattern->execute([$ruleId, $pattern, $now]);
            }
        }
    }

    private function createIndex(\PDO $pdo, string $driver, string $table, string $index, array $columns, bool $unique): void
    {
        if ($this->indexExists($pdo, $driver, $table, $index)) {
            return;
        }

        $columnSql = implode(', ', array_map(static fn (string $column): string => "`{$column}`", $columns));
        $uniqueSql = $unique ? 'UNIQUE ' : '';
        $pdo->exec("CREATE {$uniqueSql}INDEX `{$index}` ON `{$table}` ({$columnSql})");
    }

    private function tableExists(\PDO $pdo, string $driver, string $table): bool
    {
        if ($driver === 'sqlite') {
            $statement = $pdo->prepare("SELECT name FROM sqlite_master WHERE type = 'table' AND name = ?");
            $statement->execute([$table]);

            return $statement->fetchColumn() !== false;
        }

        $statement = $pdo->prepare('SHOW TABLES LIKE ?');
        $statement->execute([$table]);

        return $statement->fetchColumn() !== false;
    }

    private function columnExists(\PDO $pdo, string $driver, string $table, string $column): bool
    {
        if ($driver === 'sqlite') {
            foreach ($pdo->query("PRAGMA table_info(`{$table}`)")->fetchAll(\PDO::FETCH_ASSOC) as $candidate) {
                if (($candidate['name'] ?? null) === $column) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare("SHOW COLUMNS FROM `{$table}` LIKE ?");
        $statement->execute([$column]);

        return $statement->fetchColumn() !== false;
    }

    private function indexExists(\PDO $pdo, string $driver, string $table, string $index): bool
    {
        if ($driver === 'sqlite') {
            foreach ($pdo->query("PRAGMA index_list(`{$table}`)")->fetchAll(\PDO::FETCH_ASSOC) as $candidate) {
                if (($candidate['name'] ?? null) === $index) {
                    return true;
                }
            }

            return false;
        }

        $statement = $pdo->prepare("SHOW INDEX FROM `{$table}` WHERE `Key_name` = ?");
        $statement->execute([$index]);

        return $statement->fetchColumn() !== false;
    }
};
