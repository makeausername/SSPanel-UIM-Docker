<?php

declare(strict_types=1);

namespace App\Models;

final class ClientSession extends Model
{
    protected $connection = 'default';
    protected $table = 'client_sessions';
}
