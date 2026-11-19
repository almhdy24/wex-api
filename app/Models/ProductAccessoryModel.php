<?php

namespace App\Models;

use App\Entities\ProductAccessory;
use CodeIgniter\Model;

class ProductAccessoryModel extends Model
{
    protected $table            = 'product_accessories';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = ProductAccessory::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'product_id',
        'accessory_id',
        'sort_order',
    ];

    protected $useTimestamps = false;
}
