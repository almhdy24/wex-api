<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class HomeSection extends Entity
{
    protected $dates = ['created_at', 'updated_at'];
    protected $casts = [
        'id'         => 'integer',
        'limit'      => 'integer',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];
}
