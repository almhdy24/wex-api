<?php

namespace App\Services\Push;

use App\Entities\PushSubscription;

class FakeProvider implements PushProviderInterface
{
    /** @var array<int, array{subscription_id: int|null, user_id: int|null, provider: string, endpoint: string, payload: array}> */
    public array $sent = [];

    private bool $shouldExpire = false;
    private ?string $forceFailure = null;
    private ?string $logPath;

    public function __construct(?string $logPath = null)
    {
        if ($logPath !== null) {
            $this->logPath = $logPath;
        } else {
            $env = strtolower((string) (getenv('APP_ENV') ?: getenv('CI_ENVIRONMENT') ?: ''));
            $logEnabled = filter_var(env('PUSH_FAKE_LOG'), FILTER_VALIDATE_BOOLEAN);
            $this->logPath = ($env === 'testing' || $logEnabled)
                ? WRITEPATH . 'logs/fake_push.jsonl'
                : null;
        }
    }

    public function setShouldExpire(bool $expire): void
    {
        $this->shouldExpire = $expire;
    }

    public function setForceFailure(?string $error): void
    {
        $this->forceFailure = $error;
    }

    public function send(PushSubscription $subscription, array $payload): PushResult
    {
        $record = [
            'subscription_id' => $subscription->id,
            'user_id'         => $subscription->user_id,
            'provider'        => (string) $subscription->provider,
            'platform'        => (string) $subscription->platform,
            'endpoint'        => (string) $subscription->endpoint_or_token,
            'payload'         => $payload,
            'sent_at'         => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $this->sent[] = $record;

        if ($this->logPath !== null) {
            $dir = dirname($this->logPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            @file_put_contents($this->logPath, json_encode($record, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
        }

        if ($this->shouldExpire) {
            return PushResult::expired('Fake expired subscription', 410);
        }

        if ($this->forceFailure !== null) {
            return PushResult::failure($this->forceFailure, 500);
        }

        return PushResult::ok(201);
    }
}
