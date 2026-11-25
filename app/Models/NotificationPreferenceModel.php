<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationPreferenceModel extends Model
{
    protected $table            = 'notification_preferences';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'order_updates',
        'payment_updates',
        'price_alerts',
        'announcements',
        'new_orders',
        'receipts',
        'low_stock',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;
}
