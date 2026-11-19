<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class EmailOutbox extends Entity
{
    protected $dates = ['available_at', 'sent_at', 'created_at'];

    protected $casts = [
        'id'       => 'integer',
        'user_id'  => '?integer',
        'attempts' => 'integer',
    ];

    public function getPayload(): array
    {
        if (empty($this->attributes['payload'])) {
            return [];
        }
        if (is_array($this->attributes['payload'])) {
            return $this->attributes['payload'];
        }
        $decoded = json_decode($this->attributes['payload'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setPayload(mixed $payload): self
    {
        $this->attributes['payload'] = is_array($payload)
            ? json_encode($payload, JSON_UNESCAPED_UNICODE)
            : $payload;

        return $this;
    }
}
