<?php

namespace App\Models;

use App\Entities\RefreshToken;
use CodeIgniter\Model;

class RefreshTokenModel extends Model
{
    protected $table            = 'refresh_tokens';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = RefreshToken::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'family_id',
        'token_hash',
        'user_agent',
        'ip',
        'expires_at',
        'revoked_at',
        'replaced_by_id',
        'created_at',
    ];

    protected $useTimestamps = false;
}
