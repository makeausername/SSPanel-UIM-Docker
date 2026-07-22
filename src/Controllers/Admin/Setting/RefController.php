<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Setting;

use App\Controllers\BaseController;
use App\Services\FrontendI18n;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Smarty\Exception;

final class RefController extends BaseController
{
    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->fetch('admin/setting/ref.tpl')
        );
    }

    public function save(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->withJson([
            'ret' => 0,
            'msg' => FrontendI18n::trans('user.invite.admin_fixed_rule_read_only'),
        ]);
    }
}
