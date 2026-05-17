<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\FrontendI18n;
use App\Services\Theme;
use App\Utils\ResponseHelper;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class ThemeController extends BaseController
{
    public function switchThemeMode(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $themeMode = Theme::MODE_LIGHT;
        Theme::store($themeMode);

        return ResponseHelper::successWithData($response, FrontendI18n::trans('common.success'), [
            'theme_mode' => $themeMode,
        ])->withHeader('HX-Refresh', 'true');
    }
}
