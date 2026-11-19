<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class ShippingZone extends Entity
{
    protected $dates = ['created_at', 'updated_at'];

    protected $casts = [
        'id'        => 'integer',
        'fee_sdg'   => 'integer',
        'is_active' => 'boolean',
    ];
}
