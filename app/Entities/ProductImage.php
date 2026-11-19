<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class ProductImage extends Entity
{
    protected $dates = ['created_at'];

    protected $casts = [
        'id'         => 'integer',
        'product_id' => 'integer',
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];
}
