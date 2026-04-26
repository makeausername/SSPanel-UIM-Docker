<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\Locale;
use App\Utils\Cookie;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function is_string;
use function time;

final class LocaleController extends BaseController
{
    public function switchLocale(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        Locale::startSessionIfNeeded();

        $locale = $request->getParam('locale');
        $locale = is_string($locale) && Locale::isSupported($locale)
            ? $locale
            : Locale::DEFAULT_LOCALE;

        $_SESSION[Locale::SESSION_KEY] = $locale;
        $_COOKIE[Locale::COOKIE_KEY] = $locale;
        Locale::setCurrent($locale);

        Cookie::set([Locale::COOKIE_KEY => $locale], time() + 31536000);

        return $response
            ->withStatus(302)
            ->withHeader('Location', $this->getRedirectPath($request));
    }

    private function getRedirectPath(ServerRequest $request): string
    {
        $redirect = $request->getParam('redirect');
        $redirect = is_string($redirect) ? $redirect : null;
        $host = $request->getUri()->getHost();

        return Locale::sanitizeRedirect($redirect, $host)
            ?? Locale::sanitizeRedirect($request->getHeaderLine('Referer'), $host)
            ?? '/';
    }
}
