<?php

declare(strict_types=1);

use App\Interfaces\MigrationInterface;
use App\Services\DB;

return new class() implements MigrationInterface {
    private const CHECKPOINTS = [
        'last_daily_finance_mail_time' => '上次执行每日财务报告的时间',
        'last_weekly_finance_mail_time' => '上次执行每周财务报告的时间',
        'last_monthly_finance_mail_time' => '上次执行每月财务报告的时间',
        'last_detect_gfw_job_time' => '上次执行节点被墙检测的时间',
        'last_detect_ban_job_time' => '上次执行审计封禁的时间',
    ];

    public function up(): int
    {
        $this->apply(DB::getPdo());

        return 2026072106;
    }

    public function down(): int
    {
        $this->revert(DB::getPdo());

        return 2026072105;
    }

    public function apply(\PDO $pdo): void
    {
        $exists = $pdo->prepare('SELECT COUNT(*) FROM `config` WHERE `item` = ?');
        $insert = $pdo->prepare(
            'INSERT INTO `config` (`item`, `value`, `class`, `is_public`, `type`, `default`, `mark`)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        foreach (self::CHECKPOINTS as $item => $mark) {
            $exists->execute([$item]);

            if ((int) $exists->fetchColumn() > 0) {
                continue;
            }

            $insert->execute([$item, '0', 'cron', 0, 'int', '0', $mark]);
        }
    }

    public function revert(\PDO $pdo): void
    {
        $delete = $pdo->prepare('DELETE FROM `config` WHERE `item` = ?');

        foreach (self::CHECKPOINTS as $item => $_mark) {
            $delete->execute([$item]);
        }
    }
};
