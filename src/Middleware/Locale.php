<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Locale as LocaleService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class Locale implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if (LocaleService::isAdminPath($path)) {
            LocaleService::setCurrent(LocaleService::DEFAULT_LOCALE);

            return $handler->handle($request);
        }

        if (! LocaleService::isFrontendPath($path)) {
            LocaleService::setCurrent(LocaleService::DEFAULT_LOCALE);

            return $handler->handle($request);
        }

        LocaleService::startSessionIfNeeded();
        $locale = LocaleService::detect(
            $path,
            $_SESSION ?? [],
            $_COOKIE ?? [],
            $request->getHeaderLine('Accept-Language')
        );

        LocaleService::setCurrent($locale);

        return $handler->handle($request->withAttribute(LocaleService::SESSION_KEY, $locale));
    }
}
