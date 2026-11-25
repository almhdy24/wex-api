<?php

namespace App\Controllers\Api\V1;

use App\Exceptions\ValidationFailedException;
use App\Presenters\CustomerOrderPresenter;
use App\Services\OrderService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * OrderController
 *
 * Customer order management (create, list, view, cancel, reorder).
 * Responses are filtered through CustomerOrderPresenter to expose only SDG amounts.
 */
class OrderController extends BaseApiController
{
    protected OrderService $orderService;

    public function __construct(?OrderService $orderService = null)
    {
        $this->orderService = $orderService ?? new OrderService();
    }

    /**
     * POST /api/v1/orders
     *
     * Place a new order with atomic stock reservation and price lock.
     */
    public function create(): ResponseInterface
    {
        $user = $this->currentUser();
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();

        if (!is_array($payload)) {
            throw new ValidationFailedException('صيغة البيانات غير صحيحة.');
        }

        $headerKey = trim($this->request->getHeaderLine('Idempotency-Key'));
        if ($headerKey !== '') {
            $payload['idempotency_key'] = $headerKey;
        }

        $orderData = $this->orderService->createOrder((int) $user->id, $payload);
        $presented = CustomerOrderPresenter::present($orderData);

        return $this->respondSuccess($presented, 'تم إنشاء الطلب بنجاح وتثبيت السعر', ResponseInterface::HTTP_CREATED);
    }

    /**
     * GET /api/v1/orders
     *
     * List authenticated customer's orders.
     */
    public function index(): ResponseInterface
    {
        $user = $this->currentUser();
        [$page, $perPage] = $this->getPaginationParams();

        $result = $this->orderService->listOrders((int) $user->id, $page, $perPage);
        $presentedItems = CustomerOrderPresenter::presentCollection($result['items']);

        return $this->respondSuccess(
            $presentedItems,
            'قائمة طلباتك',
            ResponseInterface::HTTP_OK,
            $result['meta']
        );
    }

    /**
     * GET /api/v1/orders/{order_number}
     *
     * View detailed order information, items, timeline, and bank instructions.
     */
    public function show(string $orderNumber): ResponseInterface
    {
        $user = $this->currentUser();
        $order = $this->orderService->getOrder($orderNumber, (int) $user->id);
        $presented = CustomerOrderPresenter::present($order);

        return $this->respondSuccess($presented, 'تفاصيل الطلب');
    }

    /**
     * POST /api/v1/orders/{order_number}/cancel
     *
     * Cancel an order in pending_payment status.
     */
    public function cancel(string $orderNumber): ResponseInterface
    {
        $user = $this->currentUser();
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        $reason = $payload['reason'] ?? null;

        $order = $this->orderService->cancelOrder($orderNumber, (int) $user->id, $reason);
        $presented = CustomerOrderPresenter::present($order);

        return $this->respondSuccess($presented, 'تم إلغاء الطلب بنجاح');
    }

    /**
     * POST /api/v1/orders/{order_number}/reorder
     *
     * Create a fresh new order from an expired or cancelled order at current rates.
     */
    public function reorder(string $orderNumber): ResponseInterface
    {
        $user = $this->currentUser();
        $newOrder = $this->orderService->reorder($orderNumber, (int) $user->id);
        $presented = CustomerOrderPresenter::present($newOrder);

        return $this->respondSuccess($presented, 'تم إنشاء طلب جديد من الطلب السابق', ResponseInterface::HTTP_CREATED);
    }
}
