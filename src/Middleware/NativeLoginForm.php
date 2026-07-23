<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\FrontendI18n;
use App\Services\LoginFormFallback;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Factory\AppFactory;
use function is_array;
use function is_string;
use function json_decode;
use function strtoupper;

final class NativeLoginForm implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! $this->isLoginPost($request) || ! LoginFormFallback::isNative($request)) {
            return $handler->handle($request);
        }

        return $this->toBrowserResponse($handler->handle($request));
    }

    private function isLoginPost(ServerRequestInterface $request): bool
    {
        return strtoupper($request->getMethod()) === 'POST' && $request->getUri()->getPath() === '/auth/login';
    }

    private function toBrowserResponse(ResponseInterface $response): ResponseInterface
    {
        $redirect = $response->getHeaderLine('HX-Redirect');
        if ($redirect !== '') {
            return $this->redirectResponse($redirect, $response);
        }

        LoginFormFallback::storeError($this->errorMessage($response));

        return $this->redirectResponse('/auth/login', $response);
    }

    private function errorMessage(ResponseInterface $response): string
    {
        $payload = json_decode((string) $response->getBody(), true);
        $message = is_array($payload) ? ($payload['msg'] ?? null) : null;

        return is_string($message) ? $message : FrontendI18n::trans('common.unexpected_error');
    }

    private function redirectResponse(string $location, ResponseInterface $source): ResponseInterface
    {
        $response = AppFactory::determineResponseFactory()
            ->createResponse(303)
            ->withHeader('Location', $location);
        $retryAfter = $source->getHeaderLine('Retry-After');

        return $retryAfter === '' ? $response : $response->withHeader('Retry-After', $retryAfter);
    }
}
