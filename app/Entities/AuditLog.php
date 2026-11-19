<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class AuditLog extends Entity
{
    protected $dates = ['created_at'];

    protected $casts = [
        'id'        => 'integer',
        'user_id'   => '?integer',
        'entity_id' => '?integer',
    ];

    public function getDetails(): array
    {
        if (empty($this->attributes['details'])) {
            return [];
        }
        if (is_array($this->attributes['details'])) {
            return $this->attributes['details'];
        }
        $decoded = json_decode($this->attributes['details'], true);
        return is_array($decoded) ? $decoded : [];
    }

    public function setDetails(mixed $details): self
    {
        $this->attributes['details'] = is_array($details)
            ? json_encode($details, JSON_UNESCAPED_UNICODE)
            : $details;

        return $this;
    }
}
