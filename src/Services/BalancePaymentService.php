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
     * @return array{status: 'error'|'paid'|'partial', message?: string}
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
                return ['status' => 'error', 'message' => '账单不存在'];
            }

            if (! in_array($invoice->status, ['unpaid', 'partially_paid'], true)) {
                return ['status' => 'error', 'message' => '账单已处理，请刷新页面'];
            }

            if ($invoice->type === 'topup') {
                return ['status' => 'error', 'message' => '该账单不支持使用余额支付'];
            }

            $user = (new User())->where('id', $userId)->lockForUpdate()->first();

            if ($user === null) {
                return ['status' => 'error', 'message' => '用户不存在'];
            }

            if ($user->is_shadow_banned) {
                return ['status' => 'error', 'message' => '支付失败，请稍后再试'];
            }

            $moneyBefore = self::money($user->money);
            $invoiceDue = self::money($invoice->price);

            if (bccomp($invoiceDue, '0.00', 2) <= 0) {
                return ['status' => 'error', 'message' => '账单金额无效'];
            }

            if (bccomp($moneyBefore, '0.00', 2) <= 0) {
                return ['status' => 'error', 'message' => '余额不足'];
            }

            $fullyPaid = bccomp($moneyBefore, $invoiceDue, 2) >= 0;
            $paid = $fullyPaid ? $invoiceDue : $moneyBefore;
            $moneyAfter = bcsub($moneyBefore, $paid, 2);
            $user->money = $moneyAfter;
            $user->save();

            (new UserMoneyLog())->add(
                (int) $user->id,
                (float) $moneyBefore,
                (float) $moneyAfter,
                -(float) $paid,
                '支付账单 #' . $invoice->id
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
                    'name' => '余额部分支付',
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
