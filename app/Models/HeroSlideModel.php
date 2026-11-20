<?php

namespace App\Models;

use App\Entities\HeroSlide;
use CodeIgniter\Model;

class HeroSlideModel extends Model
{
    protected $table            = 'hero_slides';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = HeroSlide::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'title',
        'subtitle',
        'image',
        'button_label',
        'link',
        'sort_order',
        'is_active',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
