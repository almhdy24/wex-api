<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class OrderItem extends Entity
{
    protected $casts = [
        'id'             => 'integer',
        'order_id'       => 'integer',
        'product_id'     => 'integer',
        'unit_price_aed' => 'integer',
        'quantity'       => 'integer',
        'line_total_aed' => 'integer',
    ];
}
