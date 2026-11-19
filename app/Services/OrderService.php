<?php

namespace App\Services;

use App\Entities\Order;
use App\Entities\OrderEvent;
use App\Entities\OrderItem;
use App\Exceptions\ApiException;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Libraries\PhoneValidator;
use App\Models\OrderEventModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\PaymentProofModel;
use App\Models\ProductModel;
use App\Models\ShippingZoneModel;
use App\Models\UserModel;
use Config\Database;
use Config\Wex;

/**
 * OrderService
 *
 * Handles order creation, atomic stock reservations, dual price-lock enforcement,
 * status transitions, reorders, and order timelines.
 */
class OrderService
{
    protected OrderModel $orders;
    protected OrderItemModel $orderItems;
    protected OrderEventModel $orderEvents;
    protected ProductModel $products;
    protected ShippingZoneModel $shippingZones;
    protected PaymentProofModel $paymentProofs;
    protected UserModel $users;
    protected PricingService $pricingService;
    protected SettingService $settingService;
    protected NotificationService $notificationService;

    public function __construct(
        ?OrderModel $orders = null,
        ?OrderItemModel $orderItems = null,
        ?OrderEventModel $orderEvents = null,
        ?ProductModel $products = null,
        ?ShippingZoneModel $shippingZones = null,
        ?PaymentProofModel $paymentProofs = null,
        ?UserModel $users = null,
        ?PricingService $pricingService = null,
        ?SettingService $settingService = null,
        ?NotificationService $notificationService = null
    ) {
        $this->orders              = $orders ?? new OrderModel();
        $this->orderItems          = $orderItems ?? new OrderItemModel();
        $this->orderEvents         = $orderEvents ?? new OrderEventModel();
        $this->products            = $products ?? new ProductModel();
        $this->shippingZones       = $shippingZones ?? new ShippingZoneModel();
        $this->paymentProofs       = $paymentProofs ?? new PaymentProofModel();
        $this->users               = $users ?? new UserModel();
        $this->pricingService      = $pricingService ?? new PricingService();
        $this->settingService      = $settingService ?? new SettingService();
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * Create a new customer order with atomic stock reservation and price lock.
     *
     * @param int $userId Authenticated customer ID
     * @param array<string, mixed> $data Request payload
     * @throws ValidationFailedException|ConflictException
     */
    public function createOrder(int $userId, array $data): array
    {
        $orderType = $data['order_type'] ?? null;
        if (!in_array($orderType, Wex::DELIVERY_TYPES, true)) {
            throw new ValidationFailedException('نوع الطلب غير صحيح. يجب أن يكون استلام من المحل (pickup) أو توصيل (delivery).', [
                'order_type' => 'نوع الطلب مطلوب ويجب أن يكون pickup أو delivery.',
            ]);
        }

        // Validate items
        $items = $data['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            throw new ValidationFailedException('يجب إضافة منتج واحد على الأقل للطلب.', [
                'items' => 'قائمة المنتجات فارغة.',
            ]);
        }

        // Contact phone
        $rawPhone = $data['contact_phone'] ?? '';
        $normalizedPhone = PhoneValidator::normalizeSudanPhone($rawPhone);
        if (!$normalizedPhone) {
            throw new ValidationFailedException('رقم الهاتف للتواصل غير صحيح.', [
                'contact_phone' => 'يرجى إدخال رقم سوداني صحيح (09 / 01).',
            ]);
        }

        // Delivery specifics
        $shippingZone = null;
        $deliveryFeeSdg = 0;
        $deliveryAddress = null;

        if ($orderType === Wex::DELIVERY_TYPE_DELIVERY) {
            $zoneId = (int) ($data['shipping_zone_id'] ?? 0);
            $shippingZone = $this->shippingZones->find($zoneId);
            if (!$shippingZone || !$shippingZone->is_active) {
                throw new ValidationFailedException('منطقة التوصيل المحددة غير متوفرة حالياً.', [
                    'shipping_zone_id' => 'منطقة التوصيل غير صالحة.',
                ]);
            }

            $deliveryAddress = trim((string) ($data['delivery_address'] ?? ''));
            if (mb_strlen($deliveryAddress) < 5) {
                throw new ValidationFailedException('عنوان التوصيل مطلوب بدقة.', [
                    'delivery_address' => 'يرجى كتابة عنوان التوصيل بالتفصيل.',
                ]);
            }

            $deliveryFeeSdg = (int) $shippingZone->fee_sdg;
        }

        $customerNote = isset($data['customer_notes']) ? mb_substr(trim((string) $data['customer_notes']), 0, 500) : null;

        // Consolidate duplicate products if any
        $consolidatedItems = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 1);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            if ($qty > 10) {
                throw new ValidationFailedException('الحد الأقصى للكمية لكل منتج هو 10 قطع.', [
                    'quantity' => 'لا يمكن طلب أكثر من 10 قطع من نفس المنتج.',
                ]);
            }
            $consolidatedItems[$productId] = ($consolidatedItems[$productId] ?? 0) + $qty;
        }

        if (empty($consolidatedItems)) {
            throw new ValidationFailedException('قائمة المنتجات في الطلب غير صالحة.');
        }

        // DB Transaction for atomic stock reservation and order creation
        $db = Database::connect();
        $db->transBegin();

        try {
            $orderItemsToInsert = [];
            $subtotalAed = 0;

            foreach ($consolidatedItems as $productId => $qty) {
                // Fetch product with lock/check
                $product = $this->products->find($productId);
                if (!$product || !$product->is_active) {
                    throw new ConflictException("المنتج رقم ({$productId}) غير متوفر للطلب حالياً.");
                }

                // Atomic stock decrement: UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?
                $db->query(
                    'UPDATE products SET stock_quantity = stock_quantity - ?, updated_at = ? WHERE id = ? AND stock_quantity >= ?',
                    [$qty, gmdate('Y-m-d H:i:s'), $productId, $qty]
                );

                if ($db->affectedRows() !== 1) {
                    throw new ConflictException("المنتج '{$product->name}' لم يعد متوفراً بالكمية المطلوبة ({$qty} قطع).");
                }

                $unitPriceAed = (int) $product->price_aed;
                $lineTotalAed = $unitPriceAed * $qty;
                $subtotalAed += $lineTotalAed;

                $orderItemsToInsert[] = [
                    'product_id'            => $productId,
                    'product_name_snapshot' => $product->name,
                    'product_type_snapshot' => $product->type,
                    'unit_price_aed'        => $unitPriceAed,
                    'quantity'              => $qty,
                    'line_total_aed'        => $lineTotalAed,
                ];
            }

            // Snapshot rate and pricing calculations
            $rateSdg         = $this->pricingService->getCurrentRateSdg();
            $discountPercent = $this->pricingService->getAppDiscountPercent();
            $roundingStep    = $this->pricingService->getRoundingStepSdg();

            $totals = $this->pricingService->calculateOrderTotals(
                subtotalAed: $subtotalAed,
                rateSdg: $rateSdg,
                discountPercent: $discountPercent,
                deliveryFeeSdg: $deliveryFeeSdg,
                roundingStep: $roundingStep
            );

            // Compute price lock expiry time
            $lockHours = (int) $this->settingService->get('lock_hours', 4);
            $nowTime = time();
            $lockExpiresAt = gmdate('Y-m-d H:i:s', $nowTime + ($lockHours * 3600));

            // Generate unique readable order number: WEX-YYMMDD-XXXX
            $orderNumber = $this->generateOrderNumber();

            // Insert Order
            $order = new Order([
                'order_number'                  => $orderNumber,
                'user_id'                       => $userId,
                'status'                        => Wex::ORDER_STATUS_PENDING_PAYMENT,
                'delivery_type'                 => $orderType,
                'shipping_zone_id'              => $shippingZone?->id,
                'delivery_address'              => $deliveryAddress,
                'delivery_phone'                => $normalizedPhone,
                'customer_note'                 => $customerNote,
                'rate_sdg_snapshot'             => $rateSdg,
                'app_discount_percent_snapshot' => $discountPercent,
                'subtotal_aed'                  => $totals['subtotal_aed'],
                'subtotal_sdg'                  => $totals['subtotal_sdg'],
                'discount_sdg'                  => $totals['discount_sdg'],
                'delivery_fee_sdg'              => $totals['delivery_fee_sdg'],
                'total_sdg'                     => $totals['total_sdg'],
                'lock_expires_at'               => $lockExpiresAt,
                'created_at'                    => gmdate('Y-m-d H:i:s', $nowTime),
                'updated_at'                    => gmdate('Y-m-d H:i:s', $nowTime),
            ]);

            $orderId = $this->orders->insert($order);

            // Insert Order Items
            foreach ($orderItemsToInsert as $itemData) {
                $itemData['order_id'] = $orderId;
                $this->orderItems->insert(new OrderItem($itemData));
            }

            // Insert initial timeline event
            $this->orderEvents->insert(new OrderEvent([
                'order_id'      => $orderId,
                'from_status'   => null,
                'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                'actor_user_id' => $userId,
                'note'          => "تم إنشاء الطلب وتثبيت السعر لمدة {$lockHours} ساعات حتى " . gmdate('H:i', strtotime($lockExpiresAt)) . ' UTC',
                'created_at'    => gmdate('Y-m-d H:i:s', $nowTime),
            ]));

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        // Send order created notification
        try {
            $this->notificationService->notify(
                userId: $userId,
                type: 'order_created',
                title: 'تم استلام طلبك بنجاح',
                body: "تم استلام طلبك رقم {$orderNumber} وحجز السعر بالجنيه السوداني.",
                orderId: (int) $orderId,
                emailTemplate: 'order_created',
                emailPayload: [
                    'order_number'        => $orderNumber,
                    'delivery_type'       => $orderType,
                    'total_sdg'           => $totals['total_sdg'],
                    'lock_expires_at'     => gmdate('Y-m-d H:i', strtotime($lockExpiresAt)) . ' UTC',
                    'bank_account_name'   => $this->settingService->get('bank_account_name'),
                    'bank_account_number' => $this->settingService->get('bank_account_number'),
                ]
            );
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to dispatch order_created notification: {msg}', ['msg' => $e->getMessage()]);
        }

        // Return order detail structure with bank transfer instructions
        return $this->getOrder($orderNumber, $userId);
    }

    /**
     * Lazy lock expiry check.
     * If order is pending_payment and current time is past lock_expires_at,
     * atomically marks order as expired, restores reserved stock, and logs timeline event.
     *
     * @return bool True if order was transitioned to expired, false otherwise.
     */
    public function refreshExpiry(Order $order): bool
    {
        if ($order->status !== Wex::ORDER_STATUS_PENDING_PAYMENT) {
            return false;
        }

        $now = time();
        $expiresAt = strtotime($order->lock_expires_at);

        if ($now <= $expiresAt) {
            return false;
        }

        // Atomically update status in DB
        $db = Database::connect();
        $nowStr = gmdate('Y-m-d H:i:s', $now);

        $db->query(
            "UPDATE orders SET status = ?, updated_at = ? WHERE id = ? AND status = ?",
            [Wex::ORDER_STATUS_EXPIRED, $nowStr, $order->id, Wex::ORDER_STATUS_PENDING_PAYMENT]
        );

        if ($db->affectedRows() === 1) {
            // Restore reserved stock
            $this->restoreStockForOrder((int) $order->id);

            // Add timeline event
            $this->orderEvents->insert(new OrderEvent([
                'order_id'      => $order->id,
                'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                'to_status'     => Wex::ORDER_STATUS_EXPIRED,
                'actor_user_id' => null, // system
                'note'          => 'انتهت فترة حجز السعر المحددة للطلب وتم إلغاء الحجز واسترجاع المنتجات للمخزون.',
                'created_at'    => $nowStr,
            ]));

            $order->status = Wex::ORDER_STATUS_EXPIRED;
            $order->updated_at = $nowStr;

            // Send notification for expired order
            try {
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_expired',
                    title: 'انتهاء فترة حجز السعر للطلب',
                    body: "تم إلغاء الطلب رقم {$order->order_number} لانتهاء فترة حجز السعر المحددة.",
                    orderId: (int) $order->id,
                    emailTemplate: 'order_expired',
                    emailPayload: [
                        'order_number' => $order->order_number,
                    ]
                );
            } catch (\Throwable $e) {
                log_message('warning', 'Failed to dispatch order_expired notification: {msg}', ['msg' => $e->getMessage()]);
            }

            return true;
        }

        return false;
    }

    /**
     * Finds and expires all overdue orders across the system.
     * Executed by the cron worker `orders:expire`.
     *
     * @return int Count of newly expired orders
     */
    public function expireOverdueOrders(): int
    {
        $nowStr = gmdate('Y-m-d H:i:s');
        $overdueOrders = $this->orders
            ->where('status', Wex::ORDER_STATUS_PENDING_PAYMENT)
            ->where('lock_expires_at <', $nowStr)
            ->findAll();

        $count = 0;
        foreach ($overdueOrders as $order) {
            if ($this->refreshExpiry($order)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Customer cancels an order. Allowed only while status is pending_payment.
     *
     * @throws NotFoundException|ForbiddenException|ConflictException
     */
    public function cancelOrder(string $orderNumber, int $userId, ?string $reason = null): array
    {
        $order = $this->orders->where('order_number', $orderNumber)->first();
        if (!$order) {
            throw new NotFoundException('الطلب غير موجود.');
        }

        if ((int) $order->user_id !== $userId) {
            throw new ForbiddenException('غير مصرح لك بإلغاء هذا الطلب.');
        }

        // Lazy expiry check
        $this->refreshExpiry($order);

        if ($order->status !== Wex::ORDER_STATUS_PENDING_PAYMENT) {
            throw new ConflictException('يمكن إلغاء الطلب فقط عندما يكون في حالة انتظار التحويل البنكي.');
        }

        $nowStr = gmdate('Y-m-d H:i:s');
        $cancelReason = $reason ? mb_substr(trim($reason), 0, 255) : 'إلغاء من قبل العميل';

        $db = Database::connect();
        $db->transBegin();

        try {
            $db->query(
                "UPDATE orders SET status = ?, cancelled_reason = ?, updated_at = ? WHERE id = ? AND status = ?",
                [Wex::ORDER_STATUS_CANCELLED, $cancelReason, $nowStr, $order->id, Wex::ORDER_STATUS_PENDING_PAYMENT]
            );

            if ($db->affectedRows() !== 1) {
                throw new ConflictException('تعذر إلغاء الطلب، قد تكون حالته قد تغيرت بالفعل.');
            }

            // Restore reserved stock
            $this->restoreStockForOrder((int) $order->id);

            // Add timeline event
            $this->orderEvents->insert(new OrderEvent([
                'order_id'      => $order->id,
                'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                'to_status'     => Wex::ORDER_STATUS_CANCELLED,
                'actor_user_id' => $userId,
                'note'          => 'تم إلغاء الطلب بناءً على رغبة العميل: ' . $cancelReason,
                'created_at'    => $nowStr,
            ]));

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        return $this->getOrder($orderNumber, $userId);
    }

    /**
     * Reorder from an expired or cancelled order.
     * Creates a fresh new order using current active exchange rate and stock.
     */
    public function reorder(string $orderNumber, int $userId): array
    {
        $oldOrder = $this->orders->where('order_number', $orderNumber)->first();
        if (!$oldOrder) {
            throw new NotFoundException('الطلب غير موجود.');
        }

        if ((int) $oldOrder->user_id !== $userId) {
            throw new ForbiddenException('غير مصرح لك بإعادة طلب هذا المنتج.');
        }

        $oldItems = $this->orderItems->where('order_id', $oldOrder->id)->findAll();
        if (empty($oldItems)) {
            throw new ConflictException('لا توجد عناصر في هذا الطلب لإعادة طلبها.');
        }

        $itemsPayload = [];
        foreach ($oldItems as $item) {
            $itemsPayload[] = [
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
            ];
        }

        $payload = [
            'order_type'       => $oldOrder->delivery_type,
            'shipping_zone_id' => $oldOrder->shipping_zone_id,
            'delivery_address' => $oldOrder->delivery_address,
            'contact_phone'    => $oldOrder->delivery_phone,
            'customer_notes'   => $oldOrder->customer_note,
            'items'            => $itemsPayload,
        ];

        return $this->createOrder($userId, $payload);
    }

    /**
     * Retrieve complete order details, items, timeline, and bank transfer info.
     *
     * @throws NotFoundException|ForbiddenException
     */
    public function getOrder(string $orderNumber, ?int $userId = null, bool $isAdminOrStaff = false): array
    {
        $order = $this->orders->where('order_number', $orderNumber)->first();
        if (!$order) {
            throw new NotFoundException('الطلب غير موجود.');
        }

        // Ownership authorization check
        if (!$isAdminOrStaff && (int) $order->user_id !== $userId) {
            throw new ForbiddenException('غير مصرح لك بمشاهدة هذا الطلب.');
        }

        // Check lazy lock expiry
        $this->refreshExpiry($order);

        // Fetch items
        $items = $this->orderItems->where('order_id', $order->id)->findAll();
        $formattedItems = array_map(function (OrderItem $item) {
            return [
                'id'             => $item->id,
                'product_id'     => $item->product_id,
                'name'           => $item->product_name_snapshot,
                'type'           => $item->product_type_snapshot,
                'unit_price_aed' => (int) $item->unit_price_aed,
                'quantity'       => (int) $item->quantity,
                'line_total_aed' => (int) $item->line_total_aed,
            ];
        }, $items);

        // Fetch events timeline
        $events = $this->orderEvents
            ->where('order_id', $order->id)
            ->orderBy('id', 'ASC')
            ->findAll();

        $formattedEvents = array_map(function (OrderEvent $e) {
            return [
                'id'          => $e->id,
                'from_status' => $e->from_status,
                'to_status'   => $e->to_status,
                'status_text' => OrderStateMachine::getStatusLabel($e->to_status),
                'note'        => $e->note,
                'created_at'  => $e->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($e->created_at)) : null,
            ];
        }, $events);

        // Fetch payment proofs
        $proofs = $this->paymentProofs
            ->where('order_id', $order->id)
            ->orderBy('id', 'DESC')
            ->findAll();

        $formattedProofs = array_map(function ($p) use ($order) {
            return [
                'id'               => $p->id,
                'status'           => $p->status,
                'sender_name'      => $p->sender_name,
                'reference_number' => $p->reference_number,
                'review_note'      => $p->review_note,
                'image_url'        => "/api/v1/orders/{$order->order_number}/payment-proof/{$p->id}/image",
                'created_at'       => $p->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($p->created_at)) : null,
                'reviewed_at'      => $p->reviewed_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($p->reviewed_at)) : null,
            ];
        }, $proofs);

        // Calculate lock seconds remaining
        $now = time();
        $expiresAt = strtotime($order->lock_expires_at);
        $secondsRemaining = ($order->status === Wex::ORDER_STATUS_PENDING_PAYMENT)
            ? max(0, $expiresAt - $now)
            : 0;

        // Public bank details for payment
        $bankDetails = [
            'bank_name'           => $this->settingService->get('bank_name', 'بنك الخرطوم (تطبيق بنكك)'),
            'bank_account_name'   => $this->settingService->get('bank_account_name', 'Wex Technology - ويكس تكنولوجي'),
            'bank_account_number' => $this->settingService->get('bank_account_number', '2849102'),
            'amount_sdg'          => (int) $order->total_sdg,
            'order_number'        => $order->order_number,
        ];

        return [
            'order_number'                  => $order->order_number,
            'status'                        => $order->status,
            'status_label'                  => OrderStateMachine::getStatusLabel($order->status),
            'delivery_type'                 => $order->delivery_type,
            'delivery_phone'                => $order->delivery_phone,
            'delivery_address'              => $order->delivery_address,
            'customer_note'                 => $order->customer_note,
            'rate_sdg_snapshot'             => (int) $order->rate_sdg_snapshot,
            'app_discount_percent_snapshot' => (int) $order->app_discount_percent_snapshot,
            'subtotal_aed'                  => (int) $order->subtotal_aed,
            'subtotal_sdg'                  => (int) $order->subtotal_sdg,
            'discount_sdg'                  => (int) $order->discount_sdg,
            'delivery_fee_sdg'              => (int) $order->delivery_fee_sdg,
            'total_sdg'                     => (int) $order->total_sdg,
            'lock_expires_at'               => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
            'seconds_remaining'             => $secondsRemaining,
            'courier_name'                  => $order->courier_name,
            'courier_phone'                 => $order->courier_phone,
            'cancelled_reason'              => $order->cancelled_reason,
            'bank_details'                  => $bankDetails,
            'items'                         => $formattedItems,
            'timeline'                      => $formattedEvents,
            'payment_proofs'                => $formattedProofs,
            'created_at'                    => $order->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($order->created_at)) : null,
            'updated_at'                    => $order->updated_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($order->updated_at)) : null,
        ];
    }

    /**
     * List authenticated customer orders.
     */
    public function listOrders(int $userId, int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        $builder = $this->orders->where('user_id', $userId);
        $total   = $builder->countAllResults(false);
        $offset  = ($page - 1) * $perPage;

        $orders = $builder->orderBy('id', 'DESC')->findAll($perPage, $offset);

        $items = [];
        foreach ($orders as $order) {
            $this->refreshExpiry($order);

            $expiresAt = strtotime($order->lock_expires_at);
            $secondsRemaining = ($order->status === Wex::ORDER_STATUS_PENDING_PAYMENT)
                ? max(0, $expiresAt - time())
                : 0;

            $items[] = [
                'order_number'      => $order->order_number,
                'status'            => $order->status,
                'status_label'      => OrderStateMachine::getStatusLabel($order->status),
                'delivery_type'     => $order->delivery_type,
                'total_sdg'         => (int) $order->total_sdg,
                'lock_expires_at'   => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
                'seconds_remaining' => $secondsRemaining,
                'created_at'        => $order->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($order->created_at)) : null,
            ];
        }

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
     * List all orders for Admin / Staff with filtering and search.
     */
    public function listAdminOrders(array $filters, int $page = 1, int $perPage = 20): array
    {
        $page    = max(1, $page);
        $perPage = max(1, min(50, $perPage));

        $builder = $this->orders;

        if (!empty($filters['status']) && in_array($filters['status'], Wex::ORDER_STATUSES, true)) {
            $builder->where('status', $filters['status']);
        }

        if (!empty($filters['delivery_type']) && in_array($filters['delivery_type'], Wex::DELIVERY_TYPES, true)) {
            $builder->where('delivery_type', $filters['delivery_type']);
        }

        if (!empty($filters['q'])) {
            $q = trim($filters['q']);
            $builder->groupStart()
                ->like('order_number', $q)
                ->orLike('delivery_phone', $q)
                ->groupEnd();
        }

        $total  = $builder->countAllResults(false);
        $offset = ($page - 1) * $perPage;

        $orders = $builder->orderBy('id', 'DESC')->findAll($perPage, $offset);

        $items = [];
        foreach ($orders as $order) {
            $this->refreshExpiry($order);

            $expiresAt = strtotime($order->lock_expires_at);
            $secondsRemaining = ($order->status === Wex::ORDER_STATUS_PENDING_PAYMENT)
                ? max(0, $expiresAt - time())
                : 0;

            $items[] = [
                'order_number'      => $order->order_number,
                'user_id'           => (int) $order->user_id,
                'status'            => $order->status,
                'status_label'      => OrderStateMachine::getStatusLabel($order->status),
                'delivery_type'     => $order->delivery_type,
                'delivery_phone'    => $order->delivery_phone,
                'total_sdg'         => (int) $order->total_sdg,
                'lock_expires_at'   => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
                'seconds_remaining' => $secondsRemaining,
                'created_at'        => $order->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($order->created_at)) : null,
            ];
        }

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
     * Admin/Staff updates order status.
     *
     * @throws NotFoundException|ConflictException
     */
    public function updateOrderStatus(
        string $orderNumber,
        string $newStatus,
        int $actorUserId,
        ?string $note = null,
        array $extra = []
    ): array {
        $order = $this->orders->where('order_number', $orderNumber)->first();
        if (!$order) {
            throw new NotFoundException('الطلب غير موجود.');
        }

        $fromStatus = $order->status;
        OrderStateMachine::assertCanTransition($fromStatus, $newStatus);

        $nowStr = gmdate('Y-m-d H:i:s');
        $db = Database::connect();
        $db->transBegin();

        try {
            $updateData = [
                'status'     => $newStatus,
                'updated_at' => $nowStr,
            ];

            if ($newStatus === Wex::ORDER_STATUS_OUT_FOR_DELIVERY) {
                if (!empty($extra['courier_name'])) {
                    $updateData['courier_name'] = trim((string) $extra['courier_name']);
                }
                if (!empty($extra['courier_phone'])) {
                    $updateData['courier_phone'] = trim((string) $extra['courier_phone']);
                }
            }

            if ($newStatus === Wex::ORDER_STATUS_CANCELLED && !empty($extra['cancelled_reason'])) {
                $updateData['cancelled_reason'] = trim((string) $extra['cancelled_reason']);
            }

            $this->orders->update($order->id, $updateData);

            // Restore stock if needed
            if (OrderStateMachine::shouldRestoreStock($fromStatus, $newStatus)) {
                $this->restoreStockForOrder((int) $order->id);
            }

            // Timeline event
            $eventNote = $note ?? ('تم تغيير حالة الطلب إلى: ' . OrderStateMachine::getStatusLabel($newStatus));
            $this->orderEvents->insert(new OrderEvent([
                'order_id'      => $order->id,
                'from_status'   => $fromStatus,
                'to_status'     => $newStatus,
                'actor_user_id' => $actorUserId,
                'note'          => $eventNote,
                'created_at'    => $nowStr,
            ]));

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        // Send status change notification
        try {
            if ($newStatus === Wex::ORDER_STATUS_CONFIRMED) {
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_confirmed',
                    title: 'تم تأكيد طلبك بنجاح',
                    body: "تم تأكيد طلبك رقم {$orderNumber} وجاري تجهيزه.",
                    orderId: (int) $order->id,
                    emailTemplate: 'payment_approved',
                    emailPayload: [
                        'order_number'  => $orderNumber,
                        'total_sdg'     => (int) $order->total_sdg,
                        'delivery_type' => $order->delivery_type,
                    ]
                );
            } elseif ($newStatus === Wex::ORDER_STATUS_READY_FOR_PICKUP) {
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_ready_pickup',
                    title: 'طلبك جاهز للاستلام من المحل',
                    body: "طلبك رقم {$orderNumber} جاهز الآن للاستلام من فرع أم درمان.",
                    orderId: (int) $order->id,
                    emailTemplate: 'ready_for_pickup',
                    emailPayload: [
                        'order_number' => $orderNumber,
                        'shop_phone'   => $this->settingService->get('shop_phone'),
                    ]
                );
            } elseif ($newStatus === Wex::ORDER_STATUS_OUT_FOR_DELIVERY) {
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_out_delivery',
                    title: 'طلبك في الطريق إليك مع المندوب',
                    body: "طلبك رقم {$orderNumber} خرج مع مندوب التوصيل.",
                    orderId: (int) $order->id,
                    emailTemplate: 'out_for_delivery',
                    emailPayload: [
                        'order_number'     => $orderNumber,
                        'courier_name'     => $updateData['courier_name'] ?? null,
                        'courier_phone'    => $updateData['courier_phone'] ?? null,
                        'delivery_address' => $order->delivery_address,
                    ]
                );
            } elseif ($newStatus === Wex::ORDER_STATUS_COMPLETED) {
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_completed',
                    title: 'تم تسليم طلبك بنجاح',
                    body: "تم تسليم طلبك رقم {$orderNumber} بنجاح. شكراً لثقتك في ويكس تكنولوجي.",
                    orderId: (int) $order->id,
                    emailTemplate: 'order_completed',
                    emailPayload: [
                        'order_number' => $orderNumber,
                        'total_sdg'    => (int) $order->total_sdg,
                    ]
                );
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to dispatch status update notification: {msg}', ['msg' => $e->getMessage()]);
        }

        return $this->getOrder($orderNumber, null, true);
    }

    /**
     * Admin extends price lock expiry for an order.
     *
     * @throws NotFoundException|ConflictException
     */
    public function extendLock(string $orderNumber, int $extraHours, int $actorUserId): array
    {
        $order = $this->orders->where('order_number', $orderNumber)->first();
        if (!$order) {
            throw new NotFoundException('الطلب غير موجود.');
        }

        if (!in_array($order->status, [Wex::ORDER_STATUS_PENDING_PAYMENT, Wex::ORDER_STATUS_PAYMENT_REVIEW], true)) {
            throw new ConflictException('يمكن تمديد حجز السعر فقط للطلبات التي في مرحلة الدفع أو المراجعة.');
        }

        $extraHours = max(1, min(72, $extraHours));
        $currentExpiry = strtotime($order->lock_expires_at);
        $baseTime = max(time(), $currentExpiry);
        $newExpiry = gmdate('Y-m-d H:i:s', $baseTime + ($extraHours * 3600));

        $this->orders->update($order->id, [
            'lock_expires_at' => $newExpiry,
            'updated_at'      => gmdate('Y-m-d H:i:s'),
        ]);

        $this->orderEvents->insert(new OrderEvent([
            'order_id'      => $order->id,
            'from_status'   => $order->status,
            'to_status'     => $order->status,
            'actor_user_id' => $actorUserId,
            'note'          => "قام المشرف بتمديد فترة حجز السعر لمدة {$extraHours} ساعات إضافية حتى " . gmdate('H:i', strtotime($newExpiry)) . ' UTC',
            'created_at'    => gmdate('Y-m-d H:i:s'),
        ]));

        return $this->getOrder($orderNumber, null, true);
    }

    /**
     * Helper to restore reserved stock for an order's items.
     */
    protected function restoreStockForOrder(int $orderId): void
    {
        $items = $this->orderItems->where('order_id', $orderId)->findAll();
        $db = Database::connect();
        $now = gmdate('Y-m-d H:i:s');

        foreach ($items as $item) {
            $db->query(
                'UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = ? WHERE id = ?',
                [$item->quantity, $now, $item->product_id]
            );
        }
    }

    /**
     * Generate unique readable order number: WEX-YYMMDD-XXXXX
     */
    protected function generateOrderNumber(): string
    {
        $prefix = 'WEX-' . gmdate('ymd') . '-';
        for ($i = 0; $i < 5; $i++) {
            $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
            $candidate = $prefix . $suffix;
            if (!$this->orders->where('order_number', $candidate)->first()) {
                return $candidate;
            }
        }
        return $prefix . strtoupper(bin2hex(random_bytes(4)));
    }
}
