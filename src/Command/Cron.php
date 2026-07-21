<?php

declare(strict_types=1);

namespace App\Command;

use App\Models\Config;
use App\Services\Cron as CronService;
use App\Services\Detect;
use Exception;
use RuntimeException;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Throwable;
use function date;
use function fwrite;
use function implode;
use function mktime;
use function time;
use const STDERR;

final class Cron extends Command
{
    public string $description = <<<EOL
├─=: php xcat Cron - 站点定时任务，每五分钟
EOL;

    /**
     * @throws TelegramSDKException
     * @throws Exception
     */
    public function boot(): void
    {
        ini_set('memory_limit', '-1');

        $now = time();
        $dayStart = mktime(0, 0, 0, (int) date('m'), (int) date('d'), (int) date('Y'));
        $dailyScheduledAt = mktime(
            (int) Config::obtain('daily_job_hour'),
            (int) Config::obtain('daily_job_minute'),
            0,
            (int) date('m'),
            (int) date('d'),
            (int) date('Y')
        );
        $weekStart = mktime(
            0,
            0,
            0,
            (int) date('m'),
            (int) date('d') - ((int) date('N') - 1),
            (int) date('Y')
        );
        $monthStart = mktime(0, 0, 0, (int) date('m'), 1, (int) date('Y'));
        $hourStart = mktime((int) date('H'), 0, 0, (int) date('m'), (int) date('d'), (int) date('Y'));
        $jobs = new CronService();
        $runDailyJob = self::isDue('last_daily_job_time', $dailyScheduledAt, $now);
        $failures = [];
        $runJob = static function (string $name, callable $job) use (&$failures): bool {
            try {
                $job();

                return true;
            } catch (Throwable $exception) {
                $failures[] = $name;
                fwrite(STDERR, '[cron] ' . $name . ' failed: ' . $exception::class . PHP_EOL);

                return false;
            }
        };
        $dailyJobSucceeded = true;
        $monthlyBaselineReady = true;

        // Reset the monthly baseline before activating add-ons bought on the reset day.
        if ($runDailyJob && ! $runJob('resetUserBandwidth', [$jobs, 'resetUserBandwidth'])) {
            $dailyJobSucceeded = false;
            $monthlyBaselineReady = false;
        }

        $runJob('processPendingOrder', [$jobs, 'processPendingOrder']);

        if ($monthlyBaselineReady) {
            foreach ([
                'processTabpOrderActivation',
                'processBandwidthOrderActivation',
                'processTimeOrderActivation',
                'processTopupOrderActivation',
            ] as $jobName) {
                $runJob($jobName, [$jobs, $jobName]);
            }
        }

        foreach ([
            'deleteUnpaidRegistrations',
            'expirePaidUserAccount',
            'sendPaidUserUsageLimitNotification',
            'updateNodeIp',
        ] as $jobName) {
            $runJob($jobName, [$jobs, $jobName]);
        }

        if ($_ENV['enable_detect_offline']) {
            $runJob('detectNodeOffline', [$jobs, 'detectNodeOffline']);
        }

        // Run daily job
        if ($runDailyJob) {
            foreach (['cleanDb', 'resetNodeBandwidth', 'sendDailyTrafficReport'] as $jobName) {
                if (! $runJob($jobName, [$jobs, $jobName])) {
                    $dailyJobSucceeded = false;
                }
            }

            if (Config::obtain('enable_detect_inactive_user')
                && ! $runJob('detectInactiveUser', [$jobs, 'detectInactiveUser'])
            ) {
                $dailyJobSucceeded = false;
            }

            if (Config::obtain('remove_inactive_user_link_and_invite')
                && ! $runJob('removeInactiveUserLinkAndInvite', [$jobs, 'removeInactiveUserLinkAndInvite'])
            ) {
                $dailyJobSucceeded = false;
            }

            if (Config::obtain('im_bot_group_notify_diary')
                && ! $runJob('sendDiaryNotification', [$jobs, 'sendDiaryNotification'])
            ) {
                $dailyJobSucceeded = false;
            }

            if (! $runJob('resetTodayBandwidth', [$jobs, 'resetTodayBandwidth'])) {
                $dailyJobSucceeded = false;
            }

            if (Config::obtain('im_bot_group_notify_daily_job')
                && ! $runJob('sendDailyJobNotification', [$jobs, 'sendDailyJobNotification'])
            ) {
                $dailyJobSucceeded = false;
            }

            if ($dailyJobSucceeded) {
                $runJob(
                    'markDailyJobComplete',
                    static function () use ($dailyScheduledAt): void {
                        self::markRun('last_daily_job_time', $dailyScheduledAt);
                    }
                );
            }
        }

        // Daily finance report
        if (Config::obtain('enable_daily_finance_mail')
            && self::isDue('last_daily_finance_mail_time', $dayStart, $now)
        ) {
            $runJob('sendDailyFinanceMail', static function () use ($jobs, $dayStart): void {
                $jobs->sendDailyFinanceMail();
                self::markRun('last_daily_finance_mail_time', $dayStart);
            });
        }

        // Weekly finance report
        if (Config::obtain('enable_weekly_finance_mail')
            && self::isDue('last_weekly_finance_mail_time', $weekStart, $now)
        ) {
            $runJob('sendWeeklyFinanceMail', static function () use ($jobs, $weekStart): void {
                $jobs->sendWeeklyFinanceMail();
                self::markRun('last_weekly_finance_mail_time', $weekStart);
            });
        }

        // Monthly finance report
        if (Config::obtain('enable_monthly_finance_mail')
            && self::isDue('last_monthly_finance_mail_time', $monthStart, $now)
        ) {
            $runJob('sendMonthlyFinanceMail', static function () use ($jobs, $monthStart): void {
                $jobs->sendMonthlyFinanceMail();
                self::markRun('last_monthly_finance_mail_time', $monthStart);
            });
        }

        // Detect GFW
        if (Config::obtain('enable_detect_gfw')
            && self::isDue('last_detect_gfw_job_time', $hourStart, $now)
        ) {
            $runJob('detectGfw', static function () use ($hourStart): void {
                (new Detect())->gfw();
                self::markRun('last_detect_gfw_job_time', $hourStart);
            });
        }

        // Detect ban
        if (Config::obtain('enable_detect_ban')
            && self::isDue('last_detect_ban_job_time', $hourStart, $now)
        ) {
            $runJob('detectBan', static function () use ($hourStart): void {
                (new Detect())->ban();
                self::markRun('last_detect_ban_job_time', $hourStart);
            });
        }

        // Run email queue
        $runJob('processEmailQueue', [$jobs, 'processEmailQueue']);

        if ($failures !== []) {
            throw new RuntimeException('Cron jobs failed: ' . implode(', ', $failures));
        }
    }

    public static function isDue(string $checkpoint, int $scheduledAt, int $now): bool
    {
        return $now >= $scheduledAt && (int) Config::obtain($checkpoint) < $scheduledAt;
    }

    private static function markRun(string $checkpoint, int $scheduledAt): void
    {
        if (! Config::set($checkpoint, $scheduledAt)) {
            throw new RuntimeException('Unable to persist cron checkpoint: ' . $checkpoint);
        }
    }
}
