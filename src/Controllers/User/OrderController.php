<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\UserCoupon;
use App\Services\CouponService;
use App\Services\DB;
use App\Services\FrontendI18n;
use App\Services\InvoiceAccountingService;
use App\Services\MonthlyPlanService;
use App\Services\OrderEligibilityService;
use App\Services\PendingOrderService;
use App\Utils\Cookie;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function bccomp;
use function htmlspecialchars;
use function in_array;
use function is_numeric;
use function is_object;
use function json_decode;
use function json_encode;
use function property_exists;
use function time;
use const ENT_QUOTES;
use const ENT_SUBSTITUTE;

final class OrderController extends BaseController
{
    private static array $details = [
        'field' => [
            'op' => 'common.operation',
            'id' => 'order.id',
            'product_id' => 'order.product_id',
            'product_type' => 'order.product_type',
            'product_name' => 'order.product_name',
            'coupon' => 'order.coupon',
            'price' => 'order.amount',
            'status' => 'order.status',
            'create_time' => 'common.created_at',
            'update_time' => 'common.updated_at',
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
                ->fetch('user/order/index.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function create(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $product_id = isset($queryParams['product_id'])
            ? $this->antiXss->xss_clean($queryParams['product_id'])
            : null;
        $redir = Cookie::get('redir');

        if ($redir !== '') {
            Cookie::set(['redir' => ''], time() - 1);
        }

        if ($product_id === null || $product_id === '') {
            return $response->withRedirect('/user/product');
        }

        $product = (new Product())->where('id', $product_id)->first();
        if ($product === null) {
            return $response->withRedirect('/user/product');
        }

        $product->content = json_decode($product->content);
        if (! is_object($product->content)) {
            return $response->withRedirect('/user/product');
        }

        $product->type_text = $product->type();
        $product->content->monthly_plan = $product->content->monthly_plan ?? false;
        $product->content->unlimited_bandwidth = $product->content->unlimited_bandwidth ?? false;
        $product->content->current_month_only = $product->content->current_month_only ?? false;

        return $response->write(
            $this->view()
                ->assign('product', $product)
                ->fetch('user/order/create.tpl')
        );
    }

    /**
     * @throws Exception
     */
    public function detail(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $id = $this->antiXss->xss_clean($args['id']);

        $order = (new Order())->where('user_id', $this->user->id)->where('id', $id)->first();

        if ($order === null) {
            return $response->withRedirect('/user/order');
        }

        $order->product_type_text = self::productType((string) $order->product_type);
        $order->status = self::orderStatus((string) $order->status);
        $order->create_time = Tools::toDateTime($order->create_time);
        $order->update_time = Tools::toDateTime($order->update_time);
        $order->content = json_decode($order->product_content);

        $invoice = (new Invoice())->where('order_id', $id)->first();

        if ($invoice === null) {
            return $response->withRedirect('/user/order');
        }

        $invoice->status = self::invoiceStatus((string) $invoice->status);
        $invoice->create_time = Tools::toDateTime($invoice->create_time);
        $invoice->update_time = Tools::toDateTime($invoice->update_time);
        $invoice->pay_time = Tools::toDateTime($invoice->pay_time);
        $invoice->content = json_decode($invoice->content);

        return $response->write(
            $this->view()
                ->assign('order', $order)
                ->assign('invoice', $invoice)
                ->fetch('user/order/view.tpl')
        );
    }

    public function process(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return match ($request->getParam('type')) {
            'product' => $this->product($request, $response, $args),
            'topup' => $this->topup($request, $response, $args),
            default => $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.order.unknown_type'),
            ]),
        };
    }

    public function product(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $coupon_raw = $this->antiXss->xss_clean($request->getParam('coupon'));
        $product_id = $this->antiXss->xss_clean($request->getParam('product_id'));

        return DB::connection()->transaction(function () use (
            $coupon_raw,
            $product_id,
            $response
        ): ResponseInterface {
            $user = (new User())->where('id', $this->user->id)->lockForUpdate()->first();
            $product = (new Product())->where('id', $product_id)->lockForUpdate()->first();

            if ($user === null || $product === null || (int) $product->status !== 1) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.product_unavailable'),
                ]);
            }

            if ($user->is_shadow_banned) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.product_unavailable'),
                ]);
            }

            $existingInvoice = PendingOrderService::reusableProductInvoice(
                (int) $user->id,
                (int) $product->id
            );
            if ($existingInvoice !== null) {
                return $response->withHeader(
                    'HX-Redirect',
                    '/user/invoice/' . $existingInvoice->id . '/view'
                );
            }

            if ((int) $product->stock === 0) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.product_unavailable'),
                ]);
            }

            if (PendingOrderService::limitReached((int) $user->id)) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.pending_limit'),
                ]);
            }

            $product_content = json_decode((string) $product->content);
            if (! is_object($product_content)) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.product_config_invalid'),
                ]);
            }

            if ((string) $product->type === 'time'
                && ! OrderEligibilityService::canPurchaseTimeProduct($user, $product_content)) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.class_mismatch'),
                ]);
            }

            if (property_exists($product_content, 'current_month_only')
                && $product_content->current_month_only === true
                && ! MonthlyPlanService::canBuyCurrentMonthAddon($user)
            ) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.current_month_addon_requires_plan'),
                ]);
            }

            $buy_price = InvoiceAccountingService::money($product->price);
            $discount = '0.00';
            $coupon = null;

            if ($coupon_raw !== '') {
                $coupon = (new UserCoupon())->where('code', $coupon_raw)->lockForUpdate()->first();
                if ($coupon === null) {
                    return $response->withJson([
                        'ret' => 0,
                        'msg' => FrontendI18n::trans('response.coupon.not_found_or_expired'),
                    ]);
                }

                $couponResult = CouponService::evaluate($coupon, $product, $user);
                if (! $couponResult['valid']) {
                    return $response->withJson([
                        'ret' => 0,
                        'msg' => FrontendI18n::trans($couponResult['message_key']),
                    ]);
                }

                $discount = $couponResult['discount'];
                $buy_price = $couponResult['total'];
            }

            $product_limit = json_decode((string) $product->limit);
            if (! is_object($product_limit)) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.product_limit_invalid'),
                ]);
            }

            if ((string) ($product_limit->class_required ?? '') !== ''
                && (int) $user->class < (int) $product_limit->class_required) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.class_insufficient'),
                ]);
            }

            if ((string) ($product_limit->node_group_required ?? '') !== ''
                && (int) $user->node_group !== (int) $product_limit->node_group_required) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.group_not_allowed'),
                ]);
            }

            if ((int) ($product_limit->new_user_required ?? 0) !== 0
                && (new Order())->where('user_id', $user->id)->where('status', '!=', 'cancelled')->exists()) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.new_users_only'),
                ]);
            }

            $isFree = bccomp($buy_price, '0.00', 2) === 0;
            $now = time();
            $order = new Order();
            $order->user_id = $user->id;
            $order->product_id = $product->id;
            $order->product_type = $product->type;
            $order->product_name = $product->name;
            $order->product_content = $product->content;
            $order->coupon = $coupon_raw;
            $order->price = $buy_price;
            $order->status = $isFree ? 'pending_activation' : 'pending_payment';
            $order->create_time = $now;
            $order->update_time = $now;
            $order->save();

            $invoice_content = [
                [
                    'content_id' => 0,
                    'name' => $product->name,
                    'price' => InvoiceAccountingService::money($product->price),
                ],
            ];

            if ($coupon !== null) {
                $invoice_content[] = [
                    'content_id' => 1,
                    'name' => '优惠码 / Coupon ' . $coupon_raw,
                    'price' => '-' . $discount,
                ];
            }

            $invoice = new Invoice();
            $invoice->user_id = $user->id;
            $invoice->order_id = $order->id;
            $invoice->content = json_encode($invoice_content);
            $invoice->price = $buy_price;
            $invoice->original_price = $buy_price;
            $invoice->paid_amount = $isFree ? $buy_price : '0.00';
            $invoice->refunded_amount = '0.00';
            $invoice->status = $isFree ? 'paid_gateway' : 'unpaid';
            $invoice->create_time = $now;
            $invoice->update_time = $now;
            $invoice->pay_time = $isFree ? $now : 0;
            $invoice->type = 'product';
            $invoice->save();

            if ((int) $product->stock > 0) {
                $product->stock = (int) $product->stock - 1;
            }

            $product->sale_count = (int) $product->sale_count + 1;
            $product->save();

            if ($coupon !== null) {
                $coupon->use_count = (int) $coupon->use_count + 1;
                $coupon->save();
            }

            return $response->withHeader('HX-Redirect', '/user/invoice/' . $invoice->id . '/view');
        });
    }

    public function topup(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $amount = $this->antiXss->xss_clean($request->getParam('amount'));
        $amount = is_numeric($amount) ? InvoiceAccountingService::money($amount) : null;

        if ($amount === null || bccomp($amount, '0.00', 2) <= 0) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.order.topup_invalid'),
            ]);
        }

        return DB::connection()->transaction(function () use ($amount, $response): ResponseInterface {
            $user = (new User())->where('id', $this->user->id)->lockForUpdate()->first();
            if ($user === null || $user->is_shadow_banned) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.topup_invalid'),
                ]);
            }

            if (PendingOrderService::limitReached((int) $user->id)) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => FrontendI18n::trans('response.order.pending_limit'),
                ]);
            }

            $now = time();
            $order = new Order();
            $order->user_id = $user->id;
            $order->product_id = 0;
            $order->product_type = 'topup';
            $order->product_name = '余额充值 / Balance top-up';
            $order->product_content = json_encode(['amount' => $amount]);
            $order->coupon = '';
            $order->price = $amount;
            $order->status = 'pending_payment';
            $order->create_time = $now;
            $order->update_time = $now;
            $order->save();

            $invoice = new Invoice();
            $invoice->user_id = $user->id;
            $invoice->order_id = $order->id;
            $invoice->content = json_encode([
                [
                    'content_id' => 0,
                    'name' => '余额充值 / Balance top-up',
                    'price' => $amount,
                ],
            ]);
            $invoice->price = $amount;
            $invoice->original_price = $amount;
            $invoice->paid_amount = '0.00';
            $invoice->refunded_amount = '0.00';
            $invoice->status = 'unpaid';
            $invoice->create_time = $now;
            $invoice->update_time = $now;
            $invoice->pay_time = 0;
            $invoice->type = 'topup';
            $invoice->save();

            return $response->withHeader('HX-Redirect', '/user/invoice/' . $invoice->id . '/view');
        });
    }

    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $orders = (new Order())->orderBy('id', 'desc')->where('user_id', $this->user->id)->get();

        foreach ($orders as $order) {
            $order->op = '<a class="btn btn-primary" href="/user/order/' . $order->id . '/view">'
                . FrontendI18n::trans('docs.view') . '</a>';

            if ($order->status === 'pending_payment') {
                $invoice = (new Invoice())->where('order_id', $order->id)->first();

                if ($invoice !== null) {
                    $order->op .= '
                    <a class="btn btn-red" href="/user/invoice/' . $invoice->id . '/view">'
                        . FrontendI18n::trans('payment.pay') . '</a>';
                }
            }

            $order->product_type = self::productType((string) $order->product_type);
            $order->product_name = htmlspecialchars(
                (string) $order->product_name,
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );
            $order->status = self::orderStatus((string) $order->status);
            $order->create_time = Tools::toDateTime($order->create_time);
            $order->update_time = Tools::toDateTime($order->update_time);
        }

        return $response->withJson([
            'orders' => $orders,
        ]);
    }

    private static function orderStatus(string $status): string
    {
        $key = in_array($status, [
            'pending_payment',
            'pending_activation',
            'activated',
            'expired',
            'cancelled',
        ], true) ? $status : 'unknown';

        return FrontendI18n::trans('order.status_values.' . $key);
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

    private static function productType(string $type): string
    {
        $key = in_array($type, ['tabp', 'time', 'bandwidth', 'topup'], true) ? $type : 'other';

        return FrontendI18n::trans('order.type_values.' . $key);
    }
}
