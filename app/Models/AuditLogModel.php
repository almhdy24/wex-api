<?php

namespace App\Models;

use App\Entities\AuditLog;
use CodeIgniter\Model;

class AuditLogModel extends Model
{
    protected $table            = 'audit_logs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = AuditLog::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'action',
        'entity',
        'entity_id',
        'details',
        'ip',
        'created_at',
    ];

    protected $useTimestamps = false;
}
