<?php

declare(strict_types=1);

namespace App\Middleware;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class SecurityHeadersTest extends TestCase
{
    public function testEnforcesContentSecurityPolicy(): void
    {
        $request = (new HttpFactory())->createServerRequest('GET', 'http://panel.example.com/user');
        $response = (new SecurityHeaders())->process($request, $this->handler());

        $this->assertStringContainsString("object-src 'none'", $response->getHeaderLine('Content-Security-Policy'));
        $this->assertSame('', $response->getHeaderLine('Content-Security-Policy-Report-Only'));
        $this->assertSame('', $response->getHeaderLine('Strict-Transport-Security'));
    }

    public function testAddsHstsForForwardedHttps(): void
    {
        $request = (new HttpFactory())
            ->createServerRequest('GET', 'http://panel.example.com/user')
            ->withHeader('X-Forwarded-Proto', 'https');
        $response = (new SecurityHeaders())->process($request, $this->handler());

        $this->assertSame(
            'max-age=31536000; includeSubDomains',
            $response->getHeaderLine('Strict-Transport-Security')
        );
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
