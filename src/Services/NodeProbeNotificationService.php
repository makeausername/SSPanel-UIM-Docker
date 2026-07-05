<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Config;
use App\Models\Node;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientExceptionInterface;
use Telegram\Bot\Exceptions\TelegramSDKException;
use function trim;

final class NodeProbeNotificationService
{
    public static function shouldNotifyTransition(string $oldStatus, string $newStatus): bool
    {
        return self::transitionMessage($oldStatus, $newStatus) !== null;
    }

    public static function notifyTransition(Node $node, string $oldStatus, string $newStatus, array $context = []): void
    {
        $message = self::transitionMessage($oldStatus, $newStatus);

        if ($message === null) {
            return;
        }

        $nodeName = self::nodeName($node);
        $adminMessage = self::adminMessage($message['admin'], $nodeName, $context);

        try {
            Notification::notifyAdmin(
                self::appName() . '-' . $message['title'],
                $adminMessage,
            );
        } catch (GuzzleException | ClientExceptionInterface | TelegramSDKException) {
        }

        if (! Config::obtain($message['group_config'])) {
            return;
        }

        try {
            Notification::notifyUserGroup(
                self::groupMessage($message['group'], $nodeName),
            );
        } catch (GuzzleException | ClientExceptionInterface | TelegramSDKException) {
        }
    }

    private static function transitionMessage(string $oldStatus, string $newStatus): ?array
    {
        if ($oldStatus === $newStatus) {
            return null;
        }

        if ($newStatus === NodeProbeService::STATUS_SUSPECTED_BLOCKED) {
            return [
                'title' => '系统警告',
                'admin' => '管理员你好，系统检测到节点 {node_name} 疑似被墙。检测区域：{region}，检测类型：{type}，错误：{error}',
                'group_config' => 'im_bot_group_notify_node_gfwed',
                'group' => '节点 {node_name} 疑似被墙',
            ];
        }

        if ($oldStatus === NodeProbeService::STATUS_SUSPECTED_BLOCKED && $newStatus === NodeProbeService::STATUS_OK) {
            return [
                'title' => '系统提示',
                'admin' => '管理员你好，系统检测到节点 {node_name} 已恢复可达。',
                'group_config' => 'im_bot_group_notify_node_ungfwed',
                'group' => '节点 {node_name} 已恢复可达',
            ];
        }

        if (
            $newStatus === NodeProbeService::STATUS_UNREACHABLE
            && $oldStatus !== NodeProbeService::STATUS_UNREACHABLE
            && $oldStatus !== NodeProbeService::STATUS_SUSPECTED_BLOCKED
        ) {
            return [
                'title' => '系统警告',
                'admin' => '管理员你好，系统检测到节点 {node_name} 不可达。检测区域：{region}，检测类型：{type}，错误：{error}',
                'group_config' => 'im_bot_group_notify_node_offline',
                'group' => '节点 {node_name} 不可达',
            ];
        }

        if (
            ($oldStatus === NodeProbeService::STATUS_UNREACHABLE || $oldStatus === NodeProbeService::STATUS_ERROR)
            && $newStatus === NodeProbeService::STATUS_OK
        ) {
            return [
                'title' => '系统提示',
                'admin' => '管理员你好，系统检测到节点 {node_name} 恢复可达。',
                'group_config' => 'im_bot_group_notify_node_online',
                'group' => '节点 {node_name} 恢复可达',
            ];
        }

        return null;
    }

    private static function adminMessage(string $template, string $nodeName, array $context): string
    {
        return strtr($template, [
            '{node_name}' => $nodeName,
            '{region}' => self::contextValue($context, 'probe_region'),
            '{type}' => self::contextValue($context, 'probe_type'),
            '{error}' => self::contextValue($context, 'error'),
        ]);
    }

    private static function groupMessage(string $template, string $nodeName): string
    {
        return strtr($template, [
            '{node_name}' => $nodeName,
        ]);
    }

    private static function contextValue(array $context, string $key): string
    {
        $value = trim((string) ($context[$key] ?? ''));

        return $value === '' ? '-' : $value;
    }

    private static function nodeName(Node $node): string
    {
        $name = trim((string) ($node->name ?? ''));

        return $name === '' ? '#' . (string) $node->id : $name;
    }

    private static function appName(): string
    {
        $appName = trim((string) ($_ENV['appName'] ?? ''));

        return $appName === '' ? 'SSPanel' : $appName;
    }
}
