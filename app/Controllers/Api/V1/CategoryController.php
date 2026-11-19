<?php

namespace App\Controllers\Api\V1;

use App\Models\CategoryModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CategoryController
 *
 * Public endpoints for product categories.
 */
class CategoryController extends BaseApiController
{
    protected CategoryModel $categoryModel;

    public function __construct(?CategoryModel $categoryModel = null)
    {
        $this->categoryModel = $categoryModel ?? new CategoryModel();
    }

    /**
     * GET /api/v1/categories
     */
    public function index(): ResponseInterface
    {
        $categories = $this->categoryModel
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        $data = array_map(static fn($c) => [
            'id'         => (int) $c->id,
            'name_ar'    => $c->name_ar,
            'name_en'    => $c->name_en,
            'slug'       => $c->slug,
            'sort_order' => (int) $c->sort_order,
        ], $categories);

        return $this->respondSuccess($data, 'فئات المنتجات');
    }
}
