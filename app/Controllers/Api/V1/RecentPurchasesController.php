<?php

namespace App\Controllers\Api\V1;

use App\Services\RecentPurchasesService;
use CodeIgniter\HTTP\ResponseInterface;

class RecentPurchasesController extends BaseApiController
{
    protected RecentPurchasesService $service;

    public function __construct(?RecentPurchasesService $service = null)
    {
        $this->service = $service ?? new RecentPurchasesService();
    }

    /**
     * GET /api/v1/recent-purchases
     *
     * Returns recent anonymized purchases for the live storefront ticker.
     */
    public function index(): ResponseInterface
    {
        $purchases = $this->service->getRecentPurchases();
        return $this->respondSuccess($purchases);
    }
}
