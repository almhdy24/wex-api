<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Models\CategoryModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * CategoryAdminController
 *
 * Administrator operations for product categories.
 */
class CategoryAdminController extends BaseApiController
{
    protected CategoryModel $categoryModel;

    public function __construct(?CategoryModel $categoryModel = null)
    {
        $this->categoryModel = $categoryModel ?? new CategoryModel();
    }

    /**
     * GET /api/v1/admin/categories
     */
    public function index(): ResponseInterface
    {
        $categories = $this->categoryModel->orderBy('sort_order', 'ASC')->findAll();

        return $this->respondSuccess($categories, 'قائمة الفئات');
    }

    /**
     * POST /api/v1/admin/categories
     */
    public function create(): ResponseInterface
    {
        $body = $this->getJsonBody();

        if (empty($body['name_ar'])) {
            throw new ValidationFailedException(['name_ar' => 'اسم الفئة بالعربية مطلوب']);
        }

        $slug = !empty($body['slug'])
            ? preg_replace('/[^\p{L}\p{Nd}]+/u', '-', mb_strtolower(trim($body['slug'])))
            : preg_replace('/[^\p{L}\p{Nd}]+/u', '-', mb_strtolower(trim($body['name_ar'])));

        $slug = $this->assertSlugAvailable(trim((string) $slug, '-'));

        $now = gmdate('Y-m-d H:i:s');

        $id = $this->categoryModel->insert([
            'name_ar'    => trim($body['name_ar']),
            'name_en'    => $body['name_en'] ?? null,
            'slug'       => $slug,
            'sort_order' => isset($body['sort_order']) ? (int) $body['sort_order'] : 0,
            'is_active'  => isset($body['is_active']) ? (int) \App\Libraries\Input::bool($body['is_active']) : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $category = $this->categoryModel->find($id);

        \App\Libraries\AdminAudit::log($this->request, $this->currentUser(), 'category_created', 'category', (int) $id, ['after' => $category->toArray()]);


        return $this->respondCreated($category, lang('Api.category_created'));
    }

    /**
     * GET /api/v1/admin/categories/{id}
     */
    public function show(int $id): ResponseInterface
    {
        $category = $this->categoryModel->find($id);
        if (!$category) {
            throw new NotFoundException('الفئة غير موجودة');
        }

        return $this->respondSuccess($category, 'بيانات الفئة');
    }

    /**
     * PATCH /api/v1/admin/categories/{id}
     */
    public function update(int $id): ResponseInterface
    {
        $category = $this->categoryModel->find($id);
        if (!$category) {
            throw new NotFoundException('الفئة غير موجودة');
        }

        $body = $this->getJsonBody();
        $updateFields = [];

        foreach (['name_ar', 'name_en', 'sort_order', 'is_active'] as $key) {
            if (array_key_exists($key, $body)) {
                $updateFields[$key] = match ($key) {
                    'is_active'  => (int) \App\Libraries\Input::bool($body[$key]),
                    'sort_order' => (int) $body[$key],
                    default      => $body[$key],
                };
            }
        }

        if (isset($body['slug'])) {
            $updateFields['slug'] = $this->assertSlugAvailable(trim(preg_replace('/[^\p{L}\p{Nd}]+/u', '-', mb_strtolower((string) $body['slug'])), '-'), $id);
        }

        if (!empty($updateFields)) {
            $updateFields['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->categoryModel->update($id, $updateFields);
            \App\Libraries\AdminAudit::log($this->request, $this->currentUser(), 'category_updated', 'category', $id, ['changes' => $updateFields]);
            $category = $this->categoryModel->find($id);
        }

        return $this->respondSuccess($category, lang('Api.category_updated'));
    }

    /**
     * DELETE /api/v1/admin/categories/{id}
     */
    public function delete(int $id): ResponseInterface
    {
        $category = $this->categoryModel->find($id);
        if (!$category) {
            throw new NotFoundException('الفئة غير موجودة');
        }

        // products.category_id is ON DELETE RESTRICT (also enforced on SQLite): deleting a
        // category that still has products (soft-deleted ones included) raised a DB error (500).
        $inUse = (new \App\Models\ProductModel())->withDeleted()->where('category_id', $id)->countAllResults();
        if ($inUse > 0) {
            throw new \App\Exceptions\ConflictException('لا يمكن حذف الفئة لوجود منتجات مرتبطة بها، انقل المنتجات إلى فئة أخرى أولاً.');
        }

        $this->categoryModel->delete($id);
        \App\Libraries\AdminAudit::log($this->request, $this->currentUser(), 'category_deleted', 'category', $id, ['before' => $category->toArray()]);

        return $this->respondSuccess(null, lang('Api.category_deleted'));
    }

    /**
     * slug is UNIQUE in the schema: a duplicate or empty slug used to surface as a 500.
     */
    private function assertSlugAvailable(string $slug, ?int $ignoreId = null): string
    {
        if ($slug === '') {
            throw new ValidationFailedException(['slug' => 'معرف الرابط (slug) غير صالح']);
        }
        $builder = $this->categoryModel->where('slug', $slug);
        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }
        if ($builder->countAllResults() > 0) {
            throw new ValidationFailedException(['slug' => 'معرف الرابط مستخدم بالفعل لـالفئة أخرى']);
        }

        return $slug;
    }
}
