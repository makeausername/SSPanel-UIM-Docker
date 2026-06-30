<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use function preg_match;

final class NodeApiToken implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $authorization = $request->getHeaderLine('Authorization');
        $token = null;

        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            $token = $matches[1];
        }

        // TODO: Check node_tokens.token_hash and reject invalid or revoked tokens.
        return $handler->handle($request->withAttribute('node_api_token', $token));
    }
}
