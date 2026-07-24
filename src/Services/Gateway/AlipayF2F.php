<?php

declare(strict_types=1);

namespace App\Services\Gateway;

use Alipay\OpenAPISDK\Api\AlipayTradeApi;
use Alipay\OpenAPISDK\ApiException;
use Alipay\OpenAPISDK\Model\AlipayTradePrecreateModel;
use Alipay\OpenAPISDK\Model\AlipayTradeQueryModel;
use Alipay\OpenAPISDK\Util\AlipayConfigUtil;
use Alipay\OpenAPISDK\Util\AlipayLogger;
use Alipay\OpenAPISDK\Util\Model\AlipayConfig;
use App\Models\Config;
use App\Services\Auth;
use App\Services\View;
use Exception;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class AlipayF2F extends Base
{
    private AlipayConfig $alipayConfig;

    public function __construct()
    {
        parent::__construct();
        AlipayLogger::setNeedEnableLogger(false);
        $this->alipayConfig = new AlipayConfig();
        $this->alipayConfig->setAppid(Config::obtain('f2f_pay_app_id'));
        $this->alipayConfig->setPrivateKey(Config::obtain('f2f_pay_private_key'));
        $this->alipayConfig->setAlipayPublicKey(Config::obtain('f2f_pay_public_key'));
    }

    public static function _name(): string
    {
        return 'f2f';
    }

    public static function _readableName(): string
    {
        return 'Alipay F2F';
    }

    public static function _enable(): bool
    {
        return self::getActiveGateway('f2f');
    }

    /**
     * @throws Exception
     */
    public static function getPurchaseHTML(): string
    {
        return View::getSmarty()->fetch('gateway/f2f.tpl');
    }

    /**
     * @throws ApiException
     */
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

        if ($price <= 0) {
            return $response->withJson([
                'ret' => 0,
                'msg' => '非法的金额',
            ]);
        }

        $paylist = $this->createPaylist($user, $invoice);

        $f2f_pay_notify_url = Config::obtain('f2f_pay_notify_url');

        if ($f2f_pay_notify_url === '') {
            $notifyUrl = self::getCallbackUrl();
        } else {
            $notifyUrl = $f2f_pay_notify_url;
        }

        $api = $this->createApi();
        $aliRequest = new AlipayTradePrecreateModel();
        $aliRequest->setOutTradeNo($paylist->tradeno);
        $aliRequest->setTotalAmount($price);
        $aliRequest->setSubject($paylist->tradeno);
        $aliRequest->setNotifyUrl($notifyUrl);

        $aliResponse = $api->precreate($aliRequest);
        // 获取收款二维码内容
        $qrCode = $aliResponse->getQrCode();

        return $response->withJson([
            'ret' => 1,
            'qrcode' => $qrCode,
        ]);
    }

    /**
     * @throws ApiException
     */
    public function notify($request, $response, $args): ResponseInterface
    {
        $outTradeNo = isset($_POST['out_trade_no']) ? (string) $_POST['out_trade_no'] : '';
        if ($outTradeNo === '') {
            return $response->write('failed');
        }

        $api = $this->createApi();

        $aliRequest = new AlipayTradeQueryModel();
        $aliRequest->setOutTradeNo($outTradeNo);
        $aliResponse = $api->query($aliRequest);

        if (in_array($aliResponse->getTradeStatus(), ['TRADE_SUCCESS', 'TRADE_FINISHED'], true)) {
            $this->postPayment(
                (string) $aliResponse->getOutTradeNo(),
                $aliResponse->getTotalAmount(),
                'CNY',
                (string) $aliResponse->getTradeNo()
            );
            // https://opendocs.alipay.com/open/194/103296#%E5%BC%82%E6%AD%A5%E9%80%9A%E7%9F%A5%E7%89%B9%E6%80%A7
            return $response->write('success');
        }

        return $response->write('failed');
    }

    private function createApi(): AlipayTradeApi
    {
        $alipayTradeApi = new AlipayTradeApi(new Client([
            'connect_timeout' => 5,
            'timeout' => 15,
        ]));
        $alipayConfigUtil = new AlipayConfigUtil($this->alipayConfig);
        $alipayTradeApi->setAlipayConfigUtil($alipayConfigUtil);

        return $alipayTradeApi;
    }
}
