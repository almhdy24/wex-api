<?php

namespace App\Controllers\Api\V1;

use App\Services\NotificationService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * NotificationController
 *
 * In-app notification endpoints for authenticated users.
 */
class NotificationController extends BaseApiController
{
    protected NotificationService $notificationService;

    public function __construct(?NotificationService $notificationService = null)
    {
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * GET /api/v1/notifications
     *
     * List in-app notifications with pagination.
     */
    public function index(): ResponseInterface
    {
        $user = $this->currentUser();
        [$page, $perPage] = $this->getPaginationParams();

        $result = $this->notificationService->listUserNotifications((int) $user->id, $page, $perPage);

        return $this->respondSuccess(
            $result['items'],
            'قائمة الإشعارات',
            ResponseInterface::HTTP_OK,
            $result['meta']
        );
    }

    /**
     * GET /api/v1/notifications/unread-count
     *
     * Get badge count of unread notifications.
     */
    public function unreadCount(): ResponseInterface
    {
        $user = $this->currentUser();
        $count = $this->notificationService->getUnreadCount((int) $user->id);

        return $this->respondSuccess(['unread_count' => $count], 'عدد الإشعارات غير المقروءة');
    }

    /**
     * PATCH /api/v1/notifications/{id}/read
     *
     * Mark a specific notification as read.
     */
    public function markRead(int $id): ResponseInterface
    {
        $user = $this->currentUser();
        $notification = $this->notificationService->markAsRead($id, (int) $user->id);

        return $this->respondSuccess([
            'id'      => $notification->id,
            'is_read' => true,
            'read_at' => $notification->read_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($notification->read_at)) : null,
        ], 'تم تحديد الإشعار كمقروء');
    }

    /**
     * POST /api/v1/notifications/mark-all-read
     *
     * Mark all notifications as read for current user.
     */
    public function markAllRead(): ResponseInterface
    {
        $user = $this->currentUser();
        $updatedCount = $this->notificationService->markAllAsRead((int) $user->id);

        return $this->respondSuccess(['marked_read' => $updatedCount], 'تم تحديد جميع الإشعارات كمقروءة');
    }
}
