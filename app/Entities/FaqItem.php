<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class FaqItem extends Entity
{
    protected $dates = ['created_at', 'updated_at'];
    protected $casts = [
        'id'         => 'integer',
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];
}
