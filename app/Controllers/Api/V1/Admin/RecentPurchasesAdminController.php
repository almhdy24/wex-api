<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\RecentPurchasesService;
use CodeIgniter\HTTP\ResponseInterface;

class RecentPurchasesAdminController extends BaseApiController
{
    protected RecentPurchasesService $service;

    public function __construct(?RecentPurchasesService $service = null)
    {
        $this->service = $service ?? new RecentPurchasesService();
    }

    /**
     * GET /api/v1/admin/recent-purchases
     */
    public function index(): ResponseInterface
    {
        $orders = $this->service->getEligibleOrdersForAdmin();
        return $this->respondSuccess($orders);
    }

    /**
     * PATCH /api/v1/admin/recent-purchases/(:num)/toggle-hide
     */
    public function toggleHide(int $id): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $hidden = $this->service->toggleHide(
            orderId: $id,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(['ticker_hidden' => $hidden], 'تم تحديث حالة ظهور الطلب في الشريط.');
    }

    /**
     * PATCH /api/v1/admin/recent-purchases/settings
     */
    public function settings(): ResponseInterface
    {
        $user = $this->currentUser() ?? $this->request->auth_user;
        $body = $this->request->getJSON(true) ?? $this->request->getRawInput() ?? [];

        $enabled = isset($body['ticker_enabled']) ? (bool) $body['ticker_enabled'] : false;

        $this->service->updateSettings(
            enabled: $enabled,
            userId: (int) ($user?->id ?? 0),
            ip: $this->request->getIPAddress()
        );

        return $this->respondSuccess(['ticker_enabled' => $enabled], 'تم تحديث إعدادات شريط المشتريات.');
    }
}
