<?php

declare(strict_types=1);

namespace App\Services\IM;

use App\Exceptions\IMDeliveryException;
use App\Models\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use function is_array;
use const VERSION;

final class Discord extends Base
{
    private string $token;
    private Client $client;

    public function __construct(?string $token = null, ?Client $client = null)
    {
        $this->token = $token ?? (string) Config::obtain('discord_bot_token');
        $this->client = $client ?? new Client();
    }

    /**
     * @throws GuzzleException
     * @throws IMDeliveryException
     */
    public function send(string|int $to, string $msg): void
    {
        $to = (string) $to;
        $headers = [
            'Authorization' => "Bot {$this->token}",
            'User-Agent' => 'DiscordBot (' . ($_ENV['appName'] ?? 'SSPanel-UIM') . ', ' . VERSION . ')',
            'Content-Type' => 'application/json',
        ];

        $channel_check_url = 'https://discord.com/api/v10/channels/' . rawurlencode($to);

        $channel_check_response = $this->client->get($channel_check_url, [
            'headers' => $headers,
            'http_errors' => false,
            'connect_timeout' => 5,
            'timeout' => 15,
        ]);

        $channelStatus = $channel_check_response->getStatusCode();
        if ($channelStatus !== 200 && $channelStatus !== 404) {
            throw new IMDeliveryException('Discord channel lookup failed with HTTP ' . $channelStatus . '.');
        }

        if ($channelStatus === 404) {
            $dm_url = 'https://discord.com/api/v10/users/@me/channels';

            $dm_body = [
                'recipient_id' => $to,
            ];

            $dm_response = $this->client->post($dm_url, [
                'headers' => $headers,
                'json' => $dm_body,
                'connect_timeout' => 5,
                'timeout' => 15,
            ]);

            $dmPayload = json_decode($dm_response->getBody()->getContents(), true);
            if (! is_array($dmPayload) || ! isset($dmPayload['id'])) {
                throw new IMDeliveryException('Discord API did not return a direct-message channel id.');
            }
            $to = (string) $dmPayload['id'];
        }

        $channel_url = 'https://discord.com/api/v10/channels/' . rawurlencode($to) . '/messages';

        $msg_body = [
            'content' => $msg,
        ];

        $msg_response = $this->client->post($channel_url, [
            'headers' => $headers,
            'json' => $msg_body,
            'connect_timeout' => 5,
            'timeout' => 15,
        ]);

        if ($msg_response->getStatusCode() < 200 || $msg_response->getStatusCode() >= 300) {
            throw new IMDeliveryException($msg_response->getBody()->getContents());
        }
    }
}
