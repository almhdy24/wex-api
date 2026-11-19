<?php

namespace App\Models;

use App\Entities\EmailOutbox;
use CodeIgniter\Model;

class EmailOutboxModel extends Model
{
    protected $table            = 'email_outbox';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = EmailOutbox::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'to_email',
        'subject',
        'template',
        'payload',
        'status',
        'attempts',
        'last_error',
        'available_at',
        'sent_at',
        'created_at',
    ];

    protected $useTimestamps = false;
}
