<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Cache;
use App\Services\DB;
use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Throwable;

final class HealthController
{
    private Closure $readinessCheck;

    public function __construct(?callable $readinessCheck = null)
    {
        $this->readinessCheck = $readinessCheck === null
            ? static function (): void {
                DB::connection()->select('SELECT 1');
                $redis = (new Cache())->initRedis();
                $pong = $redis->ping();
                $redis->close();

                if ($pong !== true && $pong !== '+PONG' && $pong !== 'PONG') {
                    throw new RuntimeException('Redis readiness check failed.');
                }
            }
            : Closure::fromCallable($readinessCheck);
    }

    public function index(
        ServerRequestInterface $request,
        ResponseInterface $response,
        array $args
    ): ResponseInterface {
        try {
            ($this->readinessCheck)();
        } catch (Throwable) {
            $response->getBody()->write('unavailable');

            return $response
                ->withStatus(503)
                ->withHeader('Content-Type', 'text/plain; charset=utf-8');
        }

        $response->getBody()->write('ok');

        return $response->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
