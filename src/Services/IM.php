<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\IM\Discord;
use App\Services\IM\Slack;
use App\Services\IM\Telegram;
use GuzzleHttp\Exception\GuzzleException;
use Telegram\Bot\Exceptions\TelegramSDKException;

/*
 * IM Service
 */
final class IM
{
    /**
     * @return class-string<Discord|Slack|Telegram>
     */
    public static function clientClass(int $type): string
    {
        return match ($type) {
            1 => Slack::class,
            2 => Discord::class,
            default => Telegram::class,
        };
    }

    public static function getClient(int $type): Discord|Slack|Telegram
    {
        $clientClass = self::clientClass($type);

        return new $clientClass();
    }

    /**
     * @throws GuzzleException
     * @throws TelegramSDKException
     */
    public static function send(string|int $to, string $msg, int $type): void
    {
        self::getClient($type)->send((string) $to, $msg);
    }
}
