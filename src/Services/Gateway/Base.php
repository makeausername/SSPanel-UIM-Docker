<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Models\Config;
use App\Models\Invoice;
use App\Models\Paylist;
use App\Models\User;
use App\Models\UserMoneyLog;
use App\Services\DB;
use App\Services\InvoiceAccountingService;
use App\Utils\Tools;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use voku\helper\AntiXSS;

use function bcadd;
use function bccomp;
use function bcsub;
use function get_called_class;
use function in_array;
use function is_numeric;
use function json_decode;
use function json_encode;
use function strlen;
use function time;
use function trim;
use function strtoupper;

abstract class Base
{
    protected AntiXSS $antiXss;

    public function __construct()
    {
        $this->antiXss = new AntiXSS();
    }

    abstract public function purchase(ServerRequest $request, Response $response, array $args): ResponseInterface;

    abstract public function notify(ServerRequest $request, Response $response, array $args): ResponseInterface;

    /**
     * 支付网关的 codeName
     */
    abstract public static function _name(): string;

    /**
     * 是否启用支付网关
     */
    abstract public static function _enable(): bool;

    /**
     * 显示给用户的名称
     */
    abstract public static function _readableName(): string;

    public function getReturnHTML(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write('ok');
    }

    abstract public static function getPurchaseHTML(): string;

    public function postPayment(
        string $trade_no,
        mixed $providerAmount = null,
        ?string $providerCurrency = null,
        ?string $providerTransactionId = null
    ): void
    {
        DB::connection()->transaction(function () use (
            $trade_no,
            $providerAmount,
            $providerCurrency,
            $providerTransactionId
        ): void {
            $paylist = (new Paylist())
                ->where('tradeno', $trade_no)
                ->lockForUpdate()
                ->first();

            if ($paylist === null) {
                throw new RuntimeException('Payment record not found.');
            }

            $this->validateProviderSettlement(
                $paylist,
                $providerAmount,
                $providerCurrency,
                $providerTransactionId
            );

            $invoice = (new Invoice())
                ->where('id', $paylist->invoice_id)
                ->lockForUpdate()
                ->first();
            $user = (new User())
                ->where('id', $paylist->userid)
                ->lockForUpdate()
                ->first();

            if ($invoice === null || $user === null || (int) $invoice->user_id !== (int) $user->id) {
                throw new RuntimeException('Payment ownership validation failed.');
            }

            InvoiceAccountingService::initialize($invoice);

            if ((int) $paylist->status === 1) {
                return;
            }

            $paidAmount = self::money($paylist->total);

            if (bccomp($paidAmount, '0.00', 2) <= 0) {
                throw new RuntimeException('Payment amount must be positive.');
            }

            $paylist->datetime = time();
            $paylist->status = 1;

            if (! in_array($invoice->status, ['unpaid', 'partially_paid'], true)) {
                $paylist->save();
                $this->creditBalance($user, $paidAmount, (int) $invoice->id, 'Duplicate invoice payment');

                return;
            }

            $invoiceDue = InvoiceAccountingService::remaining($invoice);
            $comparison = bccomp($paidAmount, $invoiceDue, 2);
            InvoiceAccountingService::recordPayment($invoice, $paidAmount);
            $invoice->update_time = time();
            $invoice->pay_time = time();

            if ($comparison < 0) {
                $invoice->price = bcsub($invoiceDue, $paidAmount, 2);
                $invoice->status = 'partially_paid';
                $content = json_decode((string) $invoice->content, true);
                $content = is_array($content) ? $content : [];
                $content[] = [
                    'content_id' => count($content),
                    'name' => 'Gateway partial payment',
                    'price' => '-' . $paidAmount,
                ];
                $invoice->content = json_encode($content);
            } else {
                $invoice->status = 'paid_gateway';
            }

            $paylist->save();
            $invoice->save();

            if ($comparison > 0) {
                $this->creditBalance(
                    $user,
                    bcsub($paidAmount, $invoiceDue, 2),
                    (int) $invoice->id,
                    'Overpayment'
                );
            }

            return;
        });
    }

    protected function getPayableInvoiceForUser(mixed $invoiceId, User $user): ?Invoice
    {
        if (! is_numeric($invoiceId) || (int) $invoiceId <= 0) {
            return null;
        }

        return (new Invoice())
            ->where('id', (int) $invoiceId)
            ->where('user_id', (int) $user->id)
            ->whereIn('status', ['unpaid', 'partially_paid'])
            ->first();
    }

    protected function createPaylist(User $user, Invoice $invoice): Paylist
    {
        $paylist = new Paylist();
        $paylist->userid = $user->id;
        $paylist->total = self::money($invoice->price);
        $paylist->status = 0;
        $paylist->invoice_id = $invoice->id;
        $paylist->tradeno = self::generateGuid();
        $paylist->gateway = static::_readableName();
        $paylist->expected_provider_amount = self::providerMoney($paylist->total);
        $paylist->expected_provider_currency = 'CNY';
        $paylist->save();

        return $paylist;
    }

    protected function setExpectedProviderSettlement(Paylist $paylist, mixed $amount, string $currency): void
    {
        $paylist->expected_provider_amount = self::providerMoney($amount);
        $paylist->expected_provider_currency = strtoupper($currency);
        $paylist->save();
    }

    protected function getInvoiceReturnUrl(Invoice $invoice): string
    {
        return $_ENV['baseUrl'] . '/user/invoice/' . $invoice->id . '/view';
    }

    private static function money(mixed $amount): string
    {
        return bcadd((string) $amount, '0.00', 2);
    }

    private static function providerMoney(mixed $amount): string
    {
        return bcadd((string) $amount, '0.00000000', 8);
    }

    private function validateProviderSettlement(
        Paylist $paylist,
        mixed $providerAmount,
        ?string $providerCurrency,
        ?string $providerTransactionId
    ): void {
        if ($paylist->expected_provider_amount !== null) {
            if ($providerAmount === null || ! is_numeric($providerAmount) || $providerCurrency === null) {
                throw new RuntimeException('Provider settlement amount and currency are required.');
            }

            $providerCurrency = strtoupper(trim($providerCurrency));
            if ($providerCurrency === '') {
                throw new RuntimeException('Provider settlement currency is required.');
            }

            if (bccomp(
                self::providerMoney($providerAmount),
                self::providerMoney($paylist->expected_provider_amount),
                8
            ) !== 0 || $providerCurrency !== strtoupper((string) $paylist->expected_provider_currency)) {
                throw new RuntimeException('Provider settlement amount or currency mismatch.');
            }
        }

        if ($providerTransactionId !== null && $providerTransactionId !== '') {
            $providerTransactionId = trim($providerTransactionId);
            if ($providerTransactionId === '' || strlen($providerTransactionId) > 255) {
                throw new RuntimeException('Provider transaction identifier is invalid.');
            }

            if (
                $paylist->provider_transaction_id !== null
                && ! hash_equals((string) $paylist->provider_transaction_id, $providerTransactionId)
            ) {
                throw new RuntimeException('Provider transaction identifier mismatch.');
            }

            $paylist->provider_transaction_id = $providerTransactionId;
        }
    }

    private function creditBalance(User $user, string $amount, int $invoiceId, string $reason): void
    {
        if (bccomp($amount, '0.00', 2) <= 0) {
            return;
        }

        $moneyBefore = self::money($user->money);
        $moneyAfter = bcadd($moneyBefore, $amount, 2);
        $user->money = $moneyAfter;
        $user->save();
        (new UserMoneyLog())->add(
            (int) $user->id,
            (float) $moneyBefore,
            (float) $moneyAfter,
            (float) $amount,
            $reason . ' invoice #' . $invoiceId
        );
    }

    public static function generateGuid(): string
    {
        return Tools::genRandomChar();
    }

    protected static function getCallbackUrl(): string
    {
        return $_ENV['baseUrl'] . '/payment/notify/' . get_called_class()::_name();
    }

    protected static function getUserReturnUrl(): string
    {
        return $_ENV['baseUrl'] . '/user/payment/return/' . get_called_class()::_name();
    }

    protected static function getActiveGateway(string $key): bool
    {
        $payment_gateways = (new Config())->where('item', 'payment_gateway')->first();
        $active_gateways = json_decode($payment_gateways->value);

        if (in_array($key, $active_gateways)) {
            return true;
        }

        return false;
    }
}
