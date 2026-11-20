<?php

namespace App\Models;

use App\Entities\FaqItem;
use CodeIgniter\Model;

class FaqItemModel extends Model
{
    protected $table            = 'faq_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = FaqItem::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'question',
        'answer',
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
