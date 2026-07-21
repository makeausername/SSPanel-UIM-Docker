<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;

final class RequestBodyLimit implements MiddlewareInterface
{
    public function __construct(private readonly int $maxBytes)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentLength = $request->getHeaderLine('Content-Length');
        $streamSize = $request->getBody()->getSize();

        if (($contentLength !== '' && (int) $contentLength > $this->maxBytes)
            || ($streamSize !== null && $streamSize > $this->maxBytes)) {
            $response = AppFactory::determineResponseFactory()->createResponse(413);
            $response->getBody()->write((string) json_encode([
                'ret' => 0,
                'msg' => 'Request body too large',
                'code' => 'PAYLOAD_TOO_LARGE',
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
