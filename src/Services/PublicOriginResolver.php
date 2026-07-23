<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ServerRequestInterface;
use function array_filter;
use function array_unique;
use function array_values;
use function explode;
use function in_array;
use function is_array;
use function is_string;
use function parse_url;
use function sprintf;
use function strtolower;
use function trim;

final class PublicOriginResolver
{
    /** @return list<string> */
    public function expectedOrigins(ServerRequestInterface $request): array
    {
        $origins = array_filter([
            $this->fromUrl((string) ($_ENV['baseUrl'] ?? '')),
            $this->forwardedOrigin($request),
            $this->requestOrigin($request),
        ], is_string(...));

        return array_values(array_unique($origins));
    }

    public function fromUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));

        return is_array($parts) ? $this->fromParts($parts) : null;
    }

    /** @param array<string, mixed> $parts */
    private function fromParts(array $parts): ?string
    {
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : null;
        $port = match ([$scheme, $port]) {
            ['http', 80], ['https', 443] => null,
            default => $port,
        };

        return sprintf('%s://%s%s', $scheme, $host, $port === null ? '' : ':' . $port);
    }

    private function forwardedOrigin(ServerRequestInterface $request): ?string
    {
        $scheme = $this->firstHeaderValue($request->getHeaderLine('X-Forwarded-Proto'));
        $host = $this->firstHeaderValue($request->getHeaderLine('X-Forwarded-Host'));

        return $this->fromUrl(sprintf('%s://%s', $scheme, $host));
    }

    private function requestOrigin(ServerRequestInterface $request): ?string
    {
        $uri = $request->getUri();

        return $this->fromUrl(sprintf('%s://%s', $uri->getScheme(), $uri->getAuthority()));
    }

    private function firstHeaderValue(string $header): string
    {
        return trim(explode(',', $header)[0] ?? '');
    }
}
