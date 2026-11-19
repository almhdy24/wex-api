<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Setting extends Entity
{
    protected $dates = ['updated_at'];

    protected $casts = [
        'id' => 'integer',
    ];
}
