<?php

namespace App\Services\Push;

use App\Entities\PushSubscription;

interface PushProviderInterface
{
    public function send(PushSubscription $subscription, array $payload): PushResult;
}
