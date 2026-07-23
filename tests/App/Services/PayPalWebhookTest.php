<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Gateway\PayPal;
use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase;
use Slim\Http\ServerRequest;

final class PayPalWebhookTest extends TestCase
{
    public function testVerificationPayloadContainsPayPalTransmissionHeadersAndWebhookId(): void
    {
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'https://panel.example.com/payment/notify/paypal')
            ->withHeader('PayPal-Auth-Algo', 'SHA256withRSA')
            ->withHeader('PayPal-Cert-Url', 'https://api.paypal.com/certificate')
            ->withHeader('PayPal-Transmission-Id', 'transmission-id')
            ->withHeader('PayPal-Transmission-Sig', 'signature')
            ->withHeader('PayPal-Transmission-Time', '2026-07-23T00:00:00Z');
        $event = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => ['status' => 'COMPLETED'],
        ];

        self::assertSame([
            'webhook_id' => 'webhook-id',
            'webhook_event' => $event,
            'auth_algo' => 'SHA256withRSA',
            'cert_url' => 'https://api.paypal.com/certificate',
            'transmission_id' => 'transmission-id',
            'transmission_sig' => 'signature',
            'transmission_time' => '2026-07-23T00:00:00Z',
        ], PayPal::buildWebhookVerificationPayload(
            new ServerRequest($request),
            $event,
            'webhook-id'
        ));
    }

    public function testVerificationPayloadRejectsMissingHeadersOrWebhookId(): void
    {
        $request = (new HttpFactory())
            ->createServerRequest('POST', 'https://panel.example.com/payment/notify/paypal');
        $event = ['event_type' => 'PAYMENT.CAPTURE.COMPLETED'];

        self::assertNull(PayPal::buildWebhookVerificationPayload(
            new ServerRequest($request),
            $event,
            'webhook-id'
        ));
        self::assertNull(PayPal::buildWebhookVerificationPayload(
            new ServerRequest($request->withHeader('PayPal-Auth-Algo', 'SHA256withRSA')),
            $event,
            ''
        ));
    }
}
