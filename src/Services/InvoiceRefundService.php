<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use App\Models\User;
use App\Models\UserMoneyLog;
use RuntimeException;
use function bcadd;
use function bccomp;
use function count;
use function in_array;
use function is_array;
use function json_decode;
use function json_encode;
use function time;

final class InvoiceRefundService
{
    public function refund(int $invoiceId): bool
    {
        return DB::connection()->transaction(function () use ($invoiceId): bool {
            $invoice = (new Invoice())->where('id', $invoiceId)->lockForUpdate()->first();

            if ($invoice === null) {
                return false;
            }

            $user = (new User())->where('id', $invoice->user_id)->lockForUpdate()->first();

            if ($user === null) {
                throw new RuntimeException('Invoice user not found.');
            }

            return $this->refundLocked($invoice, $user);
        });
    }

    public function refundLocked(Invoice $invoice, User $user): bool
    {
        if (! in_array(
            $invoice->status,
            ['partially_paid', 'paid_gateway', 'paid_balance', 'paid_admin'],
            true
        )) {
            return false;
        }

        $refund = InvoiceAccountingService::refundable($invoice);

        if (bccomp($refund, '0.00', 2) <= 0) {
            return false;
        }

        $before = InvoiceAccountingService::money($user->money);
        $after = bcadd($before, $refund, 2);
        $user->money = $after;
        $user->save();

        (new UserMoneyLog())->add(
            (int) $user->id,
            (float) $before,
            (float) $after,
            (float) $refund,
            '账单 #' . $invoice->id . ' 退款至账户余额'
        );

        $content = json_decode((string) $invoice->content, true);
        $content = is_array($content) ? $content : [];
        $content[] = [
            'content_id' => count($content),
            'name' => '退款至账户余额',
            'price' => '-' . $refund,
        ];

        $invoice->content = json_encode($content);
        $invoice->refunded_amount = bcadd(
            InvoiceAccountingService::money($invoice->refunded_amount),
            $refund,
            2
        );
        $invoice->status = 'refunded_balance';
        $invoice->update_time = time();
        $invoice->save();

        return true;
    }
}
