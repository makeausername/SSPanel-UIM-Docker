<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Setting;

use App\Controllers\BaseController;
use App\Models\Config;
use App\Services\Payment;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Smarty\Exception;
use Srmklive\PayPal\Services\PayPal;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;
use Stripe\WebhookEndpoint;
use Throwable;

final class BillingController extends BaseController
{
    private array $update_field;
    private array $settings;

    public function __construct()
    {
        parent::__construct();
        $this->update_field = Config::getItemListByClass('billing');
        $this->settings = Config::getAdminClass('billing');
    }

    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('update_field', $this->update_field)
                ->assign('settings', $this->settings)
                ->assign('payment_gateways', $this->returnGatewaysList())
                ->assign('active_payment_gateway', $this->returnActiveGateways())
                ->fetch('admin/setting/billing.tpl')
        );
    }

    public function save(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $active_gateway = [];

        foreach ($this->returnGatewaysList() as $key => $value) {
            if ($request->getParam($value) === 'true') {
                $active_gateway[] = $value;
            }
        }

        if (! Config::set('payment_gateway', $active_gateway)) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '保存支付网关时出错',
            ]);
        }

        foreach ($this->update_field as $item) {
            if (in_array($item, ['payment_gateway', 'paypal_webhook_id'], true)) {
                continue;
            }

            if (! Config::setFromAdmin($item, $request->getParam($item))) {
                return $response->withJson([
                    'ret' => 0,
                    'msg' => '保存 ' . $item . ' 时出错',
                ]);
            }
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '保存成功',
        ]);
    }

    public function setStripeWebhook(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $stripe_api_key = $this->secretFromRequest($request, 'stripe_api_key');

        Stripe::setApiKey($stripe_api_key);

        try {
            WebhookEndpoint::create([
                'url' => $_ENV['baseUrl'] . '/payment/notify/stripe',
                'enabled_events' => [
                    'payment_intent.succeeded',
                ],
            ]);
        } catch (ApiErrorException) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '设置 Stripe Webhook 失败',
            ]);
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '设置 Stripe Webhook 成功',
        ]);
    }

    public function setPaypalWebhook(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $paypal_client_id = trim((string) $request->getParam('paypal_client_id'));
        $paypal_client_secret = $this->secretFromRequest($request, 'paypal_client_secret');
        $paypal_mode = (string) $request->getParam('paypal_mode');
        $paypal_mode = in_array($paypal_mode, ['live', 'sandbox'], true)
            ? $paypal_mode
            : (string) Config::obtain('paypal_mode');

        $gateway_config = [
            'mode' => $paypal_mode,
            'sandbox' => [
                'client_id' => $paypal_client_id,
                'client_secret' => $paypal_client_secret,
            ],
            'live' => [
                'client_id' => $paypal_client_id,
                'client_secret' => $paypal_client_secret,
            ],
            'payment_action' => 'Sale',
            'currency' => 'USD',
            'notify_url' => '',
            'locale' => 'en_US',
            'validate_ssl' => true,
        ];

        try {
            $pp = new PayPal($gateway_config);
            $pp->getAccessToken();
            $webhook_url = rtrim((string) $_ENV['baseUrl'], '/') . '/payment/notify/paypal';
            $webhook_id = '';
            $webhooks = $pp->listWebHooks();

            $matching_webhooks = is_array($webhooks) && is_array($webhooks['webhooks'] ?? null)
                ? $webhooks['webhooks']
                : [];

            foreach ($matching_webhooks as $webhook) {
                if (! is_array($webhook) || ($webhook['url'] ?? '') !== $webhook_url) {
                    continue;
                }

                $event_names = array_column(
                    is_array($webhook['event_types'] ?? null) ? $webhook['event_types'] : [],
                    'name'
                );
                if (in_array('PAYMENT.CAPTURE.COMPLETED', $event_names, true)
                    && isset($webhook['id'])
                ) {
                    $webhook_id = trim((string) $webhook['id']);
                    break;
                }
            }

            if ($webhook_id === '') {
                $webhook = $pp->createWebHook($webhook_url, ['PAYMENT.CAPTURE.COMPLETED']);
                $webhook_id = trim((string) ($webhook['id'] ?? ''));
            }

            if ($webhook_id === '' || ! Config::set('paypal_webhook_id', $webhook_id)) {
                throw new \RuntimeException('PayPal webhook ID could not be persisted.');
            }
        } catch (Throwable $e) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '设置 PayPal Webhook 失败',
            ]);
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => '设置 PayPal Webhook 成功',
        ]);
    }

    public function returnGatewaysList(): array
    {
        $result = [];

        foreach (Payment::getAllPaymentMap() as $payment) {
            $result[$payment::_readableName()] = $payment::_name();
        }

        return $result;
    }

    public function returnActiveGateways(): ?array
    {
        return Config::obtain('payment_gateway');
    }

    private function secretFromRequest(ServerRequest $request, string $item): string
    {
        $value = trim((string) $request->getParam($item));

        return $value === '' || $value === Config::SECRET_MASK
            ? (string) Config::obtain($item)
            : $value;
    }
}
