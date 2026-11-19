<?php

namespace App\Models;

use App\Entities\ShippingZone;
use CodeIgniter\Model;

class ShippingZoneModel extends Model
{
    protected $table            = 'shipping_zones';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = ShippingZone::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'state_name_ar',
        'fee_sdg',
        'is_active',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
