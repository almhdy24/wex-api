<?php

namespace App\Models;

use App\Entities\HomeSection;
use CodeIgniter\Model;

class HomeSectionModel extends Model
{
    protected $table            = 'home_sections';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = HomeSection::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'key',
        'title',
        'source',
        'source_value',
        'limit',
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
