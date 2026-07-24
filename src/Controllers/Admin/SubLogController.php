<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\SubscribeLog;
use App\Services\DataTableRequest;
use App\Utils\Tools;
use Exception;
use MaxMind\Db\Reader\InvalidDatabaseException;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function htmlspecialchars;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

final class SubLogController extends BaseController
{
    private static array $details =
        [
            'field' => [
                'id' => '事件ID',
                'user_id' => '用户ID',
                'type' => '获取的订阅类型',
                'request_ip' => '请求IP',
                'location' => 'IP归属地',
                'request_time' => '请求时间',
                'request_user_agent' => '客户端标识符',
            ],
        ];

    /**
     * 后台订阅记录页面
     *
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/log/sub.tpl')
        );
    }

    /**
     * 后台订阅记录页面 AJAX
     *
     * @throws InvalidDatabaseException
     */
    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $table = DataTableRequest::from(
            $request,
            ['id', 'user_id', 'type', 'request_ip', 'request_time', 'request_user_agent'],
            'id'
        );

        $sub_log = SubscribeLog::query();

        if ($table->search !== '') {
            $sub_log->where('user_id', '=', $table->search)
                ->orWhere('type', 'LIKE', "%{$table->search}%")
                ->orWhere('request_ip', 'LIKE', "%{$table->search}%")
                ->orWhere('request_user_agent', 'LIKE', "%{$table->search}%");
        }

        $sub_log->orderBy($table->orderBy, $table->orderDirection);
        if ($table->orderBy !== 'id') {
            $sub_log->orderBy('id', 'desc');
        }

        $filtered = $sub_log->count();
        $total = (new SubscribeLog())->count();

        $subscribes = $sub_log->paginate($table->length, '*', '', $table->page);

        foreach ($subscribes as $subscribe) {
            $subscribe->request_time = Tools::toDateTime($subscribe->request_time);
            $subscribe->location = htmlspecialchars(
                (string) $subscribe->location,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
            $subscribe->request_user_agent = htmlspecialchars(
                (string) $subscribe->request_user_agent,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
        }

        return $response->withJson([
            'draw' => $table->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'subscribes' => $subscribes,
        ]);
    }
}
