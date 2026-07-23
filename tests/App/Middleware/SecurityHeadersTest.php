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
        $_ENV['jsdelivr_url'] = 'cdn.jsdelivr.net';
        $request = (new HttpFactory())->createServerRequest('GET', 'http://panel.example.com/user');
        $response = (new SecurityHeaders())->process($request, $this->handler());

        $policy = $response->getHeaderLine('Content-Security-Policy');
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString('https://cdn.jsdelivr.net', $policy);
        $this->assertStringContainsString('https://unpkg.com', $policy);
        $this->assertStringContainsString('https://cdn.datatables.net', $policy);
        $this->assertStringContainsString('https://cdnjs.cloudflare.com', $policy);
        $this->assertStringContainsString('https://www.paypal.com', $policy);
        $this->assertStringContainsString('https://*.hcaptcha.com', $policy);
        $this->assertStringContainsString('https://www.gstatic.com', $policy);
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline' https:;", $policy);
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
