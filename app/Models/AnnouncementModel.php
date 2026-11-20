<?php

namespace App\Models;

use App\Entities\Announcement;
use CodeIgniter\Model;

class AnnouncementModel extends Model
{
    protected $table            = 'announcements';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Announcement::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'kind',
        'title',
        'body',
        'link_label',
        'link_url',
        'placement',
        'starts_at',
        'ends_at',
        'is_active',
        'is_dismissible',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
