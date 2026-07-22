<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\Product;
use App\Models\UserCoupon;
use App\Services\CouponService;
use App\Services\FrontendI18n;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class CouponController extends BaseController
{
    public function check(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $coupon_raw = $this->antiXss->xss_clean($request->getParam('coupon'));
        $product_id = $this->antiXss->xss_clean($request->getParam('product_id'));
        $invalid_coupon_msg = FrontendI18n::trans('response.coupon.invalid');

        if ($coupon_raw === '') {
            return $response->withJson([
                'ret' => 0,
                'msg' => $invalid_coupon_msg,
            ]);
        }

        $coupon = (new UserCoupon())->where('code', $coupon_raw)->first();

        if ($coupon === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => $invalid_coupon_msg,
            ]);
        }

        $product = (new Product())->where('id', $product_id)->first();

        if ($product === null) {
            return $response->withJson([
                'ret' => 0,
                'msg' => $invalid_coupon_msg,
            ]);
        }

        $result = CouponService::evaluate($coupon, $product, $this->user);

        if (! $result['valid']) {
            return $response->withJson([
                'ret' => 0,
                'msg' => FrontendI18n::trans($result['message_key']),
            ]);
        }

        return $response->withJson([
            'ret' => 1,
            'msg' => FrontendI18n::trans($result['message_key']),
            'data' => [
                'coupon-code' => $coupon->code,
                'product-buy-discount' => $result['discount'],
                'product-buy-total' => $result['total'],
            ],
        ]);
    }
}
