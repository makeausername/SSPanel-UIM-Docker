<?php

declare(strict_types=1);

namespace App\Services\IM;

use App\Exceptions\IMDeliveryException;
use App\Models\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use function is_array;
use function json_decode;

final class Slack extends Base
{
    private string $token;
    private Client $client;

    public function __construct(?string $token = null, ?Client $client = null)
    {
        $this->token = $token ?? (string) Config::obtain('slack_token');
        $this->client = $client ?? new Client();
    }

    /**
     * @throws GuzzleException
     * @throws IMDeliveryException
     */
    public function send(string|int $to, string $msg): void
    {
        $url = 'https://slack.com/api/chat.postMessage';

        $headers = [
            'Authorization' => 'Bearer ' . $this->token,
            'Content-Type' => 'application/json',
        ];

        $body = [
            'channel' => $to,
            'text' => $msg,
        ];

        $response = $this->client->post($url, [
            'headers' => $headers,
            'json' => $body,
            'connect_timeout' => 5,
            'timeout' => 15,
        ]);

        $payload = json_decode((string) $response->getBody(), true);

        if ($response->getStatusCode() !== 200 || ! is_array($payload) || ($payload['ok'] ?? false) !== true) {
            $error = is_array($payload) ? (string) ($payload['error'] ?? 'invalid_response') : 'invalid_response';
            throw new IMDeliveryException('Slack API rejected the message: ' . $error);
        }
    }
}
