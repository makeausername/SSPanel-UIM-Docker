<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Invoice;
use App\Models\Paylist;
use App\Services\BalancePaymentService;
use App\Services\FrontendI18n;
use App\Services\DataTableRequest;
use App\Services\Payment;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function in_array;
use function json_decode;

final class InvoiceController extends BaseController
{
    private static array $details = [
        'field' => [
            'op' => 'common.operation',
            'id' => 'invoice.id',
            'order_id' => 'order.id',
            'price' => 'invoice.amount',
            'status' => 'invoice.status',
            'create_time' => 'common.created_at',
            'update_time' => 'common.updated_at',
            'pay_time' => 'payment.paid_at',
        ],
    ];

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $details = self::$details;
        foreach ($details['field'] as $field => $translationKey) {
            $details['field'][$field] = FrontendI18n::trans($translationKey);
        }

        return $response->write(
            $this->view()
                ->assign('details', $details)
                ->fetch('user/invoice/index.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function detail(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = $this->antiXss->xss_clean($args['id']);

        $invoice = (new Invoice())->where('user_id', $this->user->id)->where('id', $id)->first();

        if ($invoice === null) {
            return $response->withRedirect('/user/invoice');
        }

        $paylist = [];

        if ($invoice->status === 'paid_gateway') {
            $paylist = (new Paylist())->where('invoice_id', $invoice->id)->where('status', 1)->first();
        }

        $invoice->status_text = self::invoiceStatus((string) $invoice->status);
        $invoice->create_time = Tools::toDateTime($invoice->create_time);
        $invoice->update_time = Tools::toDateTime($invoice->update_time);
        $invoice->pay_time = Tools::toDateTime($invoice->pay_time);
        $invoice_content = json_decode($invoice->content);

        return $response->write(
            $this->view()
                ->assign('invoice', $invoice)
                ->assign('invoice_content', $invoice_content)
                ->assign('paylist', $paylist)
                ->assign('payments', Payment::getPaymentsEnabled())
                ->fetch('user/invoice/view.tpl')
        );
    }

    public function payBalance(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $invoiceId = (int) $this->antiXss->xss_clean($request->getParam('invoice_id'));
        $result = (new BalancePaymentService())->pay((int) $this->user->id, $invoiceId);

        if ($result['status'] === 'error') {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans($result['message_key']),
            ]);
        }

        if ($result['status'] === 'paid') {
            return $response->withHeader('HX-Redirect', '/user/invoice');
        }

        return $response->withHeader('HX-Refresh', 'true');
    }

    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $table = DataTableRequest::from(
            $request,
            ['id', 'order_id', 'price', 'status', 'create_time', 'update_time', 'pay_time'],
            'id'
        );
        $query = (new Invoice())->where('user_id', $this->user->id);
        $total = (clone $query)->count();
        if ($table->search !== '') {
            $query->where(static function ($query) use ($table): void {
                $query->where('id', $table->search)
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
            $invoice->op = '<a class="btn btn-primary" href="/user/invoice/' . $invoice->id . '/view">'
                . FrontendI18n::trans('docs.view') . '</a>';
            $invoice->status = self::invoiceStatus((string) $invoice->status);
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

    private static function invoiceStatus(string $status): string
    {
        $key = in_array($status, [
            'unpaid',
            'paid_gateway',
            'paid_balance',
            'paid_admin',
            'cancelled',
            'refunded_balance',
            'partially_paid',
        ], true) ? $status : 'unknown';

        return FrontendI18n::trans('invoice.status_values.' . $key);
    }
}
