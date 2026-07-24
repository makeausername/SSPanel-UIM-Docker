<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Models\Config;
use App\Services\Auth;
use App\Services\Exchange;
use App\Services\View;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use RedisException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;
use function in_array;
use function strtolower;
use function strtoupper;

final class Stripe extends Base
{
    public static function _name(): string
    {
        return 'stripe';
    }

    public static function _enable(): bool
    {
        return self::getActiveGateway('stripe');
    }

    public static function _readableName(): string
    {
        return 'Stripe';
    }

    public function purchase(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $invoiceId = $this->antiXss->xss_clean($request->getParam('invoice_id'));
        $user = Auth::getUser();
        $invoice = $this->getPayableInvoiceForUser($invoiceId, $user);

        if ($invoice === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => 'Invoice not found',
            ]);
        }

        $price = $invoice->price;

        if ($price < Config::obtain('stripe_min_recharge') ||
            $price > Config::obtain('stripe_max_recharge')
        ) {
            return $response->withJson([
                'ret' => 0,
                'msg' => 'Price out of range',
            ]);
        }

        $paylist = $this->createPaylist($user, $invoice);
        $stripeCurrency = strtoupper((string) Config::obtain('stripe_currency'));

        try {
            $exchangeAmount = (new Exchange())->exchange((float) $price, 'CNY', $stripeCurrency);
        } catch (GuzzleException|RedisException|UnexpectedValueException) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '汇率获取失败',
            ]);
        }
        // https://docs.stripe.com/currencies?presentment-currency=US#zero-decimal
        $unitAmount = in_array(
            $stripeCurrency,
            ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW',
                'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF',
            ],
            true
        ) ? (int) round($exchangeAmount) : (int) round($exchangeAmount * 100);

        if ($unitAmount <= 0) {
            return $response->withJson([
                'ret' => 0,
                'msg' => 'Price out of range',
            ]);
        }

        $this->setExpectedProviderSettlement($paylist, $unitAmount, $stripeCurrency);

        $stripe = new StripeClient(Config::obtain('stripe_api_key'));
        $session = null;

        try {
            $session = $stripe->checkout->sessions->create([
                'customer_email' => $user->email,
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => strtolower($stripeCurrency),
                            'product_data' => [
                                'name' => 'Invoice #' . $invoice->id,
                            ],
                            'unit_amount' => $unitAmount,
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'payment_intent_data' => [
                    'metadata' => [
                        'trade_no' => $paylist->tradeno,
                    ],
                ],
                'success_url' => $this->getInvoiceReturnUrl($invoice),
                'cancel_url' => $this->getInvoiceReturnUrl($invoice),
            ]);
        } catch (ApiErrorException) {
            return $response->withJson([
                'ret' => 0,
                'msg' => 'Stripe API error',
            ]);
        }

        return $response->withHeader('HX-Redirect', $session->url);
    }

    public function notify(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        try {
            $event = Webhook::constructEvent(
                $request->getBody()->getContents(),
                $request->getHeaderLine('Stripe-Signature'),
                Config::obtain('stripe_endpoint_secret')
            );
        } catch (UnexpectedValueException) {
            return $response->withStatus(400)->withJson([
                'ret' => 0,
                'msg' => 'Unexpected Value error',
            ]);
        } catch (SignatureVerificationException) {
            return $response->withStatus(400)->withJson([
                'ret' => 0,
                'msg' => 'Signature Verification error',
            ]);
        }

        $payment_intent = $event->data->object;

        if ($event->type === 'payment_intent.succeeded' && $payment_intent->status === 'succeeded') {
            $this->postPayment(
                (string) $payment_intent->metadata->trade_no,
                $payment_intent->amount_received,
                (string) $payment_intent->currency,
                (string) $payment_intent->id
            );

            return $response->withStatus(204);
        }

        return $response->withStatus(400);
    }

    /**
     * @throws Exception
     */
    public static function getPurchaseHTML(): string
    {
        return View::getSmarty()->fetch('gateway/stripe.tpl');
    }
}
