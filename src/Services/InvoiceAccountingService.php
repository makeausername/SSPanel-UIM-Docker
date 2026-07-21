<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Invoice;
use function bcadd;
use function bccomp;
use function bcsub;
use function is_array;
use function json_decode;
use function ltrim;

final class InvoiceAccountingService
{
    public static function initialize(Invoice $invoice): void
    {
        if ($invoice->original_price !== null) {
            $invoice->original_price = self::money($invoice->original_price);
            $invoice->paid_amount = self::money($invoice->paid_amount ?? 0);
            $invoice->refunded_amount = self::money($invoice->refunded_amount ?? 0);

            return;
        }

        $current = self::money($invoice->price);
        $partial = self::legacyPartialPayments((string) $invoice->content);
        $original = bcadd($current, $partial, 2);
        $invoice->original_price = $original;
        $invoice->paid_amount = match ((string) $invoice->status) {
            'partially_paid' => $partial,
            'paid_gateway', 'paid_balance', 'paid_admin', 'refunded_balance' => $original,
            default => '0.00',
        };
        $invoice->refunded_amount = $invoice->status === 'refunded_balance'
            ? self::money($invoice->paid_amount)
            : '0.00';
    }

    public static function remaining(Invoice $invoice): string
    {
        self::initialize($invoice);
        $remaining = bcsub(
            self::money($invoice->original_price),
            self::money($invoice->paid_amount),
            2
        );

        return bccomp($remaining, '0.00', 2) > 0 ? $remaining : '0.00';
    }

    public static function recordPayment(Invoice $invoice, string $amount): string
    {
        self::initialize($invoice);
        $remaining = self::remaining($invoice);
        $applied = bccomp($amount, $remaining, 2) > 0 ? $remaining : self::money($amount);
        $invoice->paid_amount = bcadd(self::money($invoice->paid_amount), $applied, 2);

        return $applied;
    }

    public static function refundable(Invoice $invoice): string
    {
        self::initialize($invoice);
        $amount = bcsub(
            self::money($invoice->paid_amount),
            self::money($invoice->refunded_amount),
            2
        );

        return bccomp($amount, '0.00', 2) > 0 ? $amount : '0.00';
    }

    public static function money(mixed $amount): string
    {
        return bcadd((string) $amount, '0.00', 2);
    }

    private static function legacyPartialPayments(string $content): string
    {
        $entries = json_decode($content, true);
        $partial = '0.00';

        if (! is_array($entries)) {
            return $partial;
        }

        foreach ($entries as $entry) {
            if (! is_array($entry) || ! in_array(
                (string) ($entry['name'] ?? ''),
                ['Gateway partial payment', '余额部分支付'],
                true
            )) {
                continue;
            }

            $amount = ltrim((string) ($entry['price'] ?? '0'), '-');
            if (is_numeric($amount)) {
                $partial = bcadd($partial, self::money($amount), 2);
            }
        }

        return $partial;
    }
}
