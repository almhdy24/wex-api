<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class Notification extends Entity
{
    protected $dates = ['read_at', 'created_at'];

    protected $casts = [
        'id'       => 'integer',
        'user_id'  => 'integer',
        'order_id' => '?integer',
    ];

    public function isRead(): bool
    {
        return !empty($this->attributes['read_at']);
    }

    public function getDataArray(): array
    {
        if (empty($this->attributes['data_json'])) {
            return [];
        }
        $decoded = json_decode($this->attributes['data_json'], true);
        return is_array($decoded) ? $decoded : [];
    }
}
