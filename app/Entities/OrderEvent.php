<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class OrderEvent extends Entity
{
    protected $dates = ['created_at'];

    protected $casts = [
        'id'            => 'integer',
        'order_id'      => 'integer',
        'actor_user_id' => '?integer',
        'event_type'    => 'string',
        'meta'          => '?string',
    ];
}
