<?php

declare(strict_types=1);

namespace App\Services;

use App\Utils\ClassHelper;
use Psr\Http\Message\ResponseInterface;

final class Payment
{
    public static function getAllPaymentMap(): array
    {
        $payments = [];

        $helper = new ClassHelper();
        $class_list = $helper->getClassesByNamespace('\\App\\Services\\Gateway\\');

        foreach ($class_list as $class) {
            if (get_parent_class($class) === 'App\\Services\\Gateway\\Base') {
                $payments[] = $class;
            }
        }

        return $payments;
    }

    public static function getPaymentsEnabled(): array
    {
        return array_values(array_filter(Payment::getAllPaymentMap(), static function ($payment) {
            return $payment::_enable();
        }));
    }

    public static function getPaymentMap(): array
    {
        $result = [];

        foreach (self::getPaymentsEnabled() as $payment) {
            $result[$payment::_name()] = $payment;
        }

        return $result;
    }

    public static function getAllPaymentMapByName(): array
    {
        $result = [];

        foreach (self::getAllPaymentMap() as $payment) {
            $result[$payment::_name()] = $payment;
        }

        return $result;
    }

    public static function getPaymentByName($name, bool $enabledOnly = true): ?string
    {
        $all = $enabledOnly ? self::getPaymentMap() : self::getAllPaymentMapByName();

        return $all[$name] ?? null;
    }

    public static function notify($request, $response, $args): ResponseInterface
    {
        $payment = self::getPaymentByName($args['type'], false);

        if ($payment !== null) {
            $instance = new $payment();
            return $instance->notify($request, $response, $args);
        }

        return $response->withStatus(404);
    }

    public static function returnHTML($request, $response, $args): ResponseInterface
    {
        $payment = self::getPaymentByName($args['type'], false);

        if ($payment !== null) {
            $instance = new $payment();
            return $instance->getReturnHTML($request, $response, $args);
        }

        return $response->withStatus(404);
    }

    public static function purchase($request, $response, $args): ResponseInterface
    {
        $user = Auth::getUser();
        if ($user->is_shadow_banned
            || ! RateLimit::checkSafely('payment', (string) $user->id, true)
        ) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans('response.payment.rate_limited'),
            ])->withStatus(429);
        }

        $payment = self::getPaymentByName($args['type']);

        if ($payment !== null) {
            $instance = new $payment();
            return $instance->purchase($request, $response, $args);
        }

        return $response->withStatus(404);
    }
}
