<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use App\Models\Config;
use App\Services\Auth;
use App\Services\View;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class Smogate extends Base
{
    public static function _name(): string
    {
        return 'smogate';
    }

    public static function _enable(): bool
    {
        return self::getActiveGateway('smogate');
    }

    public static function _readableName(): string
    {
        return '支付宝在线充值';
    }

    public function post(array $data): string|false
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, 'https://' . Config::obtain('smogate_app_id') . '.vless.org/v1/gateway/pay');
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 15);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['User-Agent: Smogate ' . Config::obtain('smogate_app_id')]);
        $data = curl_exec($curl);
        curl_close($curl);

        return $data;
    }

    public function prepareSign(array $data): string
    {
        ksort($data);
        return http_build_query($data);
    }

    public function sign(string $data): string
    {
        return strtolower(md5($data . Config::obtain('smogate_app_secret')));
    }

    public function verify(array $data, mixed $signature): bool
    {
        unset($data['sign']);
        $mySign = $this->sign($this->prepareSign($data));
        return is_string($signature) && hash_equals($mySign, $signature);
    }

    public function purchase(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $invoiceId = $this->antiXss->xss_clean($request->getParam('invoice_id'));
        $user = Auth::getUser();
        $invoice = $this->getPayableInvoiceForUser($invoiceId, $user);

        if ($invoice === null || (float) $invoice->price <= 0) {
            return $response->withJson([
                'ret' => 0,
                'msg' => 'Invoice not found or not payable',
            ]);
        }

        $paylist = $this->createPaylist($user, $invoice);

        $data = [
            'method' => 'alipay',
            'app_id' => Config::obtain('smogate_app_id'),
            'out_trade_no' => $paylist->tradeno,
            'total_amount' => (int) round((float) $paylist->total * 100),
            'notify_url' => self::getCallbackUrl(),
        ];
        $this->setExpectedProviderSettlement($paylist, $data['total_amount'], 'CNY');
        $params = $this->prepareSign($data);
        $data['sign'] = $this->sign($params);
        $rawResult = $this->post($data);
        $result = is_string($rawResult) ? json_decode($rawResult, true) : null;

        if (! is_array($result)) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '支付网关连接失败',
            ]);
        }

        if (isset($result['errors'])) {
            return $response->withJson([
                'ret' => 0,
                'msg' => $result['errors'][array_keys($result['errors'])[0]],
            ]);
        }
        if (isset($result['message'])) {
            return $response->withJson([
                'ret' => 0,
                'msg' => $result['message'],
            ]);
        }

        return $response->withJson([
            'ret' => 1,
            'type' => $this->isMobile() ? 'url' : 'qrcode',
            'qrcode' => $result['data'],
            'amount' => $paylist->total,
            'pid' => $paylist->tradeno,
        ]);
    }

    public function notify(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $params = $request->getParams();

        if (! $this->verify($params, $request->getParam('sign'))) {
            return $response->withStatus(400)->write('FAIL');
        }

        $status = (string) ($params['trade_status'] ?? $params['status'] ?? '');
        if ($status !== '' && ! in_array(strtoupper($status), ['SUCCESS', 'PAID', 'TRADE_SUCCESS'], true)) {
            return $response->withStatus(400)->write('FAIL');
        }

        $this->postPayment(
            (string) $request->getParam('out_trade_no'),
            $params['total_amount'] ?? null,
            'CNY',
            isset($params['trade_no']) ? (string) $params['trade_no'] : null
        );

        return $response->write('SUCCESS');
    }

    public static function getPurchaseHTML(): string
    {
        return View::getSmarty()->fetch('gateway/smogate.tpl');
    }

    private function isMobile(): bool
    {
        return strpos(strtolower($_SERVER['HTTP_USER_AGENT'] ?? ''), 'mobile') !== false;
    }
}
