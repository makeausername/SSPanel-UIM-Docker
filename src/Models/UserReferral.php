<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Query\Builder;

/**
 * @property int    $invited_user_id
 * @property int    $inviter_user_id
 * @property string $invite_code
 * @property int    $create_time
 *
 * @mixin Builder
 */
final class UserReferral extends Model
{
    public $incrementing = false;
    protected $connection = 'default';
    protected $primaryKey = 'invited_user_id';
    protected $table = 'user_referral';
}
