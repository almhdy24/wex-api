<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class PaymentProof extends Entity
{
    protected $dates = ['reviewed_at', 'created_at'];

    protected $casts = [
        'id'          => 'integer',
        'order_id'    => 'integer',
        'reviewed_by' => '?integer',
    ];
}
