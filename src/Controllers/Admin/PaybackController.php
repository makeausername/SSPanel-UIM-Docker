<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\InviteSubscriptionReward;
use App\Utils\Tools;
use Exception;
use Psr\Http\Message\ResponseInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

final class PaybackController extends BaseController
{
    private static array $details =
        [
            'field' => [
                'id' => '事件ID',
                'inviter_user_id' => '邀请人ID',
                'inviter_name' => '邀请人用户名',
                'invited_user_id' => '受邀用户ID',
                'user_name' => '受邀用户名',
                'qualifying_order_id' => '符合条件的订单ID',
                'invoice_id' => '账单ID',
                'applied_order_id' => '奖励应用订单ID',
                'product_name' => '购买套餐',
                'reward_days' => '奖励天数',
                'status_text' => '状态',
                'created_at' => '创建时间',
                'applied_at' => '应用时间',
            ],
        ];

    /**
     * 后台邀请记录页面
     *
     * @throws Exception
     */
    public function index(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        return $response->write(
            $this->view()
                ->assign('details', self::$details)
                ->fetch('admin/log/payback.tpl')
        );
    }

    /**
     * 后台登录记录页面 AJAX
     */
    public function ajax(ServerRequest $request, Response $response, array $args): ResponseInterface
    {
        $paybacks = (new InviteSubscriptionReward())->orderBy('id', 'desc')->get();

        foreach ($paybacks as $payback) {
            $payback->status_text = match ((string) $payback->status) {
                'applied' => '已应用',
                'pending' => '待应用',
                'cancelled' => '已取消',
                default => (string) $payback->status,
            };
            $payback->created_at = Tools::toDateTime((int) $payback->create_time);
            $payback->applied_at = (int) $payback->apply_time > 0
                ? Tools::toDateTime((int) $payback->apply_time)
                : '-';
        }

        return $response->withJson([
            'paybacks' => $paybacks,
        ]);
    }
}
