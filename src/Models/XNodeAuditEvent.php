<?php

declare(strict_types=1);

namespace App\Models;

final class XNodeAuditEvent extends Model
{
    protected $connection = 'default';
    protected $table = 'xnode_audit_events';
}
