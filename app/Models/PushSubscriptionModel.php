<?php

namespace App\Models;

use App\Entities\PushSubscription;
use CodeIgniter\Model;

class PushSubscriptionModel extends Model
{
    protected $table            = 'push_subscriptions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = PushSubscription::class;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'user_id',
        'platform',
        'provider',
        'endpoint_or_token',
        'endpoint_hash',
        'keys_json',
        'device_label',
        'user_agent',
        'locale',
        'app_version',
        'topics_json',
        'is_active',
        'last_success_at',
        'last_failure_at',
        'failure_count',
        'created_at',
        'updated_at',
    ];

    protected $useTimestamps = false;

    public static function hashEndpoint(string $endpointOrToken): string
    {
        return hash('sha256', trim($endpointOrToken));
    }
}
