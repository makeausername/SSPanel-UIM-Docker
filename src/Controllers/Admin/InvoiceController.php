<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Paylist;
use App\Services\DB;
use App\Services\DataTableRequest;
use App\Services\InvoiceAccountingService;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function in_array;
use function json_decode;
use function time;

final class InvoiceController extends BaseController
{
    private static array $details = [
        'field' => [
            'op' => '操作',
            'id' => '账单ID',
            'user_id' => '归属用户',
            'order_id' => '订单ID',
            'price' => '账单金额',
            'status' => '账单状态',
            'create_time' => '创建时间',
            'update_time' => '更新时间',
            'pay_time' => '支付时间',
        ],
    ];

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/invoice/index.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function detail(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = $args['id'];
        $invoice = (new Invoice())->find($id);
        $paylist = [];

        if ($invoice === null) {
            return $response->withStatus(301)->withHeader('Location', '/admin/invoice');
        }

        if ($invoice->status === 'paid_gateway') {
            $paylist = (new Paylist())->where('invoice_id', $invoice->id)->where('status', 1)->first();
        }

        $invoice->status_text = $invoice->status();
        $invoice->create_time = Tools::toDateTime($invoice->create_time);
        $invoice->update_time = Tools::toDateTime($invoice->update_time);
        $invoice->pay_time = Tools::toDateTime($invoice->pay_time);
        $invoice_content = json_decode($invoice->content);

        return $response->write(
            $this->view()
                ->assign('invoice', $invoice)
                ->assign('invoice_content', $invoice_content)
                ->assign('paylist', $paylist)
                ->fetch('admin/invoice/view.tpl')
        );
    }

    public function markPaid(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $invoiceId = (int) $args['id'];
        $result = DB::connection()->transaction(static function () use ($invoiceId): array {
            $invoice = (new Invoice())->where('id', $invoiceId)->lockForUpdate()->first();

            if ($invoice === null) {
                return ['ret' => 0, 'msg' => '账单不存在'];
            }

            if (in_array($invoice->status, ['paid_gateway', 'paid_balance', 'paid_admin'], true)) {
                return ['ret' => 0, 'msg' => '不能标记已经支付的账单'];
            }

            $order = (new Order())->where('id', $invoice->order_id)->lockForUpdate()->first();
            if ($order === null || $order->status === 'cancelled') {
                return ['ret' => 0, 'msg' => '关联订单已被取消，标记失败'];
            }

            InvoiceAccountingService::initialize($invoice);
            $invoice->paid_amount = InvoiceAccountingService::money($invoice->original_price);
            $invoice->update_time = time();
            $invoice->pay_time = time();
            $invoice->status = 'paid_admin';
            $invoice->save();

            $order->update_time = time();
            $order->status = 'pending_activation';
            $order->save();

            return ['ret' => 1, 'msg' => '成功标记账单为已支付（管理员）'];
        });

        return $response->withJson($result);
    }

    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $table = DataTableRequest::from(
            $request,
            ['id', 'user_id', 'order_id', 'price', 'status', 'create_time', 'update_time', 'pay_time'],
            'id'
        );
        $query = Invoice::query();
        $total = (new Invoice())->count();
        if ($table->search !== '') {
            $query->where(static function ($query) use ($table): void {
                $query->where('id', $table->search)
                    ->orWhere('user_id', $table->search)
                    ->orWhere('order_id', $table->search)
                    ->orWhere('status', 'LIKE', "%{$table->search}%");
            });
        }
        $filtered = $query->count();
        $query->orderBy($table->orderBy, $table->orderDirection);
        if ($table->orderBy !== 'id') {
            $query->orderBy('id', 'desc');
        }
        $invoices = $query->paginate($table->length, '*', '', $table->page);

        foreach ($invoices as $invoice) {
            $invoice->op = '<a class="btn btn-primary" href="/admin/invoice/' . $invoice->id . '/view">查看</a>';
            $invoice->status = $invoice->status();
            $invoice->create_time = Tools::toDateTime($invoice->create_time);
            $invoice->update_time = Tools::toDateTime($invoice->update_time);
            $invoice->pay_time = Tools::toDateTime($invoice->pay_time);
        }

        return $response->withJson([
            'draw' => $table->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'invoices' => $invoices,
        ]);
    }
}
