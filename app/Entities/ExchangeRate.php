<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class ExchangeRate extends Entity
{
    protected $dates = ['created_at'];

    protected $casts = [
        'id'         => 'integer',
        'rate_sdg'   => 'integer',
        'changed_by' => '?integer',
    ];
}
