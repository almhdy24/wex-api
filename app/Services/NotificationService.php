<?php

namespace App\Services;

use App\Entities\Notification;
use App\Exceptions\NotFoundException;
use App\Models\NotificationModel;
use App\Models\UserModel;

/**
 * NotificationService
 *
 * Manages in-app notifications and synchronizes with the Email Outbox
 * according to user preferences.
 */
class NotificationService
{
    protected NotificationModel $notifications;
    protected UserModel $users;
    protected MailService $mailService;
    protected ?PushService $pushService;

    public function __construct(
        ?NotificationModel $notifications = null,
        ?UserModel $users = null,
        ?MailService $mailService = null,
        ?PushService $pushService = null
    ) {
        $this->notifications = $notifications ?? new NotificationModel();
        $this->users         = $users ?? new UserModel();
        $this->mailService   = $mailService ?? new MailService();
        $this->pushService   = $pushService;
    }

    protected function getPushService(): PushService
    {
        if ($this->pushService === null) {
            $this->pushService = new PushService();
        }
        return $this->pushService;
    }

    /**
     * Dispatch an in-app notification to a user, and optionally enqueue an email and push.
     */
    public function notify(
        int $userId,
        string $type,
        string $title,
        string $body,
        ?int $orderId = null,
        ?string $emailTemplate = null,
        array $emailPayload = [],
        array $pushOptions = []
    ): Notification {
        $now = gmdate('Y-m-d H:i:s');
        $orderNumber = $pushOptions['order_number'] ?? ($emailPayload['order_number'] ?? null);
        $url = $pushOptions['url'] ?? ($orderNumber ? "/orders/{$orderNumber}" : '/notifications');

        $dataJson = array_filter([
            'type'         => $type,
            'order_id'     => $orderId,
            'order_number' => $orderNumber,
            'url'          => $url,
        ], static fn ($v) => $v !== null);

        // 1. Create in-app notification record
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

        $id = $this->notifications->insert($notification);
        $saved = $this->notifications->find($id);

        // 2. Check user notification preferences for email dispatch
        $user = $this->users->find($userId);
        if ($user && $user->email_notifications && !empty($user->email) && $emailTemplate !== null) {
            $this->mailService->enqueue(
                userId: $userId,
                toEmail: $user->email,
                subject: $title,
                template: $emailTemplate,
                payload: array_merge([
                    'user_name' => $user->name,
                    'title'     => $title,
                ], $emailPayload)
            );
        }

        // 3. Dispatch push notification channel (non-blocking / best-effort)
        if (empty($pushOptions['skip_push'])) {
            try {
                $this->getPushService()->sendToUser($userId, array_merge($pushOptions, [
                    'notification_id' => (int) $saved->id,
                    'type'            => $type,
                    'title'           => $title,
                    'body'            => $body,
                    'order_id'        => $orderId,
                    'order_number'    => $orderNumber,
                    'url'             => $url,
                ]));
            } catch (\Throwable $e) {
                log_message('warning', 'Push dispatch failed for notification {id}: {msg}', [
                    'id'  => $saved->id,
                    'msg' => $e->getMessage(),
                ]);
            }
        }

        return $saved;
    }

    /**
     * Get paginated notifications for a user.
     */
    public function listUserNotifications(int $userId, int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        $builder = $this->notifications->where('user_id', $userId);
        $total   = $builder->countAllResults(false);
        $offset  = ($page - 1) * $perPage;

        $rows = $builder->orderBy('id', 'DESC')->findAll($perPage, $offset);

        $items = array_map(function (Notification $n) {
            $data = $n->getDataArray();
            return [
                'id'           => $n->id,
                'type'         => $n->type,
                'title'        => $n->title,
                'body'         => $n->body,
                'order_id'     => $n->order_id,
                'order_number' => $data['order_number'] ?? null,
                'data'         => $data,
                'is_read'      => $n->isRead(),
                'read_at'      => $n->read_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($n->read_at)) : null,
                'created_at'   => $n->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($n->created_at)) : null,
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

    /**
     * Count unread notifications for a user.
     */
    public function getUnreadCount(int $userId): int
    {
        return $this->notifications
            ->where('user_id', $userId)
            ->where('read_at IS NULL', null, false)
            ->countAllResults();
    }

    /**
     * Mark a single notification as read.
     *
     * @throws NotFoundException
     */
    public function markAsRead(int $notificationId, int $userId): Notification
    {
        $notification = $this->notifications
            ->where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            throw new NotFoundException('الإشعار غير موجود.');
        }

        if (!$notification->isRead()) {
            $now = gmdate('Y-m-d H:i:s');
            $this->notifications->update($notification->id, [
                'read_at' => $now,
            ]);
            $notification->read_at = $now;
        }

        return $notification;
    }

    /**
     * Mark all notifications for a user as read.
     */
    public function markAllAsRead(int $userId): int
    {
        $now = gmdate('Y-m-d H:i:s');
        $this->notifications
            ->where('user_id', $userId)
            ->where('read_at IS NULL', null, false)
            ->set(['read_at' => $now])
            ->update();

        return (new \Config\Database())->connect()->affectedRows();
    }
}
