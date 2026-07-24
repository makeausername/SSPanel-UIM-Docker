<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\Config;
use GuzzleHttp\Client;
use RuntimeException;

final class Postmark extends Base
{
    public function send($to, $subject, $body): void
    {
        $configs = Config::getClass('email');
        $client = new Client();
        $res = $client->post('https://api.postmarkapp.com/email', [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Postmark-Server-Token' => $configs['postmark_key'],
            ],
            'json' => [
                'From' => $configs['postmark_sender'],
                'To' => $to,
                'Subject' => $subject,
                'HtmlBody' => $body,
                'MessageStream' => $configs['postmark_stream'],
            ],
            'connect_timeout' => 5,
            'timeout' => 15,
        ]);

        if ($res->getStatusCode() !== 200) {
            throw new RuntimeException((string) $res->getBody());
        }
    }
}
