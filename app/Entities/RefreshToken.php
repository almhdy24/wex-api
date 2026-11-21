<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

class RefreshToken extends Entity
{
    protected $dates = ['expires_at', 'revoked_at', 'created_at', 'family_created_at'];

    protected $casts = [
        'id'             => 'integer',
        'user_id'        => 'integer',
        'replaced_by_id' => '?integer',
    ];

    public function isExpired(): bool
    {
        return strtotime($this->attributes['expires_at']) <= time();
    }

    public function isRevoked(): bool
    {
        return !empty($this->attributes['revoked_at']);
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isRevoked();
    }
}
