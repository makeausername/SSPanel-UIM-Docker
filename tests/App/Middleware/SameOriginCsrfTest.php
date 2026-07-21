<?php

declare(strict_types=1);

namespace App\Middleware;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;

final class SameOriginCsrfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_ENV['baseUrl'] = 'https://panel.example.com';
        AppFactory::setResponseFactory(new HttpFactory());
    }

    public function testAllowsSameOriginPost(): void
    {
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'https://panel.example.com/admin/user')
            ->withHeader('Origin', 'https://panel.example.com');

        $response = (new SameOriginCsrf())->process($request, $this->handler());

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testRejectsCrossOriginPost(): void
    {
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'https://panel.example.com/admin/user')
            ->withHeader('Origin', 'https://attacker.example');

        $response = (new SameOriginCsrf())->process($request, $this->handler());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('CSRF_REJECTED', (string) $response->getBody());
    }

    public function testAllowsGetWithoutOrigin(): void
    {
        $request = (new HttpFactory())->createServerRequest('GET', 'https://panel.example.com/admin');

        $response = (new SameOriginCsrf())->process($request, $this->handler());

        $this->assertSame(204, $response->getStatusCode());
    }

    private function handler(): RequestHandlerInterface
    {
        return new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response(204);
            }
        };
    }
}
