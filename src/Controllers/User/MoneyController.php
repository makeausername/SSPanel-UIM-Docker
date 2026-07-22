<?php

declare(strict_types=1);

namespace App\Controllers\User;

use App\Controllers\BaseController;
use App\Models\GiftCard;
use App\Models\User;
use App\Models\UserMoneyLog;
use App\Services\DB;
use App\Services\FrontendI18n;
use App\Services\InvoiceAccountingService;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use function time;

final class MoneyController extends BaseController
{
    /**
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $user = $this->user;
        $moneylogs = (new UserMoneyLog())->where('user_id', $user->id)->orderBy('id', 'desc')->get();

        foreach ($moneylogs as $moneylog) {
            $moneylog->create_time = Tools::toDateTime($moneylog->create_time);
        }

        $moneylog_count = $moneylogs->count();

        return $response->write(
            $this->view()
                ->assign('moneylogs', $moneylogs)
                ->assign('moneylog_count', $moneylog_count)
                ->fetch('user/money.tpl')
        );
    }

    public function applyGiftCard(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $giftcard_raw = $this->antiXss->xss_clean($request->getParam('giftcard'));
        $result = DB::connection()->transaction(function () use ($giftcard_raw): array {
            $giftcard = (new GiftCard())->where('card', $giftcard_raw)->lockForUpdate()->first();

            if ($giftcard === null || (int) $giftcard->status !== 0) {
                return ['ret' => 0, 'msg' => FrontendI18n::trans('response.giftcard_invalid')];
            }

            $user = (new User())->where('id', $this->user->id)->lockForUpdate()->first();

            if ($user === null || $user->is_shadow_banned) {
                return ['ret' => 0, 'msg' => FrontendI18n::trans('response.giftcard_invalid')];
            }

            $giftcard->status = 1;
            $giftcard->use_time = time();
            $giftcard->use_user = $user->id;
            $giftcard->save();

            $moneyBefore = InvoiceAccountingService::money($user->money);
            $moneyAfter = bcadd($moneyBefore, InvoiceAccountingService::money($giftcard->balance), 2);
            $user->money = $moneyAfter;
            $user->save();

            (new UserMoneyLog())->add(
                (int) $user->id,
                (float) $moneyBefore,
                (float) $moneyAfter,
                (float) $giftcard->balance,
                '礼品卡充值 / Gift card top-up ' . $giftcard->card
            );

            return ['ret' => 1, 'msg' => FrontendI18n::trans('response.topup_success')];
        });

        return $response->withJson($result);
    }
}
