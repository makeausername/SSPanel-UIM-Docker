<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\RateLimit;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use Throwable;

final class NodeEnrollRateLimit implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = (string) ($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');

        try {
            if (! (new RateLimit())->checkRateLimit('node_api_ip', $ip)) {
                $response = AppFactory::determineResponseFactory()->createResponse(429);
                $response->getBody()->write((string) json_encode([
                    'ret' => 0,
                    'msg' => 'Node enrollment rate limit exceeded',
                    'code' => 'RATE_LIMITED',
                ]));

                return $response->withHeader('Content-Type', 'application/json');
            }
        } catch (Throwable) {
            $response = AppFactory::determineResponseFactory()->createResponse(503);
            $response->getBody()->write((string) json_encode([
                'ret' => 0,
                'msg' => 'Node enrollment rate limiter unavailable',
                'code' => 'RATE_LIMIT_UNAVAILABLE',
            ]));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }
}
