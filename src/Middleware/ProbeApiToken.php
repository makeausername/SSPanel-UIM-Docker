<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\NodeProbeToken;
use App\Services\NodeProbeTokenService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use function json_encode;
use function preg_match;
use function time;
use function trim;
use function uniqid;

final class ProbeApiToken implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->extractToken($request);

        if ($token === null) {
            return $this->error($request, 'Missing probe token', 'AUTH_MISSING_PROBE_TOKEN');
        }

        $now = time();
        $tokenHash = (new NodeProbeTokenService())->hashToken($token);
        $tokenRecord = (new NodeProbeToken())
            ->where('token_hash', $tokenHash)
            ->where('is_enabled', 1)
            ->whereNull('revoked_at')
            ->where(static function ($query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->first();

        if ($tokenRecord === null) {
            return $this->error($request, 'Invalid probe token', 'AUTH_INVALID_PROBE_TOKEN');
        }

        $tokenRecord->last_used_at = $now;
        $tokenRecord->updated_at = $now;
        $tokenRecord->save();

        return $handler->handle(
            $request
                ->withAttribute('probe_token_id', (int) $tokenRecord->id)
                ->withAttribute('probe_region', (string) $tokenRecord->probe_region)
                ->withAttribute('probe_provider', $tokenRecord->probe_provider)
                ->withAttribute('probe_location', $tokenRecord->probe_location)
                ->withAttribute('probe_allowed_node_ids', NodeProbeTokenService::allowedNodeIdsFromRecord($tokenRecord))
        );
    }

    private function extractToken(ServerRequestInterface $request): ?string
    {
        $authorization = $request->getHeaderLine('Authorization');

        if (preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches) === 1) {
            $token = trim($matches[1]);

            return $token === '' ? null : $token;
        }

        $debugToken = trim($request->getHeaderLine('X-Probe-Token'));

        return $debugToken === '' ? null : $debugToken;
    }

    private function error(ServerRequestInterface $request, string $message, string $code): ResponseInterface
    {
        $response = AppFactory::determineResponseFactory()->createResponse(401);
        $response->getBody()->write((string) json_encode([
            'ret' => 0,
            'msg' => $message,
            'code' => $code,
            'request_id' => $this->getRequestId($request),
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function getRequestId(ServerRequestInterface $request): string
    {
        $requestId = $request->getHeaderLine('X-Request-Id');

        if ($requestId !== '') {
            return $requestId;
        }

        return uniqid('xnp_', true);
    }
}
