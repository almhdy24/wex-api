<?php

namespace App\Services;

use App\Entities\Notification;
use App\Entities\PushSubscription;
use App\Models\NotificationModel;
use App\Models\NotificationPreferenceModel;
use App\Models\OrderModel;
use App\Models\PushOutboxModel;
use App\Models\PushSubscriptionModel;
use App\Models\UserModel;
use App\Services\Push\FakeProvider;
use App\Services\Push\FcmProvider;
use App\Services\Push\PushProviderInterface;
use App\Services\Push\PushResult;
use App\Services\Push\WebPushProvider;
use Config\PushTemplates;
use Config\Wex;

class PushService
{
    public const MAX_INLINE_DELIVERY = 20;
    public const MAX_ATTEMPTS        = 5;
    private const BACKOFF_SECONDS    = [1 => 60, 2 => 300, 3 => 900, 4 => 3600];

    protected PushSubscriptionModel $subscriptions;
    protected NotificationPreferenceModel $preferences;
    protected PushOutboxModel $outbox;
    protected NotificationModel $notifications;
    protected UserModel $users;
    protected OrderModel $orders;
    protected SettingService $settings;

    /** @var array<string, PushProviderInterface> */
    protected array $providers = [];
    protected ?PushProviderInterface $overrideProvider = null;

    public function __construct(
        ?PushSubscriptionModel $subscriptions = null,
        ?NotificationPreferenceModel $preferences = null,
        ?PushOutboxModel $outbox = null,
        ?NotificationModel $notifications = null,
        ?UserModel $users = null,
        ?OrderModel $orders = null,
        ?SettingService $settings = null,
        ?PushProviderInterface $overrideProvider = null
    ) {
        $this->subscriptions    = $subscriptions ?? new PushSubscriptionModel();
        $this->preferences      = $preferences ?? new NotificationPreferenceModel();
        $this->outbox           = $outbox ?? new PushOutboxModel();
        $this->notifications    = $notifications ?? new NotificationModel();
        $this->users            = $users ?? new UserModel();
        $this->orders           = $orders ?? new OrderModel();
        $this->settings         = $settings ?? new SettingService();
        $this->overrideProvider = $overrideProvider;

        // The fake provider swallows notifications: never in production, whatever .env says.
        if ($this->overrideProvider === null && strtolower((string) env('PUSH_PROVIDER')) === 'fake' && !\App\Libraries\RuntimeEnv::isProduction()) {
            $this->overrideProvider = new FakeProvider();
        }
    }

    public function setProvider(PushProviderInterface $provider): void
    {
        $this->overrideProvider = $provider;
    }

    public function isGlobalEnabled(): bool
    {
        $val = $this->settings->get('push_enabled', '1');
        return filter_var($val, FILTER_VALIDATE_BOOLEAN);
    }

    public function getPublicConfig(): array
    {
        $publicKey = (string) (env('VAPID_PUBLIC_KEY') ?: '');
        $enabled   = $this->isGlobalEnabled() && $publicKey !== '';

        return [
            'webPushPublicKey' => $publicKey,
            'enabled'          => $enabled,
        ];
    }

    public function getPreferences(int $userId): array
    {
        $row = $this->preferences->where('user_id', $userId)->first();
        if (!$row) {
            return [
                'order_updates'   => true,
                'payment_updates' => true,
                'price_alerts'    => true,
                'announcements'   => true,
                'new_orders'      => true,
                'receipts'        => true,
                'low_stock'       => true,
            ];
        }

        return [
            'order_updates'   => (bool) $row['order_updates'],
            'payment_updates' => (bool) $row['payment_updates'],
            'price_alerts'    => (bool) $row['price_alerts'],
            'announcements'   => (bool) $row['announcements'],
            'new_orders'      => (bool) $row['new_orders'],
            'receipts'        => (bool) $row['receipts'],
            'low_stock'       => (bool) $row['low_stock'],
        ];
    }

    public function updatePreferences(int $userId, array $input): array
    {
        $allowed = [
            'order_updates',
            'payment_updates',
            'price_alerts',
            'announcements',
            'new_orders',
            'receipts',
            'low_stock',
        ];

        $current = $this->getPreferences($userId);
        $data = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $input)) {
                $data[$key] = filter_var($input[$key], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            } else {
                $data[$key] = $current[$key] ? 1 : 0;
            }
        }

        $now = gmdate('Y-m-d H:i:s');
        $existing = $this->preferences->where('user_id', $userId)->first();
        if ($existing) {
            $data['updated_at'] = $now;
            $this->preferences->update($existing['id'], $data);
        } else {
            $data['user_id']    = $userId;
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $this->preferences->insert($data);
        }

        return $this->getPreferences($userId);
    }

    public function registerSubscription(int $userId, array $data): PushSubscription
    {
        $endpointOrToken = trim((string) ($data['endpoint_or_token'] ?? ''));
        $hash = PushSubscriptionModel::hashEndpoint($endpointOrToken);
        $now  = gmdate('Y-m-d H:i:s');

        $payload = [
            'user_id'           => $userId,
            'platform'          => $data['platform'] ?? 'web',
            'provider'          => $data['provider'] ?? 'webpush',
            'endpoint_or_token' => $endpointOrToken,
            'endpoint_hash'     => $hash,
            'keys_json'         => !empty($data['keys']) ? json_encode($data['keys'], JSON_UNESCAPED_UNICODE) : null,
            'device_label'      => isset($data['device_label']) ? mb_substr(trim((string) $data['device_label']), 0, 191) : null,
            'user_agent'        => isset($data['user_agent']) ? mb_substr(trim((string) $data['user_agent']), 0, 255) : null,
            'locale'            => $data['locale'] ?? 'ar',
            'app_version'       => isset($data['app_version']) ? mb_substr(trim((string) $data['app_version']), 0, 50) : null,
            'topics_json'       => !empty($data['topics']) ? json_encode($data['topics'], JSON_UNESCAPED_UNICODE) : null,
            'is_active'         => 1,
            'failure_count'     => 0,
            'updated_at'        => $now,
        ];

        $existing = $this->subscriptions->where('endpoint_hash', $hash)->first();
        if ($existing) {
            $this->subscriptions->update($existing->id, $payload);
            return $this->subscriptions->find($existing->id);
        }

        $payload['created_at'] = $now;
        $id = $this->subscriptions->insert($payload);
        return $this->subscriptions->find($id);
    }

    public function unregisterSubscription(string $endpointOrToken, ?int $userId = null): int
    {
        $hash = PushSubscriptionModel::hashEndpoint($endpointOrToken);
        $builder = $this->subscriptions->where('endpoint_hash', $hash);
        if ($userId !== null) {
            $builder->where('user_id', $userId);
        }
        $rows = $builder->findAll();
        $count = 0;
        foreach ($rows as $row) {
            $this->subscriptions->delete($row->id);
            $count++;
        }
        return $count;
    }

    public function listUserSubscriptions(int $userId): array
    {
        $rows = $this->subscriptions
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->orderBy('updated_at', 'DESC')
            ->findAll();

        return array_map(static fn (PushSubscription $s) => [
            'id'              => $s->id,
            'platform'        => $s->platform,
            'provider'        => $s->provider,
            'device_label'    => $s->device_label ?: ($s->platform === 'web' ? 'متصفح ويب' : $s->platform),
            'locale'          => $s->locale,
            'last_success_at' => $s->last_success_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($s->last_success_at)) : null,
            'created_at'      => $s->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($s->created_at)) : null,
        ], $rows);
    }

    public function deleteUserSubscriptionById(int $userId, int $subscriptionId): bool
    {
        $sub = $this->subscriptions
            ->where('id', $subscriptionId)
            ->where('user_id', $userId)
            ->first();
        if (!$sub) {
            return false;
        }
        return (bool) $this->subscriptions->delete($sub->id);
    }

    /**
     * Send an event (in-app + push) to a single user.
     *
     * @param array{
     *   type: string,
     *   title?: string,
     *   body?: string,
     *   push_title?: string,
     *   push_body?: string,
     *   order_id?: int|null,
     *   order_number?: string|null,
     *   url?: string|null,
     *   tag?: string|null,
     *   actor_user_id?: int|null,
     *   expected_order_status?: string|null,
     *   scheduled_at?: string|null,
     *   notification_id?: int|null,
     *   skip_in_app?: bool
     * } $event
     */
    public function sendToUser(int $userId, array $event): ?int
    {
        if (isset($event['actor_user_id']) && (int) $event['actor_user_id'] === $userId) {
            return null;
        }

        if ($this->isStaleEvent($event)) {
            return null;
        }

        $type        = (string) ($event['type'] ?? 'order_update');
        $orderId     = isset($event['order_id']) ? (int) $event['order_id'] : null;
        $orderNumber = isset($event['order_number']) ? (string) $event['order_number'] : null;
        $url         = $this->sanitizeRelativeUrl($event['url'] ?? ($orderNumber ? "/orders/{$orderNumber}" : '/notifications'));
        $now         = gmdate('Y-m-d H:i:s');

        $title     = (string) ($event['title'] ?? '');
        $body      = (string) ($event['body'] ?? '');
        $pushTitle = (string) ($event['push_title'] ?? $title);
        $pushBody  = (string) ($event['push_body'] ?? $body);

        $dataJson = [
            'type'         => $type,
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'url'          => $url,
        ];

        $notificationId = $event['notification_id'] ?? null;
        if ($notificationId === null && empty($event['skip_in_app'])) {
            $notification = new Notification([
                'user_id'       => $userId,
                'type'          => $type,
                'title'         => $title,
                'body'          => $body,
                'order_id'      => $orderId,
                'data_json'     => json_encode($dataJson, JSON_UNESCAPED_UNICODE),
                'channel_flags' => 'in_app',
                'read_at'       => null,
                'created_at'    => $now,
            ]);
            $notificationId = (int) $this->notifications->insert($notification);
        }

        if (!$this->isGlobalEnabled()) {
            return $notificationId;
        }

        $category = PushTemplates::getCategory($type);
        $prefs    = $this->getPreferences($userId);
        if (array_key_exists($category, $prefs) && !$prefs[$category]) {
            return $notificationId;
        }

        $subs = $this->subscriptions
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->findAll();

        if (empty($subs)) {
            return $notificationId;
        }

        $tag = $event['tag'] ?? ($orderNumber ? "order-{$orderNumber}" : ($orderId ? "order-{$orderId}" : "notif-{$type}"));
        $payload = [
            'title'              => $pushTitle,
            'body'               => $pushBody,
            'tag'                => $tag,
            'requireInteraction' => PushTemplates::isCritical($type),
            'data'               => [
                'type'           => $type,
                'orderId'        => $orderId,
                'orderNumber'    => $orderNumber,
                'url'            => $url,
                'notificationId' => $notificationId,
            ],
        ];

        $scheduledAt = $event['scheduled_at'] ?? null;
        $isFuture = $scheduledAt !== null && strtotime($scheduledAt) > time();
        $nextAttempt = $isFuture ? gmdate('Y-m-d H:i:s', strtotime($scheduledAt)) : null;

        $outboxIds = [];
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

        foreach ($subs as $sub) {
            $outboxId = (int) $this->outbox->insert([
                'notification_id' => $notificationId,
                'subscription_id' => $sub->id,
                'status'          => 'pending',
                'attempts'        => 0,
                'next_attempt_at' => $nextAttempt,
                'payload_json'    => $payloadJson,
                'created_at'      => $now,
            ]);
            $outboxIds[] = $outboxId;
        }

        if ($notificationId !== null) {
            $this->notifications->update($notificationId, ['channel_flags' => 'in_app,push']);
        }

        if (!$isFuture) {
            $inlineIds = array_slice($outboxIds, 0, self::MAX_INLINE_DELIVERY);
            $this->deliverOutboxIds($inlineIds);
        }

        return $notificationId;
    }

    public function sendToRole(string|array $roles, array $event): int
    {
        $roleList = is_array($roles) ? $roles : [$roles];
        $users = $this->users
            ->whereIn('role', $roleList)
            ->where('is_active', 1)
            ->findAll();

        $count = 0;
        foreach ($users as $user) {
            $res = $this->sendToUser((int) $user->id, $event);
            if ($res !== null) {
                $count++;
            }
        }
        return $count;
    }

    public function broadcast(string $segment, array $event): array
    {
        $userIds = $this->resolveSegmentUserIds($segment);
        $enqueued = 0;

        foreach ($userIds as $uid) {
            $res = $this->sendToUser($uid, array_merge($event, [
                'type' => $event['type'] ?? 'announcement',
                'tag'  => $event['tag'] ?? 'announcement-' . gmdate('YmdHi'),
            ]));
            if ($res !== null) {
                $enqueued++;
            }
        }

        return [
            'targeted_users' => count($userIds),
            'notified'       => $enqueued,
        ];
    }

    public function processOutbox(int $limit = 100): array
    {
        $now = gmdate('Y-m-d H:i:s');
        $rows = $this->outbox
            ->where('status', 'pending')
            ->groupStart()
                ->where('next_attempt_at IS NULL', null, false)
                ->orWhere('next_attempt_at <=', $now)
            ->groupEnd()
            ->orderBy('id', 'ASC')
            ->findAll($limit);

        return $this->deliverRows($rows);
    }

    public function deliverOutboxIds(array $outboxIds): array
    {
        if (empty($outboxIds)) {
            return ['sent' => 0, 'failed' => 0, 'expired' => 0];
        }
        $rows = $this->outbox->whereIn('id', $outboxIds)->where('status', 'pending')->findAll();
        return $this->deliverRows($rows);
    }

    protected function deliverRows(array $rows): array
    {
        $stats = ['sent' => 0, 'failed' => 0, 'expired' => 0];

        foreach ($rows as $row) {
            $sub = $this->subscriptions->find($row['subscription_id']);
            if (!$sub || !$sub->is_active) {
                $this->outbox->update($row['id'], [
                    'status' => 'expired',
                    'error'  => 'Subscription inactive or missing',
                ]);
                $stats['expired']++;
                continue;
            }

            $payload = json_decode((string) $row['payload_json'], true);
            if (!is_array($payload)) {
                $this->outbox->update($row['id'], [
                    'status' => 'failed',
                    'error'  => 'Invalid payload JSON',
                ]);
                $stats['failed']++;
                continue;
            }

            // Stale-event check before sending retry
            if (!empty($payload['data']['expected_order_status']) && !empty($payload['data']['orderId'])) {
                $order = $this->orders->find((int) $payload['data']['orderId']);
                if (!$order || $order->status !== $payload['data']['expected_order_status']) {
                    $this->outbox->update($row['id'], [
                        'status' => 'expired',
                        'error'  => 'Skipped stale order event',
                    ]);
                    $stats['expired']++;
                    continue;
                }
            }

            // Claim the row with a short lease before sending. Inline delivery and the
            // push:work cron (or two overlapping cron runs) could otherwise both send it.
            // A worker that dies mid-send leaves the row eligible again once the lease ends.
            if (!$this->claimOutboxRow((int) $row['id'])) {
                continue;
            }

            $provider = $this->resolveProvider((string) $sub->provider);
            $result   = $provider->send($sub, $payload);
            $now      = gmdate('Y-m-d H:i:s');
            $attempts = ((int) $row['attempts']) + 1;

            if ($result->success) {
                $this->outbox->update($row['id'], [
                    'status'   => 'sent',
                    'attempts' => $attempts,
                    'sent_at'  => $now,
                    'error'    => null,
                ]);
                $this->subscriptions->update($sub->id, [
                    'last_success_at' => $now,
                    'failure_count'   => 0,
                    'updated_at'      => $now,
                ]);
                $stats['sent']++;
            } elseif ($result->expired) {
                $this->outbox->update($row['id'], [
                    'status'   => 'expired',
                    'attempts' => $attempts,
                    'error'    => $result->error,
                ]);
                $this->subscriptions->update($sub->id, [
                    'is_active'       => 0,
                    'last_failure_at' => $now,
                    'failure_count'   => $sub->failure_count + 1,
                    'updated_at'      => $now,
                ]);
                $stats['expired']++;
            } else {
                $isFinal = $attempts >= self::MAX_ATTEMPTS;
                $delay   = self::BACKOFF_SECONDS[$attempts] ?? 3600;
                $this->outbox->update($row['id'], [
                    'status'          => $isFinal ? 'failed' : 'pending',
                    'attempts'        => $attempts,
                    'next_attempt_at' => $isFinal ? null : gmdate('Y-m-d H:i:s', time() + $delay),
                    'error'           => $result->error,
                ]);
                $this->subscriptions->update($sub->id, [
                    'last_failure_at' => $now,
                    'failure_count'   => $sub->failure_count + 1,
                    'updated_at'      => $now,
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    public const CLAIM_LEASE_SECONDS = 300;

    protected function claimOutboxRow(int $id): bool
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->outbox->builder()
            ->where('id', $id)
            ->where('status', 'pending')
            ->groupStart()
                ->where('next_attempt_at IS NULL', null, false)
                ->orWhere('next_attempt_at <=', $now)
            ->groupEnd()
            ->update(['next_attempt_at' => gmdate('Y-m-d H:i:s', time() + self::CLAIM_LEASE_SECONDS)]);

        return $this->outbox->db->affectedRows() === 1;
    }

    protected function resolveProvider(string $providerName): PushProviderInterface
    {
        if ($this->overrideProvider !== null) {
            return $this->overrideProvider;
        }

        if (!isset($this->providers[$providerName])) {
            $this->providers[$providerName] = match ($providerName) {
                'fcm'   => new FcmProvider(),
                default => new WebPushProvider(),
            };
        }

        return $this->providers[$providerName];
    }

    protected function isStaleEvent(array $event): bool
    {
        if (empty($event['expected_order_status']) || empty($event['order_id'])) {
            return false;
        }
        $order = $this->orders->find((int) $event['order_id']);
        if (!$order) {
            return true;
        }
        return $order->status !== $event['expected_order_status'];
    }

    protected function sanitizeRelativeUrl(?string $url): string
    {
        $url = trim((string) $url);
        if ($url === '' || !str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return '/notifications';
        }
        return $url;
    }

    protected function resolveSegmentUserIds(string $segment): array
    {
        if ($segment === 'with-open-orders') {
            $openStatuses = [
                Wex::ORDER_STATUS_PENDING_PAYMENT,
                Wex::ORDER_STATUS_PAYMENT_REVIEW,
                Wex::ORDER_STATUS_CONFIRMED,
                Wex::ORDER_STATUS_READY_FOR_PICKUP,
                Wex::ORDER_STATUS_OUT_FOR_DELIVERY,
            ];
            $rows = $this->orders
                ->select('user_id')
                ->distinct()
                ->whereIn('status', $openStatuses)
                ->findAll();
            return array_values(array_unique(array_map(static fn ($o) => (int) $o->user_id, $rows)));
        }

        $builder = $this->users->select('id')->where('is_active', 1);
        if ($segment === 'customers') {
            $builder->where('role', Wex::ROLE_CUSTOMER);
        }
        $rows = $builder->findAll();
        return array_map(static fn ($u) => (int) $u->id, $rows);
    }

    public function getStats(): array
    {
        $since = gmdate('Y-m-d H:i:s', strtotime('-7 days'));

        $activeSubs = $this->subscriptions->where('is_active', 1)->findAll();
        $byPlatform = ['web' => 0, 'android' => 0, 'ios' => 0];
        foreach ($activeSubs as $sub) {
            $p = (string) $sub->platform;
            $byPlatform[$p] = ($byPlatform[$p] ?? 0) + 1;
        }

        $sentCount = $this->outbox->where('status', 'sent')->where('created_at >=', $since)->countAllResults();
        $failedCount = $this->outbox->whereIn('status', ['failed', 'expired'])->where('created_at >=', $since)->countAllResults();
        $pendingCount = $this->outbox->where('status', 'pending')->countAllResults();

        return [
            'active_subscribers' => count($activeSubs),
            'by_platform'        => $byPlatform,
            'last_7_days'        => [
                'sent'    => $sentCount,
                'failed'  => $failedCount,
                'pending' => $pendingCount,
            ],
            'push_enabled'       => $this->isGlobalEnabled(),
        ];
    }

    public function getLogs(int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(50, $perPage));
        $offset  = ($page - 1) * $perPage;

        $total = $this->outbox->countAllResults(false);
        $rows  = $this->outbox->orderBy('id', 'DESC')->findAll($perPage, $offset);

        $items = array_map(static function (array $row) {
            $payload = json_decode((string) ($row['payload_json'] ?? '{}'), true) ?: [];
            return [
                'id'              => (int) $row['id'],
                'notification_id' => $row['notification_id'] ? (int) $row['notification_id'] : null,
                'subscription_id' => (int) $row['subscription_id'],
                'status'          => $row['status'],
                'attempts'        => (int) $row['attempts'],
                'title'           => $payload['title'] ?? '',
                'type'            => $payload['data']['type'] ?? '',
                'error'           => $row['error'],
                'created_at'      => $row['created_at'] ? gmdate('Y-m-d\TH:i:s\Z', strtotime($row['created_at'])) : null,
                'sent_at'         => $row['sent_at'] ? gmdate('Y-m-d\TH:i:s\Z', strtotime($row['sent_at'])) : null,
            ];
        }, $rows);

        return [
            'items' => $items,
            'meta'  => [
                'page'        => $page,
                'per_page'    => $perPage,
                'total'       => $total,
                'total_pages' => (int) ceil($total / $perPage),
            ],
        ];
    }
}
