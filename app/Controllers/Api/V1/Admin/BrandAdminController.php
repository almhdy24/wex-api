<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Models\BrandModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * BrandAdminController
 *
 * Administrator operations for product brands.
 */
class BrandAdminController extends BaseApiController
{
    protected BrandModel $brandModel;

    public function __construct(?BrandModel $brandModel = null)
    {
        $this->brandModel = $brandModel ?? new BrandModel();
    }

    /**
     * GET /api/v1/admin/brands
     */
    public function index(): ResponseInterface
    {
        $brands = $this->brandModel->orderBy('name', 'ASC')->findAll();

        return $this->respondSuccess($brands, 'قائمة الماركات');
    }

    /**
     * POST /api/v1/admin/brands
     */
    public function create(): ResponseInterface
    {
        $body = $this->getJsonBody();

        if (empty($body['name'])) {
            throw new ValidationFailedException(['name' => 'اسم الماركة مطلوب']);
        }

        $slug = !empty($body['slug'])
            ? preg_replace('/[^\p{L}\p{Nd}]+/u', '-', mb_strtolower(trim($body['slug'])))
            : preg_replace('/[^\p{L}\p{Nd}]+/u', '-', mb_strtolower(trim($body['name'])));

        $slug = $this->assertSlugAvailable(trim((string) $slug, '-'));

        $now = gmdate('Y-m-d H:i:s');

        $id = $this->brandModel->insert([
            'name'       => trim($body['name']),
            'slug'       => $slug,
            'is_active'  => isset($body['is_active']) ? (int) \App\Libraries\Input::bool($body['is_active']) : 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $brand = $this->brandModel->find($id);

        \App\Libraries\AdminAudit::log($this->request, $this->currentUser(), 'brand_created', 'brand', (int) $id, ['after' => $brand->toArray()]);


        return $this->respondCreated($brand, lang('Api.brand_created'));
    }

    /**
     * GET /api/v1/admin/brands/{id}
     */
    public function show(int $id): ResponseInterface
    {
        $brand = $this->brandModel->find($id);
        if (!$brand) {
            throw new NotFoundException('الماركة غير موجودة');
        }

        return $this->respondSuccess($brand, 'بيانات الماركة');
    }

    /**
     * PATCH /api/v1/admin/brands/{id}
     */
    public function update(int $id): ResponseInterface
    {
        $brand = $this->brandModel->find($id);
        if (!$brand) {
            throw new NotFoundException('الماركة غير موجودة');
        }

        $body = $this->getJsonBody();
        $updateFields = [];

        foreach (['name', 'is_active'] as $key) {
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
            $this->brandModel->update($id, $updateFields);
            \App\Libraries\AdminAudit::log($this->request, $this->currentUser(), 'brand_updated', 'brand', $id, ['changes' => $updateFields]);
            $brand = $this->brandModel->find($id);
        }

        return $this->respondSuccess($brand, lang('Api.brand_updated'));
    }

    /**
     * DELETE /api/v1/admin/brands/{id}
     */
    public function delete(int $id): ResponseInterface
    {
        $brand = $this->brandModel->find($id);
        if (!$brand) {
            throw new NotFoundException('الماركة غير موجودة');
        }

        $this->brandModel->delete($id);
        \App\Libraries\AdminAudit::log($this->request, $this->currentUser(), 'brand_deleted', 'brand', $id, ['before' => $brand->toArray()]);

        return $this->respondSuccess(null, lang('Api.brand_deleted'));
    }

    /**
     * slug is UNIQUE in the schema: a duplicate or empty slug used to surface as a 500.
     */
    private function assertSlugAvailable(string $slug, ?int $ignoreId = null): string
    {
        if ($slug === '') {
            throw new ValidationFailedException(['slug' => 'معرف الرابط (slug) غير صالح']);
        }
        $builder = $this->brandModel->where('slug', $slug);
        if ($ignoreId !== null) {
            $builder->where('id !=', $ignoreId);
        }
        if ($builder->countAllResults() > 0) {
            throw new ValidationFailedException(['slug' => 'معرف الرابط مستخدم بالفعل لـالماركة أخرى']);
        }

        return $slug;
    }
}
