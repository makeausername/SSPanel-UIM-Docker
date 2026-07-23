<?php

declare(strict_types=1);

namespace App\Services;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Factory\AppFactory;
use function htmlspecialchars;
use function json_encode;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

final class CsrfRejectionResponse
{
    public function create(ServerRequestInterface $request): ResponseInterface
    {
        $message = FrontendI18n::trans('response.security.csrf_rejected');

        return $this->expectsHtml($request) ? $this->html($message) : $this->json($message);
    }

    private function expectsHtml(ServerRequestInterface $request): bool
    {
        return strtolower(trim($request->getHeaderLine('HX-Request'))) !== 'true'
            && str_contains(strtolower($request->getHeaderLine('Accept')), 'text/html');
    }

    private function html(string $message): ResponseInterface
    {
        $language = Locale::current() === 'en-US' ? 'en' : 'zh-CN';
        $title = FrontendI18n::trans('common.failure');
        $returnHome = FrontendI18n::trans('response.security.return_home');
        $template = <<<'HTML'
<!doctype html>
<html lang="%s">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>%s</title>
</head>
<body>
<main>
    <h1>%s</h1>
    <p>%s</p>
    <p><a href="/">%s</a></p>
</main>
</body>
</html>
HTML;
        $html = sprintf(
            $template,
            $language,
            $this->escape($title),
            $this->escape($title),
            $this->escape($message),
            $this->escape($returnHome)
        );
        $response = AppFactory::determineResponseFactory()->createResponse(403);
        $response->getBody()->write($html);

        return $response
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withHeader('Vary', 'Accept');
    }

    private function json(string $message): ResponseInterface
    {
        $response = AppFactory::determineResponseFactory()->createResponse(403);
        $response->getBody()->write((string) json_encode([
            'ret' => 0,
            'msg' => $message,
            'code' => 'CSRF_REJECTED',
        ]));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Vary', 'Accept');
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
