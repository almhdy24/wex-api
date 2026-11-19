<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;
use Config\Wex;

class Order extends Entity
{
    protected $dates = ['lock_expires_at', 'created_at', 'updated_at'];

    protected $casts = [
        'id'                            => 'integer',
        'user_id'                       => 'integer',
        'shipping_zone_id'              => '?integer',
        'rate_sdg_snapshot'             => 'integer',
        'app_discount_percent_snapshot' => 'integer',
        'subtotal_aed'                  => 'integer',
        'subtotal_sdg'                  => 'integer',
        'discount_sdg'                  => 'integer',
        'delivery_fee_sdg'              => 'integer',
        'total_sdg'                     => 'integer',
    ];

    public function isPendingPayment(): bool
    {
        return $this->attributes['status'] === Wex::ORDER_STATUS_PENDING_PAYMENT;
    }

    public function isLockExpired(): bool
    {
        if (empty($this->attributes['lock_expires_at'])) {
            return false;
        }
        return strtotime($this->attributes['lock_expires_at']) <= time();
    }

    public function getSecondsRemaining(): int
    {
        if (empty($this->attributes['lock_expires_at'])) {
            return 0;
        }
        $remaining = strtotime($this->attributes['lock_expires_at']) - time();
        return max(0, $remaining);
    }
}
