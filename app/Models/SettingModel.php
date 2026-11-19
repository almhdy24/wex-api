<?php

namespace App\Models;

use App\Entities\Setting;
use CodeIgniter\Model;

class SettingModel extends Model
{
    protected $table            = 'settings';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = Setting::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'key',
        'value',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = '';
    protected $updatedField  = 'updated_at';
}
