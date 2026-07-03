<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\HourlyUsage;
use App\Models\Node;
use App\Models\NodeRuntime;
use App\Models\Paylist;
use App\Models\User;
use App\Utils\Tools;
use function array_fill;
use function array_map;
use function array_merge;
use function array_unique;
use function array_values;
use function count;
use function date;
use function floatval;
use function is_null;
use function json_decode;
use function round;
use function strtotime;
use function time;

final class Analytics
{
    /**
     * 获取累计收入
     */
    public static function getIncome(string $req): float
    {
        $today = strtotime('00:00:00');
        $paylist = new Paylist();
        $number = match ($req) {
            'today' => $paylist->where('status', 1)
                ->whereBetween('datetime', [$today, time()])
                ->sum('total'),
            'yesterday' => $paylist->where('status', 1)
                ->whereBetween('datetime', [strtotime('-1 day', $today), $today])
                ->sum('total'),
            'this month' => $paylist->where('status', 1)
                ->whereBetween('datetime', [strtotime('first day of this month 00:00:00'), time()])
                ->sum('total'),
            default => $paylist->where('status', 1)->sum('total'),
        };

        return is_null($number) ? 0.00 : round(floatval($number), 2);
    }

    public static function getTotalUser(): int
    {
        return (new User())->count();
    }

    public static function getCheckinUser(): int
    {
        return (new User())->where('last_check_in_time', '>', 0)->count();
    }

    public static function getTodayCheckinUser(): int
    {
        return (new User())->where('last_check_in_time', '>', strtotime('today'))->count();
    }

    public static function getTrafficUsage(): string
    {
        return Tools::autoBytes((new User())->sum('u') + (new User())->sum('d'));
    }

    public static function getTodayTrafficUsage(): string
    {
        return Tools::autoBytes((new User())->sum('transfer_today'));
    }

    public static function getRawTodayTrafficUsage(): int
    {
        return (new User())->sum('transfer_today');
    }

    public static function getRawGbTodayTrafficUsage(): float
    {
        return Tools::bToGB((new User())->sum('transfer_today'));
    }

    public static function getLastTrafficUsage(): string
    {
        return Tools::autoBytes((new User())->sum('u') + (new User())->sum('d') - (new User())->sum('transfer_today'));
    }

    public static function getRawLastTrafficUsage(): int
    {
        return (new User())->sum('u') + (new User())->sum('d') - (new User())->sum('transfer_today');
    }

    public static function getRawGbLastTrafficUsage(): float
    {
        return Tools::bToGB((new User())->sum('u') + (new User())->sum('d') - (new User())->sum('transfer_today'));
    }

    public static function getUnusedTrafficUsage(): string
    {
        return Tools::autoBytes((new User())->sum('transfer_enable') - (new User())->sum('u') - (new User())->sum('d'));
    }

    public static function getRawUnusedTrafficUsage(): int
    {
        return (new User())->sum('transfer_enable') - (new User())->sum('u') - (new User())->sum('d');
    }

    public static function getRawGbUnusedTrafficUsage(): float
    {
        return Tools::bToGB((new User())->sum('transfer_enable') - (new User())->sum('u') - (new User())->sum('d'));
    }

    public static function getTotalTraffic(): string
    {
        return Tools::autoBytes((new User())->sum('transfer_enable'));
    }

    public static function getRawTotalTraffic(): int
    {
        return (new User())->sum('transfer_enable');
    }

    public static function getRawGbTotalTraffic(): float
    {
        return Tools::bToGB((new User())->sum('transfer_enable'));
    }

    public static function getTotalNode(): int
    {
        return count(array_unique(array_merge(
            self::getLegacyTotalNodeIds(),
            self::getXNodeTotalNodeIds()
        )));
    }

    public static function getAliveNode(): int
    {
        return count(array_unique(array_merge(
            self::getLegacyAliveNodeIds(),
            self::getXNodeAliveNodeIds()
        )));
    }

    public static function getXNodeTotalNode(): int
    {
        return count(self::getXNodeTotalNodeIds());
    }

    public static function getXNodeAliveNode(): int
    {
        return count(self::getXNodeAliveNodeIds());
    }

    public static function getXNodeRuntimeSummary(int $limit = 10): array
    {
        $runtimes = (new NodeRuntime())
            ->join('node', 'node.id', '=', 'node_runtimes.node_id')
            ->orderBy('node_runtimes.last_seen', 'desc')
            ->limit($limit)
            ->get([
                'node_runtimes.node_id',
                'node.name',
                'node.server',
                'node_runtimes.state',
                'node_runtimes.last_seen',
                'node_runtimes.last_error',
                'node_runtimes.agent_version',
                'node_runtimes.core_version',
            ]);

        foreach ($runtimes as $runtime) {
            $lastSeen = (int) ($runtime->last_seen ?? 0);
            $runtime->last_seen_formatted = $lastSeen > 0 ? date('Y-m-d H:i:s', $lastSeen) : '-';
        }

        return $runtimes->all();
    }

    private static function getLegacyTotalNodeIds(): array
    {
        return self::toIntList((new Node())->where('node_heartbeat', '>', 0)->pluck('id')->toArray());
    }

    private static function getLegacyAliveNodeIds(): array
    {
        return self::toIntList((new Node())->where('node_heartbeat', '>', time() - 90)->pluck('id')->toArray());
    }

    private static function getXNodeTotalNodeIds(): array
    {
        return self::toIntList((new NodeRuntime())
            ->join('node', 'node.id', '=', 'node_runtimes.node_id')
            ->pluck('node_runtimes.node_id')
            ->toArray());
    }

    private static function getXNodeAliveNodeIds(): array
    {
        return self::toIntList((new NodeRuntime())
            ->join('node', 'node.id', '=', 'node_runtimes.node_id')
            ->where('node_runtimes.last_seen', '>', time() - 90)
            ->where(static function ($query): void {
                $query->whereNull('node_runtimes.state')
                    ->orWhere('node_runtimes.state', '!=', 'failed');
            })
            ->pluck('node_runtimes.node_id')
            ->toArray());
    }

    private static function toIntList(array $values): array
    {
        return array_values(array_unique(array_map('intval', $values)));
    }

    public static function getInactiveUser(): int
    {
        return (new User())->where('is_inactive', 1)->count();
    }

    public static function getActiveUser(): int
    {
        return (new User())->where('is_inactive', 0)->count();
    }

    public static function getUserHourlyUsage(int $user_id, string $date): array
    {
        $hourly_usage = (new HourlyUsage())->where('user_id', $user_id)->where('date', $date)->first();

        return $hourly_usage ? json_decode($hourly_usage->usage, true) : array_fill(0, 24, 0);
    }

    public static function getUserTodayHourlyUsage(int $user_id): array
    {
        $date = date('Y-m-d');

        return self::getUserHourlyUsage($user_id, $date);
    }
}
