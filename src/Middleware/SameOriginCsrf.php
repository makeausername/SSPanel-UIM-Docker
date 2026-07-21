<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use function in_array;
use function parse_url;
use function rtrim;
use function strtolower;
use function strtoupper;

final class SameOriginCsrf implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        $expectedOrigins = $this->expectedOrigins($request);
        $origin = rtrim($request->getHeaderLine('Origin'), '/');

        if ($origin !== '' && in_array(strtolower($origin), $expectedOrigins, true)) {
            return $handler->handle($request);
        }

        $refererOrigin = $this->originFromUrl($request->getHeaderLine('Referer'));
        if ($refererOrigin !== null && in_array($refererOrigin, $expectedOrigins, true)) {
            return $handler->handle($request);
        }

        if ($origin === '' && $refererOrigin === null
            && strtolower($request->getHeaderLine('Sec-Fetch-Site')) === 'same-origin') {
            return $handler->handle($request);
        }

        $response = AppFactory::determineResponseFactory()->createResponse(403);
        $response->getBody()->write((string) json_encode([
            'ret' => 0,
            'msg' => 'Cross-site request rejected',
            'code' => 'CSRF_REJECTED',
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /** @return list<string> */
    private function expectedOrigins(ServerRequestInterface $request): array
    {
        $origins = [];
        $baseOrigin = $this->originFromUrl((string) ($_ENV['baseUrl'] ?? ''));
        if ($baseOrigin !== null) {
            $origins[] = $baseOrigin;
        }

        $uri = $request->getUri();
        if ($uri->getHost() !== '') {
            $port = $uri->getPort();
            $origins[] = strtolower($uri->getScheme() . '://' . $uri->getHost() . ($port === null ? '' : ':' . $port));
        }

        return array_values(array_unique($origins));
    }

    private function originFromUrl(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        return strtolower(
            $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '')
        );
    }
}
