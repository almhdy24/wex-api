<?php

namespace App\Models;

use App\Entities\OtpCode;
use CodeIgniter\Model;

class OtpCodeModel extends Model
{
    protected $table            = 'otp_codes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = OtpCode::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'email',
        'code_hash',
        'attempts',
        'expires_at',
        'consumed_at',
        'ip',
        'created_at',
    ];

    protected $useTimestamps = false;
}
