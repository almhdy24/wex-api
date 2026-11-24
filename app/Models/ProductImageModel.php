<?php

namespace App\Models;

use App\Entities\ProductImage;
use CodeIgniter\Model;

class ProductImageModel extends Model
{
    protected $table            = 'product_images';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = ProductImage::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'product_id',
        'path',
        'thumb_path',
        'sort_order',
        'is_primary',
        'alt',
        'created_at',
    ];

    protected $useTimestamps = false;
}
