<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\IM\Discord;
use App\Services\IM\Slack;
use App\Services\IM\Telegram;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class IMTest extends TestCase
{
    public function testConfiguredImTypesMapToTheBoundAccountProviders(): void
    {
        $this->assertSame(Slack::class, IM::clientClass(1));
        $this->assertSame(Discord::class, IM::clientClass(2));
        $this->assertSame(Telegram::class, IM::clientClass(4));
    }

    public function testSlackPreservesStringChannelIdsAndRejectsBusinessErrors(): void
    {
        $history = [];
        $handler = new MockHandler([
            new Response(200, [], '{"ok":false,"error":"channel_not_found"}'),
        ]);
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($history));
        $slack = new Slack('test-token', new Client(['handler' => $stack]));

        try {
            $slack->send('C01ABCXYZ', 'test');
            $this->fail('Slack business errors must not be reported as success.');
        } catch (Exception $e) {
            $this->assertStringContainsString('channel_not_found', $e->getMessage());
        }

        $body = json_decode((string) $history[0]['request']->getBody(), true);
        $this->assertSame('C01ABCXYZ', $body['channel']);
    }

    public function testDiscordFallsBackFromUnknownChannelToDirectMessage(): void
    {
        $history = [];
        $handler = new MockHandler([
            new Response(404, [], '{"message":"Unknown Channel"}'),
            new Response(200, [], '{"id":"998877"}'),
            new Response(200, [], '{"id":"message-id"}'),
        ]);
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($history));
        $discord = new Discord('test-token', new Client(['handler' => $stack]));

        $discord->send('user-id-123', 'test');

        $this->assertCount(3, $history);
        $this->assertSame('/api/v10/users/@me/channels', $history[1]['request']->getUri()->getPath());
        $this->assertSame('/api/v10/channels/998877/messages', $history[2]['request']->getUri()->getPath());
    }

    public function testDiscordDoesNotTreatAuthenticationFailuresAsUnknownUsers(): void
    {
        $history = [];
        $handler = new MockHandler([
            new Response(401, [], '{"message":"401: Unauthorized"}'),
        ]);
        $stack = HandlerStack::create($handler);
        $stack->push(Middleware::history($history));
        $discord = new Discord('invalid-token', new Client(['handler' => $stack]));

        $this->expectException(GuzzleException::class);
        try {
            $discord->send('user-id-123', 'test');
        } finally {
            $this->assertCount(1, $history);
        }
    }
}
