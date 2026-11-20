<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Announcement extends Entity
{
    protected $dates = ['starts_at', 'ends_at', 'created_at', 'updated_at'];
    protected $casts = [
        'id'             => 'integer',
        'is_active'      => 'boolean',
        'is_dismissible' => 'boolean',
        'sort_order'     => 'integer',
    ];
}
