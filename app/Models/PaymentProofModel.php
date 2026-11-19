<?php

namespace App\Models;

use App\Entities\PaymentProof;
use CodeIgniter\Model;

class PaymentProofModel extends Model
{
    protected $table            = 'payment_proofs';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = PaymentProof::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'order_id',
        'file_path',
        'sender_name',
        'reference_number',
        'status',
        'review_note',
        'reviewed_by',
        'reviewed_at',
        'created_at',
    ];

    protected $useTimestamps = false;
}
