<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Exceptions\ValidationFailedException;
use App\Presenters\AdminOrderPresenter;
use App\Services\OrderService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Wex;

/**
 * OrderAdminController
 *
 * Admin and Staff management for customer orders, transitions, and price lock extensions.
 * Responses are filtered through AdminOrderPresenter.
 */
class OrderAdminController extends BaseApiController
{
    protected OrderService $orderService;

    public function __construct(?OrderService $orderService = null)
    {
        $this->orderService = $orderService ?? new OrderService();
    }

    /**
     * GET /api/v1/admin/orders
     *
     * List orders with optional filters (status, delivery_type, q) and pagination.
     */
    public function index(): ResponseInterface
    {
        [$page, $perPage] = $this->getPaginationParams();

        $filters = [
            'status'        => $this->request->getGet('status'),
            'delivery_type' => $this->request->getGet('delivery_type'),
            'q'             => $this->request->getGet('q'),
        ];

        $result = $this->orderService->listAdminOrders($filters, $page, $perPage);
        $presentedItems = AdminOrderPresenter::presentCollection($result['items']);

        return $this->respondSuccess(
            $presentedItems,
            'قائمة طلبات العملاء',
            ResponseInterface::HTTP_OK,
            $result['meta']
        );
    }

    /**
     * GET /api/v1/admin/orders/{order_number}
     *
     * View detailed order data including audit trail, proofs, and customer info.
     */
    public function show(string $orderNumber): ResponseInterface
    {
        $order = $this->orderService->getOrder($orderNumber, null, true);
        $presented = AdminOrderPresenter::present($order);

        return $this->respondSuccess($presented, 'تفاصيل الطلب');
    }

    /**
     * PATCH /api/v1/admin/orders/{order_number}/status
     *
     * Transition order status (confirmed, ready_for_pickup, out_for_delivery, completed, cancelled).
     */
    public function updateStatus(string $orderNumber): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (!is_array($payload) || empty($payload['status'])) {
            throw new ValidationFailedException('الحالة الجديدة للطلب مطلوبة.', [
                'status' => 'حقل الحالة مطلوب.',
            ]);
        }

        $newStatus = trim((string) $payload['status']);
        if (!in_array($newStatus, Wex::ORDER_STATUSES, true)) {
            throw new ValidationFailedException('حالة الطلب المحددة غير صحيحة.', [
                'status' => 'الحالة المحددة غير معروفة.',
            ]);
        }

        $user = $this->currentUser();
        $note = isset($payload['note']) ? trim((string) $payload['note']) : null;

        $updated = $this->orderService->updateOrderStatus(
            orderNumber: $orderNumber,
            newStatus: $newStatus,
            actorUserId: (int) $user->id,
            note: $note,
            extra: $payload
        );
        $presented = AdminOrderPresenter::present($updated);

        return $this->respondSuccess($presented, 'تم تحديث حالة الطلب بنجاح');
    }

    /**
     * POST /api/v1/admin/orders/{order_number}/lock
     *
     * Extend price lock for an order by N hours.
     */
    public function extendLock(string $orderNumber): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $hours = (int) ($payload['hours'] ?? 4);

        if ($hours < 1 || $hours > 72) {
            throw new ValidationFailedException('فترة التمديد يجب أن تكون بين ساعة و 72 ساعة.', [
                'hours' => 'قيمة الساعات غير صالحة.',
            ]);
        }

        $user = $this->currentUser();
        $updated = $this->orderService->extendLock($orderNumber, $hours, (int) $user->id);
        $presented = AdminOrderPresenter::present($updated);

        return $this->respondSuccess($presented, 'تم تمديد فترة حجز السعر بنجاح');
    }

    /**
     * POST /api/v1/admin/orders/{order_number}/reset-orphan
     *
     * Reset or expire an orphan order stuck in payment_review without proofs.
     */
    public function resetOrphan(string $orderNumber): ResponseInterface
    {
        $user = $this->currentUser();
        $updated = $this->orderService->resetOrphanOrder($orderNumber, (int) $user->id);
        $presented = AdminOrderPresenter::present($updated);

        return $this->respondSuccess($presented, 'تمت معالجة الطلب وإعادته للحالة المناسبة بنجاح');
    }
}
