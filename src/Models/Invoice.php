<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\InvoiceRefundService;
use Illuminate\Database\Query\Builder;

/**
 * @property int    $id          账单ID
 * @property string $type        账单类型
 * @property int    $user_id     归属用户ID
 * @property string $order_id    订单ID
 * @property string $content     账单内容
 * @property float  $price       账单金额
 * @property string $status      账单状态
 * @property int    $create_time 创建时间
 * @property int    $update_time 更新时间
 * @property int    $pay_time    支付时间
 *
 * @mixin Builder
 */
final class Invoice extends Model
{
    protected $connection = 'default';
    protected $table = 'invoice';

    /**
     * 账单状态
     */
    public function status(): string
    {
        return match ($this->status) {
            'unpaid' => '未支付',
            'paid_gateway' => '已支付（支付网关）',
            'paid_balance' => '已支付（账户余额）',
            'paid_admin' => '已支付（管理员）',
            'cancelled' => '已取消',
            'refunded_balance' => '已退款（账户余额）',
            'partially_paid' => '部分支付',
            default => '未知',
        };
    }

    public function type(): string
    {
        return match ($this->type) {
            'product' => '商品',
            'topup' => '充值',
            default => '未知',
        };
    }

    public function refundToBalance(): void
    {
        (new InvoiceRefundService())->refund((int) $this->id);
    }
}
