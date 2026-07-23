<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\CsrfRejectionResponse;
use App\Services\PublicOriginResolver;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function array_filter;
use function array_intersect;
use function in_array;
use function is_string;
use function strtolower;
use function strtoupper;
use function trim;

final class SameOriginCsrf implements MiddlewareInterface
{
    private PublicOriginResolver $originResolver;
    private CsrfRejectionResponse $rejectionResponse;

    public function __construct(
        ?PublicOriginResolver $originResolver = null,
        ?CsrfRejectionResponse $rejectionResponse = null
    ) {
        $this->originResolver = $originResolver ?? new PublicOriginResolver();
        $this->rejectionResponse = $rejectionResponse ?? new CsrfRejectionResponse();
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($this->isAllowed($request)) {
            return $handler->handle($request);
        }

        return $this->rejectionResponse->create($request);
    }

    private function isAllowed(ServerRequestInterface $request): bool
    {
        if (in_array(strtoupper($request->getMethod()), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }

        $originHeader = trim($request->getHeaderLine('Origin'));
        $refererOrigin = $this->originResolver->fromUrl($request->getHeaderLine('Referer'));
        $candidates = array_filter([
            $this->originResolver->fromUrl($originHeader),
            $refererOrigin,
        ], is_string(...));

        return array_intersect($candidates, $this->originResolver->expectedOrigins($request)) !== []
            || $this->hasSameOriginFetchMetadata($request, $originHeader, $refererOrigin);
    }

    private function hasSameOriginFetchMetadata(
        ServerRequestInterface $request,
        string $originHeader,
        ?string $refererOrigin
    ): bool {
        return $originHeader === '' && $refererOrigin === null
            && strtolower($request->getHeaderLine('Sec-Fetch-Site')) === 'same-origin';
    }
}
