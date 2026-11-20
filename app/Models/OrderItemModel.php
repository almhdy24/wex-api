<?php

namespace App\Models;

use App\Entities\OrderItem;
use CodeIgniter\Model;

class OrderItemModel extends Model
{
    protected $table            = 'order_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = OrderItem::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'order_id',
        'product_id',
        'product_name_snapshot',
        'product_type_snapshot',
        'unit_price_aed',
        'quantity',
        'line_total_aed',
        'unit_price_sdg',
        'line_total_sdg',
    ];

    protected $useTimestamps = false;
}
