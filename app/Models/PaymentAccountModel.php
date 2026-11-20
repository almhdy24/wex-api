<?php

namespace App\Models;

use App\Entities\PaymentAccount;
use CodeIgniter\Model;

class PaymentAccountModel extends Model
{
    protected $table            = 'payment_accounts';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = PaymentAccount::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'bank_name',
        'account_name',
        'account_number',
        'branch',
        'notes',
        'is_active',
        'sort_order',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
