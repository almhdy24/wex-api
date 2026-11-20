<?php

namespace App\Services;

use App\Exceptions\NotFoundException;
use App\Models\OrderModel;
use Config\Database;
use Config\Wex;

class RecentPurchasesService
{
    protected OrderModel $orders;
    protected SettingService $settings;
    protected AuditLogService $auditLogs;

    public function __construct(
        ?OrderModel $orders = null,
        ?SettingService $settings = null,
        ?AuditLogService $auditLogs = null
    ) {
        $this->orders    = $orders ?? new OrderModel();
        $this->settings  = $settings ?? new SettingService();
        $this->auditLogs = $auditLogs ?? new AuditLogService();
    }

    /**
     * Public recent purchases ticker. Returns max 8 items from completed orders in the last 30 days.
     * STRICTLY anonymized: returns ONLY product_name, thumbnail, purchased_at.
     * NO customer name, phone, address, or order number.
     *
     * @return array<int, array{product_name: string, thumbnail: ?string, purchased_at: string}>
     */
    public function getRecentPurchases(): array
    {
        $tickerEnabled = $this->settings->get('ticker_enabled', '0');
        if ($tickerEnabled !== '1' && $tickerEnabled !== 1 && $tickerEnabled !== true) {
            return [];
        }

        $db = Database::connect();
        $thirtyDaysAgo = gmdate('Y-m-d H:i:s', strtotime('-30 days'));

        // Query completed orders with order_items and product primary image
        $builder = $db->table('orders')
            ->select('order_items.product_name_snapshot as product_name, orders.updated_at as purchased_at, COALESCE(product_images.thumb_path, product_images.path) as thumbnail')
            ->join('order_items', 'order_items.order_id = orders.id', 'inner')
            ->join('product_images', 'product_images.product_id = order_items.product_id AND product_images.is_primary = 1', 'left')
            ->where('orders.status', Wex::ORDER_STATUS_COMPLETED)
            ->where('orders.ticker_hidden', 0)
            ->where('orders.updated_at >=', $thirtyDaysAgo)
            ->orderBy('orders.updated_at', 'DESC')
            ->limit(8);

        $rows = $builder->get()->getResultArray();

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'product_name' => (string) $row['product_name'],
                'thumbnail'    => !empty($row['thumbnail']) ? (string) $row['thumbnail'] : null,
                'purchased_at' => gmdate('Y-m-d\TH:i:s\Z', strtotime($row['purchased_at'])),
            ];
        }

        return $result;
    }

    /**
     * Admin view of recent eligible purchases (includes order number and hide status).
     */
    public function getEligibleOrdersForAdmin(): array
    {
        $db = Database::connect();
        $thirtyDaysAgo = gmdate('Y-m-d H:i:s', strtotime('-30 days'));

        $builder = $db->table('orders')
            ->select('orders.id as order_id, orders.order_number, orders.ticker_hidden, orders.updated_at as completed_at, order_items.product_name_snapshot as product_name, COALESCE(product_images.thumb_path, product_images.path) as thumbnail')
            ->join('order_items', 'order_items.order_id = orders.id', 'inner')
            ->join('product_images', 'product_images.product_id = order_items.product_id AND product_images.is_primary = 1', 'left')
            ->where('orders.status', Wex::ORDER_STATUS_COMPLETED)
            ->where('orders.updated_at >=', $thirtyDaysAgo)
            ->orderBy('orders.updated_at', 'DESC')
            ->limit(50);

        return $builder->get()->getResultArray();
    }

    /**
     * Toggle hide status for an order.
     */
    public function toggleHide(int $orderId, int $userId, ?string $ip = null): bool
    {
        $order = $this->orders->find($orderId);
        if (!$order) {
            throw new NotFoundException('الطلب المطلوب غير موجود.');
        }

        $newHidden = $order->ticker_hidden ? 0 : 1;
        $this->orders->update($orderId, ['ticker_hidden' => $newHidden]);

        $this->auditLogs->log(
            userId: $userId,
            action: 'recent_purchase_visibility_toggled',
            entity: 'order',
            entityId: $orderId,
            details: [
                'order_number'  => $order->order_number,
                'ticker_hidden' => $newHidden,
            ],
            ip: $ip
        );

        return (bool) $newHidden;
    }

    /**
     * Update ticker_enabled setting.
     */
    public function updateSettings(bool $enabled, int $userId, ?string $ip = null): void
    {
        $val = $enabled ? '1' : '0';
        $this->settings->set('ticker_enabled', $val);

        $this->auditLogs->log(
            userId: $userId,
            action: 'ticker_settings_updated',
            entity: 'setting',
            entityId: null,
            details: ['ticker_enabled' => $val],
            ip: $ip
        );
    }
}
