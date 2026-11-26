<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Exceptions\ValidationFailedException;
use App\Models\ProductModel;
use App\Presenters\AdminProductPresenter;
use App\Services\ProductService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ProductAdminController
 *
 * Administrator operations for products, stock, and accessory links.
 */
class ProductAdminController extends BaseApiController
{
    protected ProductService $productService;
    protected ProductModel $productModel;

    public function __construct(
        ?ProductService $productService = null,
        ?ProductModel $productModel = null
    ) {
        $this->productService = $productService ?? new ProductService();
        $this->productModel   = $productModel ?? new ProductModel();
    }

    /**
     * GET /api/v1/admin/products
     */
    public function index(): ResponseInterface
    {
        [$page, $perPage] = $this->getPaginationParams();

        $filters = [];
        foreach (['type', 'category_id', 'is_active', 'q'] as $key) {
            $val = $this->request->getGet($key);
            if ($val !== null && $val !== '') {
                $filters[$key] = $val;
            }
        }

        $result = $this->productService->listAdminProducts($filters, $page, $perPage);
        $presentedItems = AdminProductPresenter::presentCollection($result['items']);
        // The admin list had no image at all: every row showed a placeholder. One preloaded
        // query, same shape as the public list's `image`.
        $images = $this->productService->primaryImagesFor(array_map(static fn ($i) => (int) $i['id'], $presentedItems));
        foreach ($presentedItems as &$item) {
            $img = $images[(int) $item['id']] ?? null;
            $item['image'] = $img ? ['path' => $img->path, 'thumb_path' => $img->thumb_path] : null;
        }
        unset($item);

        return $this->respondPaginated(
            items: $presentedItems,
            page: $page,
            perPage: $perPage,
            total: $result['total'],
            message: 'قائمة الأجهزة (إدارة)'
        );
    }

    /**
     * POST /api/v1/admin/products
     */
    public function create(): ResponseInterface
    {
        $body = $this->getJsonBody();
        $product = $this->productService->createProduct($body);
        $this->audit('product_created', (int) $product->id, ['after' => $product->toArray()]);

        return $this->respondCreated(AdminProductPresenter::present($product), lang('Api.product_created'));
    }

    /**
     * GET /api/v1/admin/products/{id}
     */
    public function show(int $id): ResponseInterface
    {
        $product = $this->productModel->find($id);
        if (!$product) {
            return $this->respondError(lang('Api.product_not_found'), 404);
        }

        return $this->respondSuccess(AdminProductPresenter::present($product), 'بيانات الجهاز');
    }

    /**
     * PATCH /api/v1/admin/products/{id}
     */
    public function update(int $id): ResponseInterface
    {
        $body   = $this->getJsonBody();
        $before = $this->productModel->find($id);
        $product = $this->productService->updateProduct($id, $body);
        $this->audit('product_updated', $id, $this->diff($before ? $before->toArray() : [], $product->toArray()));

        return $this->respondSuccess(AdminProductPresenter::present($product), lang('Api.product_updated'));
    }

    /**
     * DELETE /api/v1/admin/products/{id}
     */
    public function delete(int $id): ResponseInterface
    {
        $this->productService->deleteProduct($id);
        $this->audit('product_deleted', $id, []);

        return $this->respondSuccess(null, lang('Api.product_deleted'));
    }

    /**
     * PATCH /api/v1/admin/products/{id}/stock
     */
    public function updateStock(int $id): ResponseInterface
    {
        $body = $this->getJsonBody();

        if (!isset($body['stock_quantity']) || !is_numeric($body['stock_quantity'])) {
            throw new ValidationFailedException(['stock_quantity' => 'الكمية مطلوبة ويجب أن تكون رقماً']);
        }

        $before  = $this->productModel->find($id);
        $product = $this->productService->updateStock($id, (int) $body['stock_quantity']);
        $this->audit('product_stock_updated', $id, [
            'from' => $before ? (int) $before->stock_quantity : null,
            'to'   => (int) $product->stock_quantity,
        ]);

        return $this->respondSuccess(AdminProductPresenter::present($product), 'تم تحديث كمية المخزون بنجاح');
    }

    /**
     * POST /api/v1/admin/products/{id}/accessories
     */
    public function linkAccessory(int $id): ResponseInterface
    {
        $body = $this->getJsonBody();

        if (empty($body['accessory_id']) || !is_numeric($body['accessory_id'])) {
            throw new ValidationFailedException(['accessory_id' => 'معرف الإكسسوار مطلوب']);
        }

        $this->productService->linkAccessory($id, (int) $body['accessory_id']);
        $this->audit('product_accessory_linked', $id, ['accessory_id' => (int) $body['accessory_id']]);

        return $this->respondSuccess(null, 'تم ربط الإكسسوار بالجهاز بنجاح');
    }

    /**
     * DELETE /api/v1/admin/products/{id}/accessories/{accessory_id}
     */
    public function unlinkAccessory(int $id, int $accessoryId): ResponseInterface
    {
        $this->productService->unlinkAccessory($id, $accessoryId);
        $this->audit('product_accessory_unlinked', $id, ['accessory_id' => $accessoryId]);

        return $this->respondSuccess(null, 'تم فك ارتباط الإكسسوار بنجاح');
    }

    /**
     * @param array<string, mixed> $details
     */
    private function audit(string $action, int $productId, array $details): void
    {
        try {
            $user = $this->currentUser();
            (new \App\Services\AuditLogService())->log(
                $user ? (int) $user->id : null,
                $action,
                'product',
                $productId,
                $details,
                $this->request->getIPAddress()
            );
        } catch (\Throwable $e) {
            log_message('error', 'Audit log write failed for {action}: {msg}', ['action' => $action, 'msg' => $e->getMessage()]);
        }
    }

    /**
     * @return array{changes: array<string, array{from: mixed, to: mixed}>}
     */
    private function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $key => $value) {
            if ($key === 'updated_at') {
                continue;
            }
            $old = $before[$key] ?? null;
            if ((string) json_encode($old) !== (string) json_encode($value)) {
                $changes[$key] = ['from' => $old, 'to' => $value];
            }
        }

        return ['changes' => $changes];
    }
}
