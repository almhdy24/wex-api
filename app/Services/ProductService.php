<?php

namespace App\Services;

use App\Entities\Product;
use App\Exceptions\ConflictException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Models\BrandModel;
use App\Models\CategoryModel;
use App\Models\ProductAccessoryModel;
use App\Models\ProductImageModel;
use App\Models\ProductModel;
use App\Models\SettingModel;
use Config\Wex;

/**
 * ProductService
 *
 * Handles catalog browsing, product search/filtering, pricing presentation,
 * accessory associations, and admin product CRUD.
 */
class ProductService
{
    protected ProductModel $productModel;
    protected ProductImageModel $imageModel;
    protected ProductAccessoryModel $accessoryModel;
    protected CategoryModel $categoryModel;
    protected BrandModel $brandModel;
    protected PricingService $pricingService;
    protected SettingModel $settingModel;
    protected SettingService $settingService;

    public function __construct(
        ?ProductModel $productModel = null,
        ?ProductImageModel $imageModel = null,
        ?ProductAccessoryModel $accessoryModel = null,
        ?CategoryModel $categoryModel = null,
        ?BrandModel $brandModel = null,
        ?PricingService $pricingService = null,
        ?SettingModel $settingModel = null,
        ?SettingService $settingService = null
    ) {
        $this->productModel   = $productModel ?? new ProductModel();
        $this->imageModel     = $imageModel ?? new ProductImageModel();
        $this->accessoryModel = $accessoryModel ?? new ProductAccessoryModel();
        $this->categoryModel  = $categoryModel ?? new CategoryModel();
        $this->brandModel     = $brandModel ?? new BrandModel();
        $this->pricingService = $pricingService ?? new PricingService();
        $this->settingModel   = $settingModel ?? new SettingModel();
        $this->settingService = $settingService ?? new SettingService();
    }

    /**
     * Lists public active products with filtering, sorting, pagination,
     * and dynamic SDG price calculations.
     */
    public function listPublicProducts(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $builder = $this->productModel->where('is_active', 1);

        // Filter by Product Type
        if (!empty($filters['type']) && in_array($filters['type'], Wex::PRODUCT_TYPES, true)) {
            $builder->where('type', $filters['type']);
        }

        // Filter by Category (slug or ID)
        if (!empty($filters['category'])) {
            if (is_numeric($filters['category'])) {
                $builder->where('category_id', (int) $filters['category']);
            } else {
                $cat = $this->categoryModel->where('slug', $filters['category'])->first();
                if ($cat) {
                    $builder->where('category_id', $cat->id);
                } else {
                    $builder->where('category_id', 0); // No match
                }
            }
        }

        // Filter by Brand (slug, name, or ID)
        if (!empty($filters['brand'])) {
            if (is_numeric($filters['brand'])) {
                $builder->where('brand_id', (int) $filters['brand']);
            } else {
                $brandInput = trim((string) $filters['brand']);
                $brand = $this->brandModel
                    ->groupStart()
                        ->where('slug', strtolower($brandInput))
                        ->orWhere('name', $brandInput)
                    ->groupEnd()
                    ->first();
                if ($brand) {
                    $builder->where('brand_id', (int) $brand->id);
                } else {
                    $builder->where('brand_id', 0);
                }
            }
        }

        // Filter by Condition
        if (!empty($filters['condition']) && in_array($filters['condition'], Wex::CONDITIONS, true)) {
            $builder->where('condition', $filters['condition']);
        }

        // Filter by Price Range in SDG (internal conversion to AED)
        if (isset($filters['min_price_sdg']) && is_numeric($filters['min_price_sdg'])) {
            $minPriceSdg = (int) $filters['min_price_sdg'];
            $rate = $this->pricingService->getCurrentRateSdg();
            $minPriceAed = (int) floor($minPriceSdg / $rate);
            $builder->where('price_aed >=', $minPriceAed);
        }
        if (isset($filters['max_price_sdg']) && is_numeric($filters['max_price_sdg'])) {
            $maxPriceSdg = (int) $filters['max_price_sdg'];
            $rate = $this->pricingService->getCurrentRateSdg();
            $maxPriceAed = (int) ceil($maxPriceSdg / $rate);
            $builder->where('price_aed <=', $maxPriceAed);
        }

        // Backward compatibility for AED filter if ever used internally
        if (isset($filters['min_price_aed']) && is_numeric($filters['min_price_aed'])) {
            $builder->where('price_aed >=', (int) $filters['min_price_aed']);
        }
        if (isset($filters['max_price_aed']) && is_numeric($filters['max_price_aed'])) {
            $builder->where('price_aed <=', (int) $filters['max_price_aed']);
        }

        // Filter by Featured
        if (isset($filters['featured'])) {
            $isFeatured = filter_var($filters['featured'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $builder->where('is_featured', $isFeatured);
        }

        // Filter by In-Stock
        if (isset($filters['in_stock']) && filter_var($filters['in_stock'], FILTER_VALIDATE_BOOLEAN)) {
            $builder->where('stock_quantity >', 0);
        }

        // Search Query (q): Arabic-normalised, cross-script, every word must match.
        if (!empty($filters['q'])) {
            $this->applySearch($builder, (string) $filters['q']);
        }

        // Sorting (whitelist allowed sorts only)
        $sort = $filters['sort'] ?? 'newest';
        match ($sort) {
            'price_asc'  => $builder->orderBy('price_aed', 'ASC'),
            'price_desc' => $builder->orderBy('price_aed', 'DESC'),
            default      => $builder->orderBy('created_at', 'DESC'),
        };

        $total = $builder->countAllResults(false);

        $offset = ($page - 1) * $perPage;
        $products = $builder->findAll($perPage, $offset);

        $rateSdg       = $this->pricingService->getCurrentRateSdg();
        $rateUpdatedAt = $this->pricingService->getRateUpdatedAt();
        $lowThreshold  = $this->getLowStockThreshold();

        // One query for all primary images instead of one per product (N+1).
        $imagesByProduct = $this->primaryImagesFor(array_map(static fn ($p) => (int) $p->id, $products));

        $items = [];
        foreach ($products as $prod) {
            $items[] = $this->formatProductListItem($prod, $rateSdg, $rateUpdatedAt, $lowThreshold, $imagesByProduct);
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Retrieves full product detail by slug for public viewing.
     */
    public function getPublicProductDetail(string $slug): array
    {
        $product = $this->productModel->where('slug', $slug)->where('is_active', 1)->first();

        if (!$product && is_numeric($slug)) {
            $product = $this->productModel->where('id', (int) $slug)->where('is_active', 1)->first();
        }

        if (!$product) {
            throw new NotFoundException(lang('Api.product_not_found'));
        }

        $rateSdg       = $this->pricingService->getCurrentRateSdg();
        $rateUpdatedAt = $this->pricingService->getRateUpdatedAt();
        $lowThreshold  = $this->getLowStockThreshold();

        // Load images
        $images = $this->imageModel
            ->where('product_id', $product->id)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        $formattedImages = array_map(static fn($img) => [
            'id'         => (int) $img->id,
            'path'       => $img->path,
            'thumb_path' => $img->thumb_path,
            'is_primary' => (bool) $img->is_primary,
            'sort_order' => (int) $img->sort_order,
        ], $images);

        // Load accessories
        $accessories = $this->getSuggestedAccessories((int) $product->id, $rateSdg, $rateUpdatedAt, $lowThreshold);

        // Category & Brand info
        $category = $this->categoryModel->find($product->category_id);
        $brand    = $product->brand_id ? $this->brandModel->find($product->brand_id) : null;

        return [
            'id'                => (int) $product->id,
            'slug'              => $product->slug,
            'type'              => $product->type,
            'name'              => $product->name,
            'short_description' => $product->short_description,
            'description'       => $product->description,
            'condition'         => $product->condition,
            'price_sdg'         => $this->pricingService->convertAedToSdg((int) $product->price_aed, $rateSdg),
            'stock_status'      => $product->getStockStatus($lowThreshold),
            'warranty_text'     => $product->warranty_text,
            'specs'             => $product->getSpecs(),
            'is_featured'       => (bool) $product->is_featured,
            'category'          => $category ? [
                'id'      => (int) $category->id,
                'name_ar' => $category->name_ar,
                'name_en' => $category->name_en,
                'slug'    => $category->slug,
            ] : null,
            'brand'             => $brand ? [
                'id'   => (int) $brand->id,
                'name' => $brand->name,
                'slug' => $brand->slug,
            ] : null,
            'images'            => $formattedImages,
            'accessories'       => $accessories,
        ];
    }

    /**
     * Formats a single product for catalog list views.
     */
    /**
     * Primary (else first by sort order) image per product, in a single query.
     *
     * @param list<int> $productIds
     * @return array<int, object>
     */
    public function primaryImagesFor(array $productIds): array
    {
        if ($productIds === []) {
            return [];
        }
        $rows = $this->imageModel
            ->whereIn('product_id', $productIds)
            ->orderBy('product_id', 'ASC')
            ->orderBy('is_primary', 'DESC')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();
        $out = [];
        foreach ($rows as $img) {
            $out[(int) $img->product_id] ??= $img;
        }

        return $out;
    }

    protected function formatProductListItem(Product $prod, int $rateSdg, string $rateUpdatedAt, int $lowThreshold, ?array $imagesByProduct = null): array
    {
        // Get primary or first image (preloaded for lists; single query otherwise)
        $primaryImage = $imagesByProduct !== null
            ? ($imagesByProduct[(int) $prod->id] ?? null)
            : ($this->primaryImagesFor([(int) $prod->id])[(int) $prod->id] ?? null);

        return [
            'id'                => (int) $prod->id,
            'slug'              => $prod->slug,
            'type'              => $prod->type,
            'name'              => $prod->name,
            'short_description' => $prod->short_description,
            'condition'         => $prod->condition,
            'price_sdg'         => $this->pricingService->convertAedToSdg((int) $prod->price_aed, $rateSdg),
            'stock_status'      => $prod->getStockStatus($lowThreshold),
            'is_featured'       => (bool) $prod->is_featured,
            // Compact spec map so list cards can show "i5 · 8GB · 256GB SSD" without a detail call.
            'specs'             => $prod->getSpecs(),
            'image'             => $primaryImage ? [
                'path'       => $primaryImage->path,
                'thumb_path' => $primaryImage->thumb_path,
            ] : null,
        ];
    }

    /**
     * Loads suggested accessories linked to a product.
     */
    protected function getSuggestedAccessories(int $productId, int $rateSdg, string $rateUpdatedAt, int $lowThreshold): array
    {
        $links = $this->accessoryModel
            ->where('product_id', $productId)
            ->orderBy('sort_order', 'ASC')
            ->findAll();

        if (empty($links)) {
            return [];
        }

        $accessoryIds = array_map(static fn($l) => (int) $l->accessory_id, $links);
        $accessories  = $this->productModel
            ->whereIn('id', $accessoryIds)
            ->where('is_active', 1)
            ->findAll();

        $items = [];
        foreach ($accessories as $acc) {
            $img = $this->imageModel
                ->where('product_id', $acc->id)
                ->orderBy('is_primary', 'DESC')
                ->first();

            $items[] = [
                'id'                => (int) $acc->id,
                'slug'              => $acc->slug,
                'type'              => $acc->type,
                'name'              => $acc->name,
                'short_description' => $acc->short_description,
                'condition'         => $acc->condition,
                'price_sdg'         => $this->pricingService->convertAedToSdg((int) $acc->price_aed, $rateSdg),
                'stock_status'      => $acc->getStockStatus($lowThreshold),
                'image'             => $img ? [
                    'path'       => $img->path,
                    'thumb_path' => $img->thumb_path,
                ] : null,
            ];
        }

        return $items;
    }

    /**
     * Retrieves the low stock threshold setting.
     */
    protected function getLowStockThreshold(): int
    {
        $row = $this->settingModel->where('key', 'low_stock_threshold')->first();
        return $row ? max(1, (int) $row->value) : 2;
    }

    // -----------------------------------------------------------------
    // Admin Management Methods
    // -----------------------------------------------------------------

    public function listAdminProducts(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $builder = $this->productModel;

        if (!empty($filters['type'])) {
            $builder->where('type', $filters['type']);
        }
        if (!empty($filters['category_id'])) {
            $builder->where('category_id', (int) $filters['category_id']);
        }
        if (isset($filters['is_active'])) {
            $builder->where('is_active', (int) $filters['is_active']);
        }
        if (!empty($filters['q'])) {
            $this->applySearch($builder, (string) $filters['q']);
        }

        $total = $builder->countAllResults(false);
        $offset = ($page - 1) * $perPage;
        $items = $builder->orderBy('id', 'DESC')->findAll($perPage, $offset);

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    public function createProduct(array $data): Product
    {
        $this->validateProductData($data);

        $now = gmdate('Y-m-d H:i:s');
        $slug = $this->generateUniqueSlug($data['name']);

        $id = $this->productModel->insert([
            'type'              => $data['type'],
            'category_id'       => (int) $data['category_id'],
            'brand_id'          => !empty($data['brand_id']) ? (int) $data['brand_id'] : null,
            'name'              => trim($data['name']),
            'slug'              => $slug,
            'short_description' => $data['short_description'] ?? null,
            'description'       => $data['description'] ?? null,
            'condition'         => $data['condition'],
            'price_aed'         => (int) $data['price_aed'],
            'specs'             => isset($data['specs']) ? (is_array($data['specs']) ? json_encode($data['specs'], JSON_UNESCAPED_UNICODE) : $data['specs']) : null,
            'warranty_text'     => $data['warranty_text'] ?? null,
            'stock_quantity'    => isset($data['stock_quantity']) ? (int) $data['stock_quantity'] : 0,
            'is_active'         => isset($data['is_active']) ? (int) \App\Libraries\Input::bool($data['is_active']) : 1,
            'is_featured'       => isset($data['is_featured']) ? (int) \App\Libraries\Input::bool($data['is_featured']) : 0,
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);

        $this->settingService->touchPricesUpdatedAt();
        $this->refreshSearchText((int) $id);

        return $this->productModel->find($id);
    }

    public function updateProduct(int $id, array $data): Product
    {
        $product = $this->productModel->find($id);
        if (!$product) {
            throw new NotFoundException(lang('Api.product_not_found'));
        }

        $this->validateProductData($data, true);

        $updateFields = [];

        if (isset($data['name'])) {
            $name = trim($data['name']);
            if ($name !== $product->name) {
                $updateFields['name'] = $name;
                $updateFields['slug'] = $this->generateUniqueSlug($name, $id);
            }
        }

        $allowedKeys = [
            'type', 'category_id', 'brand_id', 'short_description', 'description',
            'condition', 'price_aed', 'warranty_text', 'stock_quantity',
            'is_active', 'is_featured',
        ];

        $intKeys  = ['category_id', 'price_aed', 'stock_quantity'];
        $boolKeys = ['is_active', 'is_featured'];
        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data)) {
                $value = $data[$key];
                if (in_array($key, $intKeys, true)) {
                    $value = (int) $value;
                } elseif (in_array($key, $boolKeys, true)) {
                    $value = (int) filter_var($value, FILTER_VALIDATE_BOOLEAN);
                } elseif ($key === 'brand_id') {
                    $value = !empty($value) ? (int) $value : null;
                }
                $updateFields[$key] = $value;
            }
        }

        if (isset($data['specs'])) {
            $updateFields['specs'] = is_array($data['specs'])
                ? json_encode($data['specs'], JSON_UNESCAPED_UNICODE)
                : $data['specs'];
        }

        if (!empty($updateFields)) {
            $updateFields['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->productModel->update($id, $updateFields);
            $product = $this->productModel->find($id);

            if (array_key_exists('price_aed', $updateFields)) {
                $this->settingService->touchPricesUpdatedAt();
            }
            $this->refreshSearchText($id);
            $product = $this->productModel->find($id);
        }

        return $product;
    }

    public function deleteProduct(int $id): bool
    {
        $product = $this->productModel->find($id);
        if (!$product) {
            throw new NotFoundException(lang('Api.product_not_found'));
        }

        return (bool) $this->productModel->delete($id);
    }

    public function updateStock(int $id, int $stockQuantity): Product
    {
        $product = $this->productModel->find($id);
        if (!$product) {
            throw new NotFoundException(lang('Api.product_not_found'));
        }

        $this->productModel->update($id, [
            'stock_quantity' => max(0, $stockQuantity),
            'updated_at'     => gmdate('Y-m-d H:i:s'),
        ]);

        return $this->productModel->find($id);
    }

    public function linkAccessory(int $productId, int $accessoryId): bool
    {
        $product   = $this->productModel->find($productId);
        $accessory = $this->productModel->find($accessoryId);

        if (!$product || !$accessory) {
            throw new NotFoundException('الجهاز أو الإكسسوار غير موجود');
        }

        if ($productId === $accessoryId) {
            throw new ValidationFailedException(['accessory_id' => 'لا يمكن ربط الجهاز بنفسه كإكسسوار']);
        }

        $exists = $this->accessoryModel
            ->where('product_id', $productId)
            ->where('accessory_id', $accessoryId)
            ->first();

        if ($exists) {
            return true;
        }

        $maxSort = (int) $this->accessoryModel
            ->where('product_id', $productId)
            ->selectMax('sort_order')
            ->first()?->sort_order;

        return (bool) $this->accessoryModel->insert([
            'product_id'   => $productId,
            'accessory_id' => $accessoryId,
            'sort_order'   => $maxSort + 1,
        ]);
    }

    public function unlinkAccessory(int $productId, int $accessoryId): bool
    {
        return (bool) $this->accessoryModel
            ->where('product_id', $productId)
            ->where('accessory_id', $accessoryId)
            ->delete();
    }

    /**
     * Every normalised query word must appear in search_text (or the raw name/slug, for
     * rows saved before search_text existed).
     */
    protected function applySearch($builder, string $query): void
    {
        $tokens = \App\Libraries\ArabicSearch::tokens($query);
        if ($tokens === []) {
            return;
        }
        foreach ($tokens as $tok) {
            $builder->groupStart()
                ->like('search_text', ' ' . $tok)          // word-start match
                ->orGroupStart()
                    ->where('search_text', null)            // rows not yet indexed
                    ->like('name', $tok)
                ->groupEnd()
                ->groupEnd();
        }
    }

    /** Recomputes products.search_text from the product and its brand/category names. */
    public function refreshSearchText(int $productId): void
    {
        $p = $this->productModel->withDeleted()->find($productId);
        if (!$p) {
            return;
        }
        $brand = $p->brand_id ? $this->brandModel->find($p->brand_id) : null;
        $cat   = $p->category_id ? $this->categoryModel->find($p->category_id) : null;
        $specs = is_string($p->specs ?? null) ? $p->specs : json_encode($p->specs ?? [], JSON_UNESCAPED_UNICODE);
        $this->productModel->builder()->where('id', $productId)->update(['search_text' => \App\Libraries\ArabicSearch::index(
            (string) $p->name, (string) ($p->short_description ?? ''), (string) ($p->description ?? ''), (string) $specs,
            (string) ($brand->name ?? ''), trim(($cat->name_ar ?? '') . ' ' . ($cat->name_en ?? '')),
        )]);
    }

    /**
     * Validates product input. With $partial=true (PATCH) only the keys present are checked,
     * but every present key is checked: an update must not be able to set a zero/negative
     * price, negative stock, an unknown type/condition or a non-existent category/brand.
     */
    protected function validateProductData(array $data, bool $partial = false): void
    {
        $errors = [];
        $has = static fn (string $k): bool => !$partial || array_key_exists($k, $data);

        if ($has('name') && trim((string) ($data['name'] ?? '')) === '') {
            $errors['name'] = 'اسم الجهاز مطلوب';
        } elseif ($has('name') && mb_strlen(trim((string) $data['name'])) > 191) {
            $errors['name'] = 'اسم الجهاز طويل جداً';
        }
        if ($has('type') && (empty($data['type']) || !in_array($data['type'], Wex::PRODUCT_TYPES, true))) {
            $errors['type'] = 'نوع المنتج غير صالح (laptop, phone, accessory)';
        }
        if ($has('condition') && (empty($data['condition']) || !in_array($data['condition'], Wex::CONDITIONS, true))) {
            $errors['condition'] = 'حالة المنتج غير صالحة (new, used_imported)';
        }
        if ($has('price_aed') && (!isset($data['price_aed']) || filter_var($data['price_aed'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000000]]) === false)) {
            $errors['price_aed'] = 'سعر الدرهم مطلوب ويجب أن يكون رقماً صحيحاً أكبر من صفر';
        }
        if (array_key_exists('stock_quantity', $data) && filter_var($data['stock_quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]) === false) {
            $errors['stock_quantity'] = 'الكمية يجب أن تكون رقماً صحيحاً بين 0 و 100000';
        }
        if ($has('category_id')) {
            if (empty($data['category_id']) || !is_numeric($data['category_id'])) {
                $errors['category_id'] = 'فئة المنتج مطلوبة';
            } elseif (!$this->categoryModel->find((int) $data['category_id'])) {
                $errors['category_id'] = 'الفئة المحددة غير موجودة';
            }
        }
        if (!empty($data['brand_id']) && (!is_numeric($data['brand_id']) || !$this->brandModel->find((int) $data['brand_id']))) {
            $errors['brand_id'] = 'العلامة التجارية المحددة غير موجودة';
        }

        if (!empty($errors)) {
            throw new ValidationFailedException($errors);
        }
    }

    protected function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        // Simple slugger transliterating English words and cleaning up
        $slug = preg_replace('/[^\p{L}\p{Nd}]+/u', '-', mb_strtolower(trim($name)));
        $slug = trim($slug, '-');

        if ($slug === '') {
            $slug = 'product-' . bin2hex(random_bytes(4));
        }

        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $builder = $this->productModel->withDeleted()->where('slug', $slug);
            if ($ignoreId !== null) {
                $builder->where('id !=', $ignoreId);
            }
            if ($builder->countAllResults() === 0) {
                break;
            }
            $slug = $originalSlug . '-' . (++$counter);
        }

        return $slug;
    }
}
