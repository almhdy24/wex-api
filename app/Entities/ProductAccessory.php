<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class ProductAccessory extends Entity
{
    protected $casts = [
        'id'           => 'integer',
        'product_id'   => 'integer',
        'accessory_id' => 'integer',
        'sort_order'   => 'integer',
    ];
}
