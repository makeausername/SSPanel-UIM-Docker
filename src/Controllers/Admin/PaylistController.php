<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Paylist;
use App\Services\DataTableRequest;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class PaylistController extends BaseController
{
    private static array $details =
        [
            'field' => [
                'id' => '事件ID',
                'userid' => '用户ID',
                'total' => '金额',
                'status' => '状态',
                'gateway' => '支付网关',
                'tradeno' => '网关单号',
                'datetime' => '支付时间',
                'invoice_id' => '关联账单ID',
            ],
        ];

    /**
     * 后台网关记录页面
     *
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/log/gateway.tpl')
        );
    }

    /**
     * 后台网关记录页面 AJAX
     */
    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $table = DataTableRequest::from(
            $request,
            ['id', 'userid', 'total', 'status', 'gateway', 'tradeno', 'datetime', 'invoice_id'],
            'id'
        );
        $query = Paylist::query();
        $total = (new Paylist())->count();
        if ($table->search !== '') {
            $query->where(static function ($query) use ($table): void {
                $query->where('id', $table->search)
                    ->orWhere('userid', $table->search)
                    ->orWhere('invoice_id', $table->search)
                    ->orWhere('tradeno', 'LIKE', "%{$table->search}%")
                    ->orWhere('gateway', 'LIKE', "%{$table->search}%");
            });
        }
        $filtered = $query->count();
        $query->orderBy($table->orderBy, $table->orderDirection);
        if ($table->orderBy !== 'id') {
            $query->orderBy('id', 'desc');
        }
        $paylists = $query->paginate($table->length, '*', '', $table->page);

        foreach ($paylists as $paylist) {
            $paylist->status = $paylist->status();
            $paylist->datetime = Tools::toDateTime((int) $paylist->datetime);
        }

        return $response->withJson([
            'draw' => $table->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'paylists' => $paylists,
        ]);
    }
}
