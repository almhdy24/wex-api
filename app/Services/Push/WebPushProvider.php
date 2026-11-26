<?php

namespace App\Services\Push;

use App\Entities\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushProvider implements PushProviderInterface
{
    private ?string $publicKey;
    private ?string $privateKey;
    private string $subject;
    private int $timeout;

    public function __construct(
        ?string $publicKey = null,
        ?string $privateKey = null,
        ?string $subject = null,
        int $timeout = 4
    ) {
        $this->publicKey  = $publicKey ?? (string) (env('VAPID_PUBLIC_KEY') ?: '');
        $this->privateKey = $privateKey ?? (string) (env('VAPID_PRIVATE_KEY') ?: '');
        $this->subject    = $subject ?? \App\Libraries\SiteIdentity::vapidSubject();
        $this->timeout    = $timeout;
    }

    public function isConfigured(): bool
    {
        return $this->publicKey !== '' && $this->privateKey !== '';
    }

    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        if (!$this->isConfigured()) {
            return PushResult::failure('VAPID keys are not configured.');
        }

        $keys = $subscription->getKeysArray();
        $p256dh = $keys['p256dh'] ?? null;
        $auth   = $keys['auth'] ?? null;

        if (empty($subscription->endpoint_or_token) || empty($p256dh) || empty($auth)) {
            return PushResult::expired('Missing WebPush subscription endpoint or keys.', 400);
        }

        // Defence in depth for rows stored before the endpoint policy existed (SSRF).
        if (!\App\Libraries\PushEndpointPolicy::isAllowed((string) $subscription->endpoint_or_token)) {
            return PushResult::expired('WebPush endpoint host is not an allowed push service.', 400);
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject'    => $this->subject,
                    'publicKey'  => $this->publicKey,
                    'privateKey' => $this->privateKey,
                ],
            ], [
                'TTL' => 86400,
            ], $this->timeout);

            $webPush->setReuseVAPIDHeaders(true);

            $sub = Subscription::create([
                'endpoint'        => $subscription->endpoint_or_token,
                'contentEncoding' => $keys['contentEncoding'] ?? 'aes128gcm',
                'keys'            => [
                    'p256dh' => $p256dh,
                    'auth'   => $auth,
                ],
            ]);

            $report = $webPush->sendOneNotification($sub, json_encode($payload, JSON_UNESCAPED_UNICODE));
            $response = $report->getResponse();
            $statusCode = $response ? $response->getStatusCode() : null;

            if ($report->isSuccess()) {
                return PushResult::ok($statusCode ?? 201);
            }

            if ($report->isSubscriptionExpired() || in_array($statusCode, [404, 410], true)) {
                return PushResult::expired($report->getReason() ?: 'Subscription expired', $statusCode ?? 410);
            }

            return PushResult::failure($report->getReason() ?: 'WebPush delivery failed', $statusCode);
        } catch (\Throwable $e) {
            return PushResult::failure($e->getMessage());
        }
    }
}
