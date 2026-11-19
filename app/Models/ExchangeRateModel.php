<?php

namespace App\Models;

use App\Entities\ExchangeRate;
use CodeIgniter\Model;

class ExchangeRateModel extends Model
{
    protected $table            = 'exchange_rates';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = ExchangeRate::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'rate_sdg',
        'changed_by',
        'created_at',
    ];

    protected $useTimestamps = false;
}
