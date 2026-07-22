<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Query\Builder;

/**
 * @property int         $id
 * @property int         $inviter_user_id
 * @property int         $invited_user_id
 * @property int         $qualifying_order_id
 * @property int         $invoice_id
 * @property int         $applied_order_id
 * @property string      $product_sku
 * @property int         $reward_days
 * @property string      $status
 * @property string|null $expiry_before
 * @property string|null $expiry_after
 * @property int         $create_time
 * @property int         $apply_time
 *
 * @mixin Builder
 */
final class InviteSubscriptionReward extends Model
{
    protected $connection = 'default';
    protected $table = 'invite_subscription_reward';
    protected $appends = ['inviter_name', 'user_name', 'product_name'];

    public function getInviterNameAttribute(): string
    {
        return (new User())->where('id', $this->inviter_user_id)->value('user_name') ?? 'Deleted user';
    }

    public function getUserNameAttribute(): string
    {
        return (new User())->where('id', $this->invited_user_id)->value('user_name') ?? 'Deleted user';
    }

    public function getProductNameAttribute(): string
    {
        return match ($this->product_sku) {
            'mini' => 'Mini',
            'lite' => 'Lite',
            'basic' => 'Basic',
            'standard' => 'Standard',
            'pro' => 'Pro',
            'ultra' => 'Ultra',
            default => strtoupper((string) $this->product_sku),
        };
    }
}
