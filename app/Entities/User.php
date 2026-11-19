<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;
use Config\Wex;

class User extends Entity
{
    protected $dates = ['email_verified_at', 'last_login_at', 'created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'                  => 'integer',
        'email_notifications' => 'boolean',
        'is_active'           => 'boolean',
    ];

    public function isAdmin(): bool
    {
        return $this->attributes['role'] === Wex::ROLE_ADMIN;
    }

    public function isStaff(): bool
    {
        return in_array($this->attributes['role'], [Wex::ROLE_ADMIN, Wex::ROLE_STAFF], true);
    }

    public function isCustomer(): bool
    {
        return $this->attributes['role'] === Wex::ROLE_CUSTOMER;
    }

    public function toArray(bool $onlyChanged = false, bool $cast = true, bool $recursive = false): array
    {
        $arr = parent::toArray($onlyChanged, $cast, $recursive);
        unset($arr['deleted_at']);
        return $arr;
    }
}
