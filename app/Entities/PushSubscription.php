<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class PushSubscription extends Entity
{
    protected $dates = ['last_success_at', 'last_failure_at', 'created_at', 'updated_at'];

    protected $casts = [
        'id'            => 'integer',
        'user_id'       => '?integer',
        'is_active'     => 'boolean',
        'failure_count' => 'integer',
    ];

    public function getKeysArray(): array
    {
        if (empty($this->attributes['keys_json'])) {
            return [];
        }
        $decoded = json_decode($this->attributes['keys_json'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public function getTopicsArray(): array
    {
        if (empty($this->attributes['topics_json'])) {
            return [];
        }
        $decoded = json_decode($this->attributes['topics_json'], true);
        return is_array($decoded) ? $decoded : [];
    }
}
