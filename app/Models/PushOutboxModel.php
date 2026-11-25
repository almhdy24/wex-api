<?php

namespace App\Models;

use CodeIgniter\Model;

class PushOutboxModel extends Model
{
    protected $table            = 'push_outbox';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'notification_id',
        'subscription_id',
        'status',
        'attempts',
        'next_attempt_at',
        'error',
        'payload_json',
        'created_at',
        'sent_at',
    ];

    protected $useTimestamps = false;
}
