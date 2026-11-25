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
    protected PaymentAccountService $paymentAccountService;

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
        ?NotificationService $notificationService = null,
        ?PaymentAccountService $paymentAccountService = null
    ) {
        $this->orders                = $orders ?? new OrderModel();
        $this->orderItems            = $orderItems ?? new OrderItemModel();
        $this->orderEvents           = $orderEvents ?? new OrderEventModel();
        $this->products              = $products ?? new ProductModel();
        $this->shippingZones         = $shippingZones ?? new ShippingZoneModel();
        $this->paymentProofs         = $paymentProofs ?? new PaymentProofModel();
        $this->users                 = $users ?? new UserModel();
        $this->pricingService        = $pricingService ?? new PricingService();
        $this->settingService        = $settingService ?? new SettingService();
        $this->notificationService   = $notificationService ?? new NotificationService();
        $this->paymentAccountService = $paymentAccountService ?? new PaymentAccountService();
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
        // Guard 1: Are orders enabled?
        $ordersEnabled = $this->settingService->get('orders_enabled', '1');
        if ($ordersEnabled === '0' || $ordersEnabled === 0 || $ordersEnabled === false) {
            $msg = (string) $this->settingService->get('orders_disabled_message', 'استقبال الطلبات متوقف مؤقتاً في الوقت الحالي، نعتذر عن الإزعاج.');
            throw new ValidationFailedException($msg, ['orders' => $msg]);
        }

        // Guard 2: Are there active payment accounts?
        $activeAccounts = $this->paymentAccountService->getActiveAccounts();
        if (empty($activeAccounts)) {
            throw new ValidationFailedException('لا توجد حسابات دفع بنكية مفعلة حالياً لإتمام الطلب، يرجى التواصل مع إدارة المتجر.', [
                'payment_accounts' => 'لا توجد حسابات دفع مفعلة حالياً.',
            ]);
        }

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

        // Idempotency: a replay of the same checkout returns the original order.
        $idempotencyKey = isset($data['idempotency_key']) ? trim((string) $data['idempotency_key']) : '';
        if ($idempotencyKey !== '') {
            if (!preg_match('/^[A-Za-z0-9_-]{8,64}$/', $idempotencyKey)) {
                throw new ValidationFailedException('مفتاح منع التكرار غير صالح.', [
                    'idempotency_key' => 'يجب أن يتكون المفتاح من 8 إلى 64 حرفاً أو رقماً.',
                ]);
            }
            $existing = $this->findByIdempotencyKey($userId, $idempotencyKey);
            if ($existing) {
                return $this->getOrder($existing->order_number, $userId);
            }
        } else {
            $idempotencyKey = null;
        }

        // DB Transaction for atomic stock reservation and order creation
        $db = Database::connect();
        $db->transBegin();

        try {
            // Snapshot rate and pricing parameters
            $rateSdg         = $this->pricingService->getCurrentRateSdg();
            $discountPercent = $this->pricingService->getAppDiscountPercent();
            $roundingStep    = $this->pricingService->getRoundingStepSdg();

            $orderItemsToInsert = [];
            $lowStockProducts = [];
            $lowStockThreshold = (int) $this->settingService->get('low_stock_threshold', 2);
            $subtotalAed = 0;
            $subtotalSdg = 0;

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

                $newStock = (int) $product->stock_quantity - $qty;
                if ((int) $product->stock_quantity > $lowStockThreshold && $newStock <= $lowStockThreshold) {
                    $lowStockProducts[] = [
                        'id'   => (int) $productId,
                        'name' => (string) $product->name,
                    ];
                }

                $unitPriceAed = (int) $product->price_aed;
                $itemPricing = $this->pricingService->calculateOrderItemPrices(
                    priceAed: $unitPriceAed,
                    rateSdg: $rateSdg,
                    quantity: $qty,
                    roundingStep: $roundingStep
                );

                $subtotalAed += $itemPricing['line_total_aed'];
                $subtotalSdg += $itemPricing['line_total_sdg'];

                $orderItemsToInsert[] = [
                    'product_id'            => $productId,
                    'product_name_snapshot' => $product->name,
                    'product_type_snapshot' => $product->type,
                    'unit_price_aed'        => $unitPriceAed,
                    'quantity'              => $qty,
                    'line_total_aed'        => $itemPricing['line_total_aed'],
                    'unit_price_sdg'        => $itemPricing['unit_price_sdg'],
                    'line_total_sdg'        => $itemPricing['line_total_sdg'],
                ];
            }

            $totals = $this->pricingService->calculateOrderTotals(
                subtotalAed: $subtotalAed,
                rateSdg: $rateSdg,
                discountPercent: $discountPercent,
                deliveryFeeSdg: $deliveryFeeSdg,
                roundingStep: $roundingStep,
                subtotalSdg: $subtotalSdg
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
                'idempotency_key'               => $idempotencyKey,
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
            $eventMeta = [
                'lock_hours'      => (int) $lockHours,
                'lock_expires_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime($lockExpiresAt)),
            ];
            $this->orderEvents->insert(new OrderEvent([
                'order_id'      => $orderId,
                'from_status'   => null,
                'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                'actor_user_id' => $userId,
                'event_type'    => 'order_created',
                'meta'          => json_encode($eventMeta),
                'note'          => "تم إنشاء الطلب وتثبيت السعر لمدة {$lockHours} ساعات",
                'created_at'    => gmdate('Y-m-d H:i:s', $nowTime),
            ]));

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();

            // A concurrent request with the same key won the unique index: return its order.
            if ($idempotencyKey !== null) {
                $winner = $this->findByIdempotencyKey($userId, $idempotencyKey);
                if ($winner) {
                    return $this->getOrder($winner->order_number, $userId);
                }
            }
            throw $e;
        }

        // Send order created notification
        try {
            $tpl = \Config\PushTemplates::render('order_created', ['order_number' => $orderNumber]);
            $this->notificationService->notify(
                userId: $userId,
                type: 'order_created',
                title: $tpl['title'],
                body: $tpl['body'],
                orderId: (int) $orderId,
                emailTemplate: 'order_created',
                emailPayload: [
                    'order_number'        => $orderNumber,
                    'delivery_type'       => $orderType,
                    'total_sdg'           => $totals['total_sdg'],
                    'lock_expires_at'     => gmdate('Y-m-d H:i', strtotime($lockExpiresAt)),
                    'bank_account_name'   => $this->settingService->get('bank_account_name'),
                    'bank_account_number' => $this->settingService->get('bank_account_number'),
                ],
                pushOptions: [
                    'order_number'          => $orderNumber,
                    'url'                   => "/orders/{$orderNumber}",
                    'expected_order_status' => Wex::ORDER_STATUS_PENDING_PAYMENT,
                ]
            );

            $pushService = new PushService();
            $adminTpl = \Config\PushTemplates::render('admin_new_order', ['order_number' => $orderNumber]);
            $pushService->sendToRole([Wex::ROLE_ADMIN, Wex::ROLE_STAFF], [
                'type'          => 'admin_new_order',
                'title'         => $adminTpl['title'],
                'body'          => $adminTpl['body'],
                'order_id'      => (int) $orderId,
                'order_number'  => $orderNumber,
                'url'           => "/admin/orders/{$orderNumber}",
                'actor_user_id' => $userId,
            ]);

            foreach ($lowStockProducts as $ls) {
                $lsTpl = \Config\PushTemplates::render('admin_low_stock', ['product_name' => $ls['name']]);
                $pushService->sendToRole([Wex::ROLE_ADMIN, Wex::ROLE_STAFF], [
                    'type'  => 'admin_low_stock',
                    'title' => $lsTpl['title'],
                    'body'  => $lsTpl['body'],
                    'url'   => '/admin/products',
                    'tag'   => 'low-stock-' . $ls['id'],
                ]);
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to dispatch order_created notification: {msg}', ['msg' => $e->getMessage()]);
        }

        // Return order detail structure with bank transfer instructions
        return $this->getOrder($orderNumber, $userId);
    }

    protected function findByIdempotencyKey(int $userId, string $key): ?Order
    {
        return $this->orders
            ->where('user_id', $userId)
            ->where('idempotency_key', $key)
            ->first();
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

        // Atomically update status, restore stock and log the event in ONE transaction:
        // a failure half-way must not leave an expired order whose stock was never released.
        $db = Database::connect();
        $nowStr = gmdate('Y-m-d H:i:s', $now);

        $db->transBegin();
        try {
            $db->query(
                "UPDATE orders SET status = ?, updated_at = ? WHERE id = ? AND status = ?",
                [Wex::ORDER_STATUS_EXPIRED, $nowStr, $order->id, Wex::ORDER_STATUS_PENDING_PAYMENT]
            );
            $expired = $db->affectedRows() === 1;

            if ($expired) {
                $this->restoreStockForOrder((int) $order->id);

                $this->orderEvents->insert(new OrderEvent([
                    'order_id'      => $order->id,
                    'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                    'to_status'     => Wex::ORDER_STATUS_EXPIRED,
                    'actor_user_id' => null, // system
                    'event_type'    => 'order_expired',
                    'meta'          => json_encode(['reason' => 'lock_expired']),
                    'note'          => 'انتهت فترة حجز السعر المحددة للطلب وتم إلغاء الحجز وإعادة المنتجات إلى المخزون.',
                    'created_at'    => $nowStr,
                ]));
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        if ($expired) {
            $order->status = Wex::ORDER_STATUS_EXPIRED;
            $order->updated_at = $nowStr;

            // Send notification for expired order
            try {
                $tpl = \Config\PushTemplates::render('order_expired', ['order_number' => $order->order_number]);
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_expired',
                    title: $tpl['title'],
                    body: $tpl['body'],
                    orderId: (int) $order->id,
                    emailTemplate: 'order_expired',
                    emailPayload: [
                        'order_number' => $order->order_number,
                    ],
                    pushOptions: [
                        'order_number'          => $order->order_number,
                        'url'                   => "/orders/{$order->order_number}",
                        'expected_order_status' => Wex::ORDER_STATUS_EXPIRED,
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
        $formattedItems = array_map(function (OrderItem $item) use ($order) {
            $unitPriceSdg = (int) ($item->unit_price_sdg ?? 0);
            $lineTotalSdg = (int) ($item->line_total_sdg ?? 0);
            if ($unitPriceSdg === 0 && !empty($item->unit_price_aed)) {
                $rate = (int) ($order->rate_sdg_snapshot ?? 650);
                $step = $this->pricingService->getRoundingStepSdg();
                $unitPriceSdg = $this->pricingService->convertAedToSdg((int) $item->unit_price_aed, $rate, $step);
                $lineTotalSdg = $unitPriceSdg * (int) $item->quantity;
            }

            return [
                'id'             => $item->id,
                'product_id'     => $item->product_id,
                'name'           => $item->product_name_snapshot,
                'type'           => $item->product_type_snapshot,
                'unit_price_aed' => (int) $item->unit_price_aed,
                'quantity'       => (int) $item->quantity,
                'line_total_aed' => (int) $item->line_total_aed,
                'unit_price_sdg' => $unitPriceSdg,
                'line_total_sdg' => $lineTotalSdg,
            ];
        }, $items);

        // Fetch events timeline
        $events = $this->orderEvents
            ->where('order_id', $order->id)
            ->orderBy('id', 'ASC')
            ->findAll();

        $formattedEvents = array_map(function (OrderEvent $e) {
            $metaData = null;
            if (!empty($e->meta)) {
                $metaData = is_string($e->meta) ? json_decode($e->meta, true) : $e->meta;
            }
            return [
                'id'          => (int) $e->id,
                'from_status' => $e->from_status,
                'to_status'   => $e->to_status,
                'status_text' => OrderStateMachine::getStatusLabel($e->to_status),
                'type'        => (string) ($e->event_type ?: 'status_changed'),
                'event_type'  => (string) ($e->event_type ?: 'status_changed'),
                'meta'        => $metaData,
                'note'        => (string) $e->note,
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
                'id'               => (int) $p->id,
                'status'           => (string) $p->status,
                'sender_name'      => $p->sender_name,
                'reference_number' => $p->reference_number,
                'review_note'      => $p->review_note,
                'image_url'        => "/api/v1/orders/{$order->order_number}/payment-proof/{$p->id}/image",
                'created_at'       => $p->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($p->created_at)) : null,
                'uploaded_at'      => $p->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($p->created_at)) : null,
                'reviewed_at'      => $p->reviewed_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($p->reviewed_at)) : null,
            ];
        }, $proofs);

        // Compute unified payment state block
        $paymentBlock = PaymentStateService::computePaymentBlock($order, $formattedProofs);

        // Resolve customer details
        $userObj = $order->user_id ? $this->users->find((int) $order->user_id) : null;
        $customerName = trim((string) ($userObj?->name ?? ''));
        if ($customerName === '') {
            $customerName = trim((string) ($order->delivery_name ?? ''));
        }
        if ($customerName === '') {
            $customerName = trim((string) ($userObj?->phone ?? $order->delivery_phone ?? ''));
        }
        if ($customerName === '') {
            $email = (string) ($userObj?->email ?? '');
            if ($email !== '' && str_contains($email, '@')) {
                $customerName = explode('@', $email)[0];
            }
        }
        if ($customerName === '') {
            $customerName = 'مستخدم #' . ($userObj?->id ?? $order->user_id);
        }
        $customerPhone = trim((string) ($userObj?->phone ?? $order->delivery_phone ?? ''));

        // Calculate lock seconds remaining
        $now = time();
        $expiresAt = strtotime($order->lock_expires_at);
        $secondsRemaining = ($order->status === Wex::ORDER_STATUS_PENDING_PAYMENT)
            ? max(0, $expiresAt - $now)
            : 0;

        // Payment accounts for customer view
        $activeAccounts = $this->paymentAccountService->getActiveAccounts();
        $formattedAccounts = array_map(static function ($acc) {
            return [
                'id'             => (int) $acc->id,
                'bank_name'      => (string) $acc->bank_name,
                'account_name'   => (string) $acc->account_name,
                'account_number' => (string) $acc->account_number,
                'branch'         => $acc->branch ? (string) $acc->branch : null,
                'notes'          => $acc->notes ? (string) $acc->notes : null,
            ];
        }, $activeAccounts);

        $firstAccount = !empty($activeAccounts) ? $activeAccounts[0] : null;

        // Public bank details for payment (backward compatibility)
        $bankDetails = [
            'bank_name'           => $firstAccount ? $firstAccount->bank_name : $this->settingService->get('bank_name', ''),
            'bank_account_name'   => $firstAccount ? $firstAccount->account_name : $this->settingService->get('bank_account_name', ''),
            'bank_account_number' => $firstAccount ? $firstAccount->account_number : $this->settingService->get('bank_account_number', ''),
            'amount_sdg'          => (int) $order->total_sdg,
            'order_number'        => $order->order_number,
        ];

        return [
            'order_number'                  => $order->order_number,
            'user_id'                       => (int) $order->user_id,
            'customer_name'                 => $customerName,
            'customer_phone'                => $customerPhone,
            'customer'                      => [
                'name'  => $customerName,
                'phone' => $customerPhone,
                'email' => $userObj?->email ?? null,
            ],
            'user'                          => $userObj ? [
                'id'    => (int) $userObj->id,
                'name'  => $customerName,
                'email' => $userObj->email,
                'phone' => $customerPhone,
            ] : null,
            'status'                        => $order->status,
            'status_label'                  => OrderStateMachine::getStatusLabel($order->status),
            'delivery_type'                 => $order->delivery_type,
            'delivery_phone'                => $order->delivery_phone,
            'delivery_address'              => $order->delivery_address,
            'shipping_zone_name'            => $order->shipping_zone_id
                ? ($this->shippingZones->find((int) $order->shipping_zone_id)?->state_name_ar)
                : null,
            'customer_note'                 => $order->customer_note,
            'payment'                       => $paymentBlock,
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
            'payment_accounts'              => $formattedAccounts,
            'payment_general_notes'         => (string) $this->settingService->get('payment_general_notes', ''),
            'items'                         => $formattedItems,
            'timeline'                      => $formattedEvents,
            'proofs'                        => $formattedProofs,
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

        $orderIds = array_map(static fn($o) => (int) $o->id, $orders);
        $proofsByOrderId = [];
        if (!empty($orderIds)) {
            $proofRows = $this->paymentProofs->whereIn('order_id', $orderIds)->orderBy('id', 'DESC')->findAll();
            foreach ($proofRows as $p) {
                $proofsByOrderId[$p->order_id][] = $p;
            }
        }

        $items = [];
        foreach ($orders as $order) {
            $this->refreshExpiry($order);

            $orderProofs = $proofsByOrderId[$order->id] ?? [];
            $paymentBlock = PaymentStateService::computePaymentBlock($order, $orderProofs);

            $expiresAt = strtotime($order->lock_expires_at);
            $secondsRemaining = ($order->status === Wex::ORDER_STATUS_PENDING_PAYMENT)
                ? max(0, $expiresAt - time())
                : 0;

            $items[] = [
                'order_number'      => $order->order_number,
                'status'            => $order->status,
                'status_label'      => OrderStateMachine::getStatusLabel($order->status),
                'delivery_type'     => $order->delivery_type,
                'payment'           => $paymentBlock,
                'total_sdg'         => (int) $order->total_sdg,
                'total_amount_sdg'  => (int) $order->total_sdg,
                'lock_expires_at'   => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
                'expires_at'        => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
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

        $userIds = array_filter(array_unique(array_map(static fn($o) => (int) $o->user_id, $orders)));
        $usersById = [];
        if (!empty($userIds)) {
            $userRows = $this->users->whereIn('id', $userIds)->findAll();
            foreach ($userRows as $u) {
                $usersById[$u->id] = $u;
            }
        }

        $orderIds = array_map(static fn($o) => (int) $o->id, $orders);
        $proofsByOrderId = [];
        if (!empty($orderIds)) {
            $proofRows = $this->paymentProofs->whereIn('order_id', $orderIds)->orderBy('id', 'DESC')->findAll();
            foreach ($proofRows as $p) {
                $proofsByOrderId[$p->order_id][] = [
                    'id'               => (int) $p->id,
                    'status'           => (string) $p->status,
                    'sender_name'      => $p->sender_name,
                    'reference_number' => $p->reference_number,
                    'review_note'      => $p->review_note,
                    'created_at'       => $p->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($p->created_at)) : null,
                    'uploaded_at'      => $p->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($p->created_at)) : null,
                    'reviewed_at'      => $p->reviewed_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($p->reviewed_at)) : null,
                ];
            }
        }

        $items = [];
        foreach ($orders as $order) {
            $this->refreshExpiry($order);

            $expiresAt = strtotime($order->lock_expires_at);
            $secondsRemaining = ($order->status === Wex::ORDER_STATUS_PENDING_PAYMENT)
                ? max(0, $expiresAt - time())
                : 0;

            $userObj = $usersById[$order->user_id] ?? null;
            $customerName = trim((string) ($userObj?->name ?? ''));
            if ($customerName === '') {
                $customerName = trim((string) ($order->delivery_name ?? ''));
            }
            if ($customerName === '') {
                $customerName = trim((string) ($userObj?->phone ?? $order->delivery_phone ?? ''));
            }
            if ($customerName === '') {
                $email = (string) ($userObj?->email ?? '');
                if ($email !== '' && str_contains($email, '@')) {
                    $customerName = explode('@', $email)[0];
                }
            }
            if ($customerName === '') {
                $customerName = 'مستخدم #' . ($userObj?->id ?? $order->user_id);
            }
            $customerPhone = trim((string) ($userObj?->phone ?? $order->delivery_phone ?? ''));

            $orderProofs = $proofsByOrderId[$order->id] ?? [];
            foreach ($orderProofs as &$pr) {
                $pr['image_url'] = "/api/v1/orders/{$order->order_number}/payment-proof/{$pr['id']}/image";
                $pr['uploaded_at'] = $pr['created_at'] ?? null;
            }
            unset($pr);

            $paymentBlock = PaymentStateService::computePaymentBlock($order, $orderProofs);

            $items[] = [
                'id'                => (int) $order->id,
                'order_number'      => $order->order_number,
                'user_id'           => (int) $order->user_id,
                'customer_name'     => $customerName,
                'customer_phone'    => $customerPhone,
                'customer'          => [
                    'name'  => $customerName,
                    'phone' => $customerPhone,
                    'email' => $userObj?->email ?? null,
                ],
                'amount'            => (int) $order->total_sdg,
                'expected_amount'   => (int) $order->total_sdg,
                'payment_proofs'    => $orderProofs,
                'proofs'            => $orderProofs,
                'payment'           => $paymentBlock,
                'status'            => $order->status,
                'status_label'      => OrderStateMachine::getStatusLabel($order->status),
                'delivery_type'     => $order->delivery_type,
                'delivery_phone'    => $order->delivery_phone,
                'rate_sdg_snapshot' => (int) $order->rate_sdg_snapshot,
                'subtotal_aed'      => (int) $order->subtotal_aed,
                'total_sdg'         => (int) $order->total_sdg,
                'total_amount_sdg'  => (int) $order->total_sdg,
                'lock_expires_at'   => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
                'expires_at'        => gmdate('Y-m-d\TH:i:s\Z', $expiresAt),
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

        // Lazy expiry first so an admin never acts on a stale pending order.
        $this->refreshExpiry($order);

        $fromStatus = $order->status;
        OrderStateMachine::assertCanTransition($fromStatus, $newStatus);
        OrderStateMachine::assertMatchesDeliveryType((string) $order->delivery_type, $newStatus);

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

            // Compare-and-set on the status we validated: concurrent transitions (two admins,
            // admin vs customer cancel, expiry worker) must not both succeed, or stock would be
            // restored twice.
            $this->orders->builder()
                ->where('id', $order->id)
                ->where('status', $fromStatus)
                ->update($updateData);

            if ($db->affectedRows() !== 1) {
                throw new ConflictException('تغيرت حالة الطلب أثناء المعالجة، يرجى تحديث الصفحة والمحاولة مجدداً.');
            }

            // Restore stock if needed
            if (OrderStateMachine::shouldRestoreStock($fromStatus, $newStatus)) {
                $this->restoreStockForOrder((int) $order->id);
            }

            // Timeline event
            $eventNote = $note ?? ('تم تغيير حالة الطلب إلى: ' . OrderStateMachine::getStatusLabel($newStatus));
            $eventMeta = [
                'from_status' => $fromStatus,
                'to_status'   => $newStatus,
            ];
            $this->orderEvents->insert(new OrderEvent([
                'order_id'      => $order->id,
                'from_status'   => $fromStatus,
                'to_status'     => $newStatus,
                'actor_user_id' => $actorUserId,
                'event_type'    => 'status_changed',
                'meta'          => json_encode($eventMeta),
                'note'          => $eventNote,
                'created_at'    => $nowStr,
            ]));

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $this->audit($actorUserId, 'order_status_changed', (int) $order->id, [
            'order_number' => $orderNumber,
            'from'         => $fromStatus,
            'to'           => $newStatus,
            'note'         => $note,
        ]);

        // Send status change notification
        try {
            $pushOpts = [
                'order_number'          => $orderNumber,
                'url'                   => "/orders/{$orderNumber}",
                'actor_user_id'         => $actorUserId,
                'expected_order_status' => $newStatus,
            ];

            if ($newStatus === Wex::ORDER_STATUS_CONFIRMED) {
                $tpl = \Config\PushTemplates::render('order_confirmed', ['order_number' => $orderNumber]);
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_confirmed',
                    title: $tpl['title'],
                    body: $tpl['body'],
                    orderId: (int) $order->id,
                    emailTemplate: 'payment_approved',
                    emailPayload: [
                        'order_number'  => $orderNumber,
                        'total_sdg'     => (int) $order->total_sdg,
                        'delivery_type' => $order->delivery_type,
                    ],
                    pushOptions: $pushOpts
                );
            } elseif ($newStatus === Wex::ORDER_STATUS_READY_FOR_PICKUP) {
                $tpl = \Config\PushTemplates::render('order_ready_pickup', ['order_number' => $orderNumber]);
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_ready_pickup',
                    title: $tpl['title'],
                    body: $tpl['body'],
                    orderId: (int) $order->id,
                    emailTemplate: 'ready_for_pickup',
                    emailPayload: [
                        'order_number' => $orderNumber,
                        'shop_phone'   => $this->settingService->get('shop_phone'),
                    ],
                    pushOptions: $pushOpts
                );
            } elseif ($newStatus === Wex::ORDER_STATUS_OUT_FOR_DELIVERY) {
                $tpl = \Config\PushTemplates::render('order_out_delivery', ['order_number' => $orderNumber]);
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_out_delivery',
                    title: $tpl['title'],
                    body: $tpl['body'],
                    orderId: (int) $order->id,
                    emailTemplate: 'out_for_delivery',
                    emailPayload: [
                        'order_number'     => $orderNumber,
                        'courier_name'     => $updateData['courier_name'] ?? null,
                        'courier_phone'    => $updateData['courier_phone'] ?? null,
                        'delivery_address' => $order->delivery_address,
                    ],
                    pushOptions: $pushOpts
                );
            } elseif ($newStatus === Wex::ORDER_STATUS_COMPLETED) {
                $tpl = \Config\PushTemplates::render('order_completed', ['order_number' => $orderNumber]);
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_completed',
                    title: $tpl['title'],
                    body: $tpl['body'],
                    orderId: (int) $order->id,
                    emailTemplate: 'order_completed',
                    emailPayload: [
                        'order_number' => $orderNumber,
                        'total_sdg'    => (int) $order->total_sdg,
                    ],
                    pushOptions: $pushOpts
                );
            } elseif ($newStatus === Wex::ORDER_STATUS_CANCELLED) {
                $tpl = \Config\PushTemplates::render('order_cancelled', ['order_number' => $orderNumber]);
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'order_cancelled',
                    title: $tpl['title'],
                    body: $tpl['body'],
                    orderId: (int) $order->id,
                    pushOptions: $pushOpts
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

        $this->refreshExpiry($order);

        if (!in_array($order->status, [Wex::ORDER_STATUS_PENDING_PAYMENT, Wex::ORDER_STATUS_PAYMENT_REVIEW], true)) {
            throw new ConflictException('يمكن تمديد حجز السعر فقط للطلبات التي في مرحلة الدفع أو المراجعة.');
        }

        $extraHours = max(1, min(72, $extraHours));
        $currentExpiry = strtotime($order->lock_expires_at);
        $baseTime = max(time(), $currentExpiry);
        $newExpiry = gmdate('Y-m-d H:i:s', $baseTime + ($extraHours * 3600));

        $this->orders->builder()
            ->where('id', $order->id)
            ->where('status', $order->status)
            ->update([
                'lock_expires_at' => $newExpiry,
                'updated_at'      => gmdate('Y-m-d H:i:s'),
            ]);
        if ($this->orders->db->affectedRows() !== 1) {
            throw new ConflictException('تغيرت حالة الطلب أثناء المعالجة، يرجى تحديث الصفحة والمحاولة مجدداً.');
        }

        $this->audit($actorUserId, 'order_lock_extended', (int) $order->id, [
            'order_number' => $orderNumber,
            'from'         => $order->lock_expires_at,
            'to'           => $newExpiry,
            'extra_hours'  => $extraHours,
        ]);

        $eventMeta = [
            'extra_hours'     => (int) $extraHours,
            'lock_expires_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime($newExpiry)),
        ];

        $this->orderEvents->insert(new OrderEvent([
            'order_id'      => $order->id,
            'from_status'   => $order->status,
            'to_status'     => $order->status,
            'actor_user_id' => $actorUserId,
            'event_type'    => 'lock_extended',
            'meta'          => json_encode($eventMeta),
            'note'          => "قام المشرف بتمديد فترة حجز السعر لمدة {$extraHours} ساعات إضافية",
            'created_at'    => gmdate('Y-m-d H:i:s'),
        ]));

        return $this->getOrder($orderNumber, null, true);
    }

    /**
     * Admin/Staff recovers an orphan order stuck in payment_review with zero proofs.
     *
     * @throws NotFoundException|ConflictException
     */
    public function resetOrphanOrder(string $orderNumber, int $actorUserId): array
    {
        $order = $this->orders->where('order_number', $orderNumber)->first();
        if (!$order) {
            throw new NotFoundException('الطلب غير موجود.');
        }

        if ($order->status !== Wex::ORDER_STATUS_PAYMENT_REVIEW) {
            throw new ConflictException('الطلب ليس في حالة مراجعة الدفع.');
        }

        $pendingCount = $this->paymentProofs->where('order_id', $order->id)->where('status', Wex::PROOF_STATUS_PENDING)->countAllResults();
        if ($pendingCount > 0) {
            throw new ConflictException('الطلب يحتوي على إشعار دفع قيد المراجعة ولا يعتبر يتيماً.');
        }

        $now = time();
        $expiresAt = strtotime($order->lock_expires_at);
        $nowStr = gmdate('Y-m-d H:i:s');
        $db = Database::connect();
        $db->transBegin();

        try {
            if ($expiresAt > $now) {
                $newStatus = Wex::ORDER_STATUS_PENDING_PAYMENT;
                $this->casStatus($order, Wex::ORDER_STATUS_PAYMENT_REVIEW, $newStatus, $nowStr);

                $this->orderEvents->insert(new OrderEvent([
                    'order_id'      => $order->id,
                    'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                    'to_status'     => $newStatus,
                    'actor_user_id' => $actorUserId,
                    'event_type'    => 'orphan_order_reset',
                    'meta'          => json_encode(['reason' => 'zero_proofs_in_review']),
                    'note'          => 'إعادة الطلب إلى بانتظار التحويل لعدم وجود إشعار مرفوع',
                    'created_at'    => $nowStr,
                ]));
            } else {
                $newStatus = Wex::ORDER_STATUS_EXPIRED;
                $this->casStatus($order, Wex::ORDER_STATUS_PAYMENT_REVIEW, $newStatus, $nowStr);

                $this->restoreStockForOrder((int) $order->id);

                $this->orderEvents->insert(new OrderEvent([
                    'order_id'      => $order->id,
                    'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                    'to_status'     => $newStatus,
                    'actor_user_id' => $actorUserId,
                    'event_type'    => 'orphan_order_expired',
                    'meta'          => json_encode(['reason' => 'lock_expired_without_proof']),
                    'note'          => 'إلغاء الطلب لانتهاء مهلة حجز السعر بدون إشعار دفع',
                    'created_at'    => $nowStr,
                ]));
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        $this->audit($actorUserId, 'order_orphan_reset', (int) $order->id, [
            'order_number' => $orderNumber,
            'from'         => Wex::ORDER_STATUS_PAYMENT_REVIEW,
            'to'           => $newStatus,
        ]);

        return $this->getOrder($orderNumber, null, true);
    }

    /**
     * Compare-and-set an order status. Throws ConflictException when another request
     * already moved the order away from $expectedStatus.
     *
     * @throws ConflictException
     */
    public function casStatus(Order $order, string $expectedStatus, string $newStatus, string $nowStr): void
    {
        $this->orders->builder()
            ->where('id', $order->id)
            ->where('status', $expectedStatus)
            ->update(['status' => $newStatus, 'updated_at' => $nowStr]);

        if ($this->orders->db->affectedRows() !== 1) {
            throw new ConflictException('تغيرت حالة الطلب أثناء المعالجة، يرجى تحديث الصفحة والمحاولة مجدداً.');
        }
    }

    /**
     * Writes an admin audit entry; never lets an audit failure break the business action.
     */
    protected function audit(?int $actorUserId, string $action, int $entityId, array $details): void
    {
        try {
            (new AuditLogService())->log($actorUserId, $action, 'order', $entityId, $details);
        } catch (\Throwable $e) {
            log_message('error', 'Audit log write failed for {action}: {msg}', ['action' => $action, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * Restores reserved stock for an order (public for PaymentProofService).
     */
    public function restoreStock(int $orderId): void
    {
        $this->restoreStockForOrder($orderId);
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
     * Vowel-free, unambiguous alphabet (no 0/O/1/I/L): random suffixes can never spell
     * words, and never "AED" (hex suffixes did, leaking the internal currency to customers).
     */
    public const ORDER_SUFFIX_ALPHABET = '23456789BCFGHJKMNPQRSTVWXYZ';

    /**
     * Generate unique readable order number: WEX-YYMMDD-XXXXX
     */
    protected function generateOrderNumber(): string
    {
        $prefix = 'WEX-' . gmdate('ymd') . '-';
        for ($i = 0; $i < 5; $i++) {
            $candidate = $prefix . self::randomSuffix(5);
            if (!$this->orders->where('order_number', $candidate)->first()) {
                return $candidate;
            }
        }
        return $prefix . self::randomSuffix(8);
    }

    public static function randomSuffix(int $length): string
    {
        $alphabet = self::ORDER_SUFFIX_ALPHABET;
        $max      = strlen($alphabet) - 1;
        $out      = '';
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}
