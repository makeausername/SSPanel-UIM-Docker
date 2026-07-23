<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\LoginIp;
use App\Services\DataTableRequest;
use App\Utils\Tools;
use Exception;
use MaxMind\Db\Reader\InvalidDatabaseException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class LoginLogController extends BaseController
{
    private static array $details =
        [
            'field' => [
                'id' => '事件ID',
                'userid' => '用户ID',
                'ip' => '登录IP',
                'location' => 'IP归属地',
                'datetime' => '时间',
                'type' => '类型',
            ],
        ];

    /**
     * 后台登录记录页面
     *
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/log/login.tpl')
        );
    }

    /**
     * 后台登录记录页面 AJAX
     *
     * @throws InvalidDatabaseException
     */
    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $table = DataTableRequest::from(
            $request,
            ['id', 'userid', 'ip', 'datetime', 'type'],
            'id'
        );

        $login_log = LoginIp::query();

        if ($table->search !== '') {
            $login_log->where('userid', '=', $table->search)
                ->orWhere('ip', 'LIKE', "%{$table->search}%");
        }

        $login_log->orderBy($table->orderBy, $table->orderDirection);
        if ($table->orderBy !== 'id') {
            $login_log->orderBy('id', 'desc');
        }

        $filtered = $login_log->count();
        $total = (new LoginIp())->count();

        $logins = $login_log->paginate($table->length, '*', '', $table->page);

        foreach ($logins as $login) {
            $login->location = Tools::getIpLocation($login->ip);
            $login->datetime = Tools::toDateTime((int) $login->datetime);
            $login->type = $login->type();
        }

        return $response->withJson([
            'draw' => $table->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'logins' => $logins,
        ]);
    }
}
