<?php

namespace App\Models;

use App\Entities\Notification;
use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Notification::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'type',
        'title',
        'body',
        'order_id',
        'data_json',
        'channel_flags',
        'read_at',
        'created_at',
    ];

    protected $useTimestamps = false;
}
