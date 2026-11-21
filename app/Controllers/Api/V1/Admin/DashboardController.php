<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Models\OrderModel;
use App\Models\PaymentProofModel;
use App\Models\ProductModel;
use App\Services\PricingService;
use App\Services\SettingService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Wex;

/**
 * DashboardController
 *
 * Operational dashboard metrics for store owners and staff.
 */
class DashboardController extends BaseApiController
{
    protected OrderModel $orders;
    protected PaymentProofModel $proofs;
    protected ProductModel $products;
    protected PricingService $pricingService;
    protected SettingService $settingService;

    public function __construct(
        ?OrderModel $orders = null,
        ?PaymentProofModel $proofs = null,
        ?ProductModel $products = null,
        ?PricingService $pricingService = null,
        ?SettingService $settingService = null
    ) {
        $this->orders         = $orders ?? new OrderModel();
        $this->proofs         = $proofs ?? new PaymentProofModel();
        $this->products       = $products ?? new ProductModel();
        $this->pricingService = $pricingService ?? new PricingService();
        $this->settingService = $settingService ?? new SettingService();
    }

    /**
     * GET /api/v1/admin/dashboard
     *
     * Summarizes orders by status, pending receipt reviews, today's revenue,
     * low-stock products, and active exchange rate.
     */
    public function index(): ResponseInterface
    {
        // 1. Orders count by status (excluding debug test orders)
        $statusCounts = [];
        foreach (Wex::ORDER_STATUSES as $status) {
            $statusCounts[$status] = $this->orders
                ->where('status', $status)
                ->notLike('order_number', 'WEX-DBG-')
                ->countAllResults();
        }

        // 2. Pending payment reviews requiring staff attention
        $pendingReviewsCount = $this->proofs
            ->where('status', Wex::PROOF_STATUS_PENDING)
            ->countAllResults();

        // 3. Today's orders and revenue (UTC) excluding cancelled, expired and debug orders
        $todayStart = gmdate('Y-m-d 00:00:00');
        $todayEnd   = gmdate('Y-m-d 23:59:59');

        $inactiveStatuses = [
            Wex::ORDER_STATUS_CANCELLED,
            Wex::ORDER_STATUS_EXPIRED,
        ];

        $todayOrdersCount = $this->orders
            ->where('created_at >=', $todayStart)
            ->where('created_at <=', $todayEnd)
            ->whereNotIn('status', $inactiveStatuses)
            ->notLike('order_number', 'WEX-DBG-')
            ->countAllResults();

        // Sum revenue of active / confirmed / completed orders today
        $revenueStatuses = [
            Wex::ORDER_STATUS_CONFIRMED,
            Wex::ORDER_STATUS_READY_FOR_PICKUP,
            Wex::ORDER_STATUS_OUT_FOR_DELIVERY,
            Wex::ORDER_STATUS_COMPLETED,
        ];

        $todayRevenueRow = $this->orders
            ->selectSum('total_sdg', 'revenue')
            ->where('created_at >=', $todayStart)
            ->where('created_at <=', $todayEnd)
            ->whereIn('status', $revenueStatuses)
            ->notLike('order_number', 'WEX-DBG-')
            ->first();

        $todayRevenueSdg = $todayRevenueRow ? (int) ($todayRevenueRow->revenue ?? 0) : 0;

        // 4. Low stock inventory alerts
        $lowStockThreshold = (int) $this->settingService->get('low_stock_threshold', 2);
        $lowStockProducts  = $this->products
            ->select('id, name, type, stock_quantity')
            ->where('is_active', 1)
            ->where('stock_quantity <=', $lowStockThreshold)
            ->orderBy('stock_quantity', 'ASC')
            ->findAll(20);

        $formattedLowStock = array_map(function ($p) {
            return [
                'id'             => $p->id,
                'name'           => $p->name,
                'type'           => $p->type,
                'stock_quantity' => (int) $p->stock_quantity,
            ];
        }, $lowStockProducts);

        // 5. Pricing context
        $rateSdg       = $this->pricingService->getCurrentRateSdg();
        $rateUpdatedAt = $this->pricingService->getRateUpdatedAt();
        $lockHours     = (int) $this->settingService->get('lock_hours', 4);

        $data = [
            'orders_by_status'        => $statusCounts,
            'pending_reviews_count'   => $pendingReviewsCount,
            'today_orders'            => [
                'count'       => $todayOrdersCount,
                'revenue_sdg' => $todayRevenueSdg,
            ],
            'low_stock_threshold'     => $lowStockThreshold,
            'low_stock_products'      => $formattedLowStock,
            'exchange_rate'           => [
                'rate_sdg'   => $rateSdg,
                'updated_at' => $rateUpdatedAt,
            ],
            'price_lock_hours'        => $lockHours,
        ];

        return $this->respondSuccess($data, 'مؤشرات لوحة التحكم');
    }
}
