<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class OtpCode extends Entity
{
    protected $dates = ['expires_at', 'consumed_at', 'created_at'];

    protected $casts = [
        'id'       => 'integer',
        'attempts' => 'integer',
    ];

    public function isExpired(): bool
    {
        return strtotime($this->attributes['expires_at']) <= time();
    }

    public function isConsumed(): bool
    {
        return !empty($this->attributes['consumed_at']);
    }

    public function isBurned(): bool
    {
        return ($this->attributes['attempts'] ?? 0) >= 5;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isConsumed() && !$this->isBurned();
    }
}
