<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Models\ProductImageModel;
use App\Models\ProductModel;
use App\Services\ImageService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ProductImageAdminController
 *
 * Administrator operations for product photo uploads, ordering, and deletion.
 */
class ProductImageAdminController extends BaseApiController
{
    protected ProductModel $productModel;
    protected ProductImageModel $imageModel;
    protected ImageService $imageService;

    public function __construct(
        ?ProductModel $productModel = null,
        ?ProductImageModel $imageModel = null,
        ?ImageService $imageService = null
    ) {
        $this->productModel = $productModel ?? new ProductModel();
        $this->imageModel   = $imageModel ?? new ProductImageModel();
        $this->imageService = $imageService ?? new ImageService();
    }

    /**
     * POST /api/v1/admin/products/{id}/images
     *
     * Handles multiple image file uploads.
     */
    public function upload(int $id): ResponseInterface
    {
        $product = $this->productModel->find($id);
        if (!$product) {
            throw new NotFoundException(lang('Api.product_not_found'));
        }

        $files = $this->request->getFiles();

        if (empty($files) || !isset($files['images'])) {
            throw new ValidationFailedException(['images' => 'يرجى اختيار صورة واحدة على الأقل']);
        }

        $uploadList = is_array($files['images']) ? $files['images'] : [$files['images']];

        $hasPrimary = $this->imageModel
            ->where('product_id', $id)
            ->where('is_primary', 1)
            ->countAllResults() > 0;

        $maxSort = (int) $this->imageModel
            ->where('product_id', $id)
            ->selectMax('sort_order')
            ->first()?->sort_order;

        $createdImages = [];
        $now = gmdate('Y-m-d H:i:s');

        foreach ($uploadList as $file) {
            if (!$file->isValid()) {
                continue;
            }

            $stored = $this->imageService->storeProductImage($file);

            $isPrimary = !$hasPrimary ? 1 : 0;
            if ($isPrimary) {
                $hasPrimary = true;
            }

            $imgId = $this->imageModel->insert([
                'product_id' => $id,
                'path'       => $stored['path'],
                'thumb_path' => $stored['thumb_path'],
                'sort_order' => ++$maxSort,
                'is_primary' => $isPrimary,
                'created_at' => $now,
            ]);

            $createdImages[] = $this->imageModel->find($imgId);
        }

        return $this->respondCreated($createdImages, lang('Api.image_uploaded'));
    }

    /**
     * PATCH /api/v1/admin/products/{id}/images/{imageId}
     */
    public function update(int $id, int $imageId): ResponseInterface
    {
        $image = $this->imageModel->where('product_id', $id)->find($imageId);
        if (!$image) {
            throw new NotFoundException('الصورة غير موجودة');
        }

        $body = $this->getJsonBody();

        // If setting as primary, demote other images for this product
        if (isset($body['is_primary']) && filter_var($body['is_primary'], FILTER_VALIDATE_BOOLEAN)) {
            $this->imageModel
                ->where('product_id', $id)
                ->set(['is_primary' => 0])
                ->update();

            $this->imageModel->update($imageId, ['is_primary' => 1]);
        }

        if (isset($body['sort_order']) && is_numeric($body['sort_order'])) {
            $this->imageModel->update($imageId, ['sort_order' => (int) $body['sort_order']]);
        }

        $updated = $this->imageModel->find($imageId);

        return $this->respondSuccess($updated, 'تم تحديث بيانات الصورة');
    }

    /**
     * DELETE /api/v1/admin/products/{id}/images/{imageId}
     */
    public function delete(int $id, int $imageId): ResponseInterface
    {
        $image = $this->imageModel->where('product_id', $id)->find($imageId);
        if (!$image) {
            throw new NotFoundException('الصورة غير موجودة');
        }

        // Delete files from disk
        if (!empty($image->path) && file_exists(FCPATH . $image->path)) {
            @unlink(FCPATH . $image->path);
        }
        if (!empty($image->thumb_path) && file_exists(FCPATH . $image->thumb_path)) {
            @unlink(FCPATH . $image->thumb_path);
        }

        $this->imageModel->delete($imageId);

        return $this->respondSuccess(null, lang('Api.image_deleted'));
    }
}
