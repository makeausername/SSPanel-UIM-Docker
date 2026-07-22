<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\SysLog;
use App\Services\Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;
use function in_array;
use function json_encode;
use function strtoupper;
use function time;

final class AdminAudit implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());

        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $handler->handle($request);
        }

        try {
            $response = $handler->handle($request);
        } catch (Throwable $e) {
            $this->record($request, $method, 500);
            throw $e;
        }

        $this->record($request, $method, $response->getStatusCode());

        return $response;
    }

    private function record(ServerRequestInterface $request, string $method, int $status): void
    {
        try {
            $user = Auth::getUser();
            $context = [
                'method' => $method,
                'path' => $request->getUri()->getPath(),
                'status' => $status,
                'request_id' => $request->getHeaderLine('X-Request-Id'),
            ];

            (new SysLog())->insert([
                'user_id' => (int) ($user->id ?? 0),
                'ip' => (string) ($request->getServerParams()['REMOTE_ADDR'] ?? ''),
                'message' => $method . ' ' . $request->getUri()->getPath(),
                'level' => $status >= 400 ? 300 : 200,
                'context' => json_encode($context),
                'channel' => 'admin',
                'datetime' => time(),
            ]);
        } catch (Throwable) {
            return;
        }
    }
}
