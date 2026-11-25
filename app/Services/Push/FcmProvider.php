<?php

namespace App\Services\Push;

use App\Entities\PushSubscription;
use Firebase\JWT\JWT;

/**
 * FcmProvider
 *
 * Fully coded FCM HTTP v1 provider for Android/iOS Flutter clients.
 * Disabled by default unless FCM_ENABLED=true in .env.
 */
class FcmProvider implements PushProviderInterface
{
    private bool $enabled;
    private string $serviceAccountPath;
    private string $projectId;
    private int $timeout;

    public function __construct(
        ?bool $enabled = null,
        ?string $serviceAccountPath = null,
        ?string $projectId = null,
        int $timeout = 4
    ) {
        $envEnabled = env('FCM_ENABLED');
        $this->enabled = $enabled ?? filter_var($envEnabled, FILTER_VALIDATE_BOOLEAN);
        $this->serviceAccountPath = $serviceAccountPath ?? (string) (env('FCM_SERVICE_ACCOUNT_PATH') ?: '');
        $this->projectId = $projectId ?? (string) (env('FCM_PROJECT_ID') ?: '');
        $this->timeout = $timeout;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        if (!$this->enabled) {
            return PushResult::failure('FCM_ENABLED is false; FCM delivery is disabled.');
        }

        $token = trim((string) $subscription->endpoint_or_token);
        if ($token === '') {
            return PushResult::expired('Empty FCM token', 400);
        }

        return $this->sendMessage(['token' => $token], $payload);
    }

    public function sendToTopic(string $topic, array $payload): PushResult
    {
        if (!$this->enabled) {
            return PushResult::failure('FCM_ENABLED is false; FCM delivery is disabled.');
        }

        return $this->sendMessage(['topic' => $topic], $payload);
    }

    private function sendMessage(array $target, array $payload): PushResult
    {
        try {
            $sa = $this->loadServiceAccount();
            $projectId = $this->projectId !== '' ? $this->projectId : ($sa['project_id'] ?? '');
            if ($projectId === '') {
                return PushResult::failure('FCM project_id is missing.');
            }

            $accessToken = $this->getAccessToken($sa);

            $dataPayload = [];
            if (!empty($payload['data']) && is_array($payload['data'])) {
                foreach ($payload['data'] as $k => $v) {
                    $dataPayload[(string) $k] = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
                }
            }

            $channelId = $this->resolveAndroidChannel((string) ($payload['data']['type'] ?? ''));

            $message = array_merge($target, [
                'notification' => [
                    'title' => (string) ($payload['title'] ?? ''),
                    'body'  => (string) ($payload['body'] ?? ''),
                ],
                'data' => $dataPayload,
                'android' => [
                    'priority'     => 'high',
                    'notification' => [
                        'channel_id' => $channelId,
                        'tag'        => (string) ($payload['tag'] ?? ''),
                    ],
                ],
            ]);

            $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => $this->timeout,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json',
                ],
                CURLOPT_POSTFIELDS     => json_encode(['message' => $message], JSON_UNESCAPED_UNICODE),
            ]);

            $raw = curl_exec($ch);
            $curlErr = curl_error($ch);
            $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($raw === false) {
                return PushResult::failure('FCM cURL error: ' . $curlErr);
            }

            if ($statusCode >= 200 && $statusCode < 300) {
                return PushResult::ok($statusCode);
            }

            $decoded = json_decode($raw, true);
            $errCode = $decoded['error']['details'][0]['errorCode']
                ?? $decoded['error']['status']
                ?? '';
            $errMsg  = $decoded['error']['message'] ?? $raw;

            if (in_array($errCode, ['UNREGISTERED', 'INVALID_ARGUMENT'], true) || $statusCode === 404) {
                return PushResult::expired($errMsg, $statusCode);
            }

            return PushResult::failure($errMsg, $statusCode);
        } catch (\Throwable $e) {
            return PushResult::failure($e->getMessage());
        }
    }

    private function resolveAndroidChannel(string $type): string
    {
        if (str_contains($type, 'payment') || str_contains($type, 'receipt')) {
            return 'payments';
        }
        if (str_contains($type, 'order') || str_contains($type, 'stock')) {
            return 'orders';
        }
        return 'offers';
    }

    private function loadServiceAccount(): array
    {
        if ($this->serviceAccountPath === '' || !is_file($this->serviceAccountPath)) {
            throw new \RuntimeException('FCM service account JSON file not found.');
        }
        $data = json_decode((string) file_get_contents($this->serviceAccountPath), true);
        if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
            throw new \RuntimeException('Invalid FCM service account JSON.');
        }
        return $data;
    }

    private function getAccessToken(array $sa): string
    {
        $cacheKey = 'fcm_oauth_token_' . md5($sa['client_email']);
        $cache = cache();
        $cached = $cache->get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $now = time();
        $jwt = JWT::encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ], $sa['private_key'], 'RS256');

        $ch = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ]),
        ]);

        $raw = curl_exec($ch);
        $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if ($statusCode !== 200 || empty($decoded['access_token'])) {
            throw new \RuntimeException('Failed to obtain FCM OAuth access token.');
        }

        $token = (string) $decoded['access_token'];
        $cache->save($cacheKey, $token, 3300);

        return $token;
    }
}
