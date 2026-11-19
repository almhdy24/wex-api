<?php

namespace App\Models;

use App\Entities\OrderEvent;
use CodeIgniter\Model;

class OrderEventModel extends Model
{
    protected $table            = 'order_events';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = OrderEvent::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'order_id',
        'from_status',
        'to_status',
        'actor_user_id',
        'note',
        'created_at',
    ];

    protected $useTimestamps = false;
}
