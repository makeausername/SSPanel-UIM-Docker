<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Models\UserMoneyLog;
use function bcadd;
use function bccomp;
use function bcsub;
use function count;
use function is_array;
use function in_array;
use function json_decode;
use function json_encode;
use function time;

final class BalancePaymentService
{
    /**
     * @return array{status: 'error'|'paid'|'partial', message_key?: string}
     */
    public function pay(int $userId, int $invoiceId): array
    {
        return DB::connection()->transaction(static function () use ($userId, $invoiceId): array {
            $invoice = (new Invoice())
                ->where('id', $invoiceId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($invoice === null) {
                return ['status' => 'error', 'message_key' => 'response.payment.invoice_not_found'];
            }

            if (! in_array($invoice->status, ['unpaid', 'partially_paid'], true)) {
                return ['status' => 'error', 'message_key' => 'response.payment.invoice_processed'];
            }

            if ($invoice->type === 'topup') {
                return ['status' => 'error', 'message_key' => 'response.payment.balance_not_supported'];
            }

            $user = (new User())->where('id', $userId)->lockForUpdate()->first();

            if ($user === null) {
                return ['status' => 'error', 'message_key' => 'response.payment.user_not_found'];
            }

            if ($user->is_shadow_banned) {
                return ['status' => 'error', 'message_key' => 'response.payment.failed'];
            }

            $moneyBefore = self::money($user->money);
            InvoiceAccountingService::initialize($invoice);
            $invoiceDue = InvoiceAccountingService::remaining($invoice);

            if (bccomp($invoiceDue, '0.00', 2) <= 0) {
                return ['status' => 'error', 'message_key' => 'response.payment.invoice_amount_invalid'];
            }

            if (bccomp($moneyBefore, '0.00', 2) <= 0) {
                return ['status' => 'error', 'message_key' => 'response.payment.insufficient_balance'];
            }

            $fullyPaid = bccomp($moneyBefore, $invoiceDue, 2) >= 0;
            $paid = $fullyPaid ? $invoiceDue : $moneyBefore;
            InvoiceAccountingService::recordPayment($invoice, $paid);
            $moneyAfter = bcsub($moneyBefore, $paid, 2);
            $user->money = $moneyAfter;
            $user->save();

            (new UserMoneyLog())->add(
                (int) $user->id,
                (float) $moneyBefore,
                (float) $moneyAfter,
                -(float) $paid,
                '支付账单 / Invoice payment #' . $invoice->id
            );

            if ($fullyPaid) {
                $invoice->status = 'paid_balance';
            } else {
                $invoice->status = 'partially_paid';
                $invoice->price = bcsub($invoiceDue, $paid, 2);
                $content = json_decode((string) $invoice->content, true);
                $content = is_array($content) ? $content : [];
                $content[] = [
                    'content_id' => count($content),
                    'name' => '余额部分支付 / Partial balance payment',
                    'price' => '-' . $paid,
                ];
                $invoice->content = json_encode($content);
            }

            $invoice->update_time = time();
            $invoice->pay_time = time();
            $invoice->save();

            return ['status' => $fullyPaid ? 'paid' : 'partial'];
        });
    }

    private static function money(mixed $amount): string
    {
        return bcadd((string) $amount, '0.00', 2);
    }
}
