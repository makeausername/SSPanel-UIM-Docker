<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function explode;
use function implode;
use function is_string;
use function parse_url;
use function preg_match;
use function str_contains;
use function strtolower;
use function trim;
use const PHP_URL_HOST;

final class SecurityHeaders implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $scriptSources = [
            "'self'",
            "'unsafe-inline'",
            'https://unpkg.com',
            'https://challenges.cloudflare.com',
            'https://static.geetest.com',
            'https://js.hcaptcha.com',
            'https://*.hcaptcha.com',
            'https://www.recaptcha.net',
            'https://www.gstatic.com',
            'https://telegram.org',
            'https://client.crisp.chat',
            'https://cdn.livechatinc.com',
            'https://cdn.datatables.net',
            'https://cdnjs.cloudflare.com',
            'https://www.paypal.com',
            'https://www.paypalobjects.com',
        ];
        $styleSources = [
            "'self'",
            "'unsafe-inline'",
            'https://cdn.datatables.net',
            'https://cdnjs.cloudflare.com',
            'https://*.hcaptcha.com',
        ];
        $cdnSource = self::configuredCdnSource();
        if ($cdnSource !== null) {
            $scriptSources[] = $cdnSource;
            $styleSources[] = $cdnSource;
        }

        $policy = "default-src 'self'; script-src " . implode(' ', $scriptSources)
            . '; style-src ' . implode(' ', $styleSources)
            . "; img-src 'self' data: https:; font-src 'self' data: https:; connect-src 'self' https: wss:; "
            . "frame-src https:; object-src 'none'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'";
        $response = $handler->handle($request)
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'SAMEORIGIN')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->withHeader('Content-Security-Policy', $policy);

        $forwardedProto = strtolower(trim(explode(',', $request->getHeaderLine('X-Forwarded-Proto'))[0] ?? ''));
        if ($request->getUri()->getScheme() === 'https' || $forwardedProto === 'https') {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    private static function configuredCdnSource(): ?string
    {
        $configured = trim((string) ($_ENV['jsdelivr_url'] ?? ''));
        if ($configured === '') {
            return null;
        }

        $url = str_contains($configured, '://') ? $configured : 'https://' . $configured;
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || preg_match('/^[A-Za-z0-9.-]+$/', $host) !== 1) {
            return null;
        }

        return 'https://' . $host;
    }
}
