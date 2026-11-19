<?php

namespace App\Controllers\Api\V1;

use App\Models\BrandModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * BrandController
 *
 * Public endpoints for product brands.
 */
class BrandController extends BaseApiController
{
    protected BrandModel $brandModel;

    public function __construct(?BrandModel $brandModel = null)
    {
        $this->brandModel = $brandModel ?? new BrandModel();
    }

    /**
     * GET /api/v1/brands
     */
    public function index(): ResponseInterface
    {
        $brands = $this->brandModel
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        $data = array_map(static fn($b) => [
            'id'   => (int) $b->id,
            'name' => $b->name,
            'slug' => $b->slug,
        ], $brands);

        return $this->respondSuccess($data, 'الماركات المتوفرة');
    }
}
