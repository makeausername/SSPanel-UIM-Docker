<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\InviteSubscriptionReward;
use App\Services\DataTableRequest;
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
        $table = DataTableRequest::from(
            $request,
            ['id', 'inviter_user_id', 'invited_user_id', 'qualifying_order_id', 'invoice_id', 'applied_order_id', 'reward_days', 'status', 'create_time', 'apply_time'],
            'id'
        );
        $query = InviteSubscriptionReward::query();
        $total = (new InviteSubscriptionReward())->count();
        if ($table->search !== '') {
            $query->where(static function ($query) use ($table): void {
                $query->where('id', $table->search)
                    ->orWhere('inviter_user_id', $table->search)
                    ->orWhere('invited_user_id', $table->search)
                    ->orWhere('invoice_id', $table->search)
                    ->orWhere('status', 'LIKE', "%{$table->search}%");
            });
        }
        $filtered = $query->count();
        $query->orderBy($table->orderBy, $table->orderDirection);
        if ($table->orderBy !== 'id') {
            $query->orderBy('id', 'desc');
        }
        $paybacks = $query->paginate($table->length, '*', '', $table->page);

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
            'draw' => $table->draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'paybacks' => $paybacks,
        ]);
    }
}
