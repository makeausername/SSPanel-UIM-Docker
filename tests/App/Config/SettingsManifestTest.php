<?php

declare(strict_types=1);

namespace App\Config;

use JsonException;
use PHPUnit\Framework\TestCase;
use function array_column;
use function array_unique;
use function count;
use function file_get_contents;
use function json_decode;

final class SettingsManifestTest extends TestCase
{
    private const REQUIRED_CRON_CHECKPOINTS = [
        'last_daily_reset_user_bandwidth_time',
        'last_daily_clean_db_time',
        'last_daily_reset_node_bandwidth_time',
        'last_daily_traffic_report_time',
        'last_daily_detect_inactive_user_time',
        'last_daily_remove_inactive_access_time',
        'last_daily_diary_notification_time',
        'last_daily_reset_today_bandwidth_time',
        'last_daily_job_notification_time',
    ];

    /**
     * @throws JsonException
     */
    public function testManifestPreservesAllDailyCronCheckpoints(): void
    {
        $manifest = json_decode(
            file_get_contents(dirname(__DIR__, 3) . '/config/settings.json'),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
        $items = array_column($manifest, 'item');

        self::assertCount(count($items), array_unique($items), 'Settings manifest contains duplicates.');
        foreach (self::REQUIRED_CRON_CHECKPOINTS as $checkpoint) {
            self::assertContains($checkpoint, $items);
        }
    }
}
