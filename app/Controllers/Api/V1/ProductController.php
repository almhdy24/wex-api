<?php

namespace App\Controllers\Api\V1;

use App\Services\ProductService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ProductController
 *
 * Public endpoints for product browsing, filtering, search, and detail views.
 */
class ProductController extends BaseApiController
{
    protected ProductService $productService;

    public function __construct(?ProductService $productService = null)
    {
        $this->productService = $productService ?? new ProductService();
    }

    /**
     * GET /api/v1/products
     *
     * Whitelists query filters and sorting params.
     */
    public function index(): ResponseInterface
    {
        [$page, $perPage] = $this->getPaginationParams();

        $allowedFilterKeys = [
            'type', 'category', 'brand', 'condition',
            'min_price_aed', 'max_price_aed',
            'featured', 'in_stock', 'q', 'sort',
        ];

        $filters = [];
        foreach ($allowedFilterKeys as $key) {
            $val = $this->request->getGet($key);
            if ($val !== null && $val !== '') {
                $filters[$key] = $val;
            }
        }

        $result = $this->productService->listPublicProducts($filters, $page, $perPage);

        return $this->respondPaginated(
            items: $result['items'],
            page: $page,
            perPage: $perPage,
            total: $result['total'],
            message: 'قائمة الأجهزة والمنتجات'
        );
    }

    /**
     * GET /api/v1/products/{slug}
     */
    public function show(string $slug): ResponseInterface
    {
        $data = $this->productService->getPublicProductDetail($slug);

        return $this->respondSuccess($data, 'تفاصيل الجهاز');
    }
}
