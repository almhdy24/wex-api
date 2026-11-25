<?php

namespace App\Models;

use App\Entities\Order;
use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table            = 'orders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Order::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'order_number',
        'user_id',
        'status',
        'delivery_type',
        'shipping_zone_id',
        'delivery_address',
        'delivery_phone',
        'customer_note',
        'rate_sdg_snapshot',
        'app_discount_percent_snapshot',
        'subtotal_aed',
        'subtotal_sdg',
        'discount_sdg',
        'delivery_fee_sdg',
        'total_sdg',
        'lock_expires_at',
        'courier_name',
        'courier_phone',
        'cancelled_reason',
        'ticker_hidden',
        'idempotency_key',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
