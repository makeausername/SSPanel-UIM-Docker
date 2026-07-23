<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Locale;
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
        Locale::setCurrent(Locale::DEFAULT_LOCALE);
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

    public function testAllowsForwardedPublicOriginBehindReverseProxy(): void
    {
        $_ENV['baseUrl'] = 'https://configured.example.com';
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'http://nginx/user/checkin')
            ->withHeader('Origin', 'https://panel.example.com')
            ->withHeader('X-Forwarded-Proto', 'https, http')
            ->withHeader('X-Forwarded-Host', 'panel.example.com, nginx');

        $response = (new SameOriginCsrf())->process($request, $this->handler());

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testNormalizesDefaultHttpsPort(): void
    {
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'http://nginx/auth/login')
            ->withHeader('Origin', 'https://panel.example.com:443');

        $response = (new SameOriginCsrf())->process($request, $this->handler());

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testAllowsSameOriginRefererWhenOriginIsMissing(): void
    {
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'http://nginx/auth/login')
            ->withHeader('Referer', 'https://panel.example.com/auth/login');

        $response = (new SameOriginCsrf())->process($request, $this->handler());

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testAllowsSameOriginFetchMetadataWhenOriginAndRefererAreMissing(): void
    {
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'http://nginx/auth/login')
            ->withHeader('Sec-Fetch-Site', 'same-origin');

        $response = (new SameOriginCsrf())->process($request, $this->handler());

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testRendersLocalizedHtmlForRejectedBrowserForm(): void
    {
        Locale::setCurrent('zh-CN');
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'https://panel.example.com/user/checkin')
            ->withHeader('Origin', 'https://attacker.example')
            ->withHeader('Accept', 'text/html,application/xhtml+xml');

        $response = (new SameOriginCsrf())->process($request, $this->handler());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('安全校验失败', (string) $response->getBody());
        $this->assertStringNotContainsString('CSRF_REJECTED', (string) $response->getBody());
    }

    public function testKeepsJsonForRejectedHtmxRequest(): void
    {
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'https://panel.example.com/auth/login')
            ->withHeader('Origin', 'https://attacker.example')
            ->withHeader('Accept', 'text/html')
            ->withHeader('HX-Request', 'true');

        $response = (new SameOriginCsrf())->process($request, $this->handler());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('CSRF_REJECTED', (string) $response->getBody());
    }

    public function testRejectsMalformedOriginEvenWithSameOriginFetchMetadata(): void
    {
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'https://panel.example.com/auth/login')
            ->withHeader('Origin', 'null')
            ->withHeader('Sec-Fetch-Site', 'same-origin');

        $response = (new SameOriginCsrf())->process($request, $this->handler());

        $this->assertSame(403, $response->getStatusCode());
        $this->assertStringContainsString('CSRF_REJECTED', (string) $response->getBody());
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
