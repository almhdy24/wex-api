<?php

namespace Tests\Feature;

use App\Database\Seeds\AdminSeeder;
use App\Database\Seeds\DatabaseSeeder;
use App\Entities\Product;
use App\Entities\ProductImage;
use App\Entities\User;
use App\Models\ProductImageModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class ProductImageFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected User $adminUser;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $tokenService = new TokenService();
        $userModel    = new UserModel();

        $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        if (!$admin) {
            (new AdminSeeder())->run();
            $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        }
        $this->adminUser  = $admin;
        $this->adminToken = $tokenService->generateAccessToken($admin)['access_token'];
    }

    private function createIsolatedProduct(): Product
    {
        $cat = $this->db->table('categories')->get()->getFirstRow();
        $productModel = new ProductModel();
        $id = $productModel->insert(new Product([
            'name'        => 'جهاز مخصص لاختبار الصور',
            'slug'        => 'test-image-laptop-' . uniqid(),
            'type'        => \Config\Wex::PRODUCT_TYPE_LAPTOP,
            'category_id' => $cat->id,
            'condition'   => \Config\Wex::CONDITION_USED_IMPORTED,
            'price_aed'   => 500,
            'stock'       => 3,
            'is_active'   => 1,
        ]));
        return $productModel->find($id);
    }

    public function testPatchImageAltAndPrimary(): void
    {
        $product = $this->createIsolatedProduct();
        $imageModel = new ProductImageModel();
        $imgId = $imageModel->insert(new ProductImage([
            'product_id' => $product->id,
            'path'       => 'uploads/products/test1.webp',
            'thumb_path' => 'uploads/products/thumbs/test1.webp',
            'sort_order' => 1,
            'is_primary' => 1,
        ]));

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'alt' => 'صورة الواجهة الأمامية للجهاز',
        ]))->patch("api/v1/admin/products/{$product->id}/images/{$imgId}");

        $result->assertStatus(200);
        $updated = $imageModel->find($imgId);
        $this->assertSame('صورة الواجهة الأمامية للجهاز', $updated->alt);
    }

    public function testReorderImages(): void
    {
        $product = $this->createIsolatedProduct();
        $imageModel = new ProductImageModel();
        $id1 = $imageModel->insert(new ProductImage([
            'product_id' => $product->id,
            'path'       => 'uploads/products/reorder1.webp',
            'sort_order' => 1,
            'is_primary' => 1,
        ]));
        $id2 = $imageModel->insert(new ProductImage([
            'product_id' => $product->id,
            'path'       => 'uploads/products/reorder2.webp',
            'sort_order' => 2,
            'is_primary' => 0,
        ]));

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'order' => [$id2, $id1],
        ]))->put("api/v1/admin/products/{$product->id}/images/order");

        $result->assertStatus(200);
        $img1 = $imageModel->find($id1);
        $img2 = $imageModel->find($id2);

        $this->assertEquals(2, $img1->sort_order);
        $this->assertEquals(1, $img2->sort_order);
    }

    public function testDeletePrimaryPromotesNext(): void
    {
        $product = $this->createIsolatedProduct();
        $imageModel = new ProductImageModel();
        $id1 = $imageModel->insert(new ProductImage([
            'product_id' => $product->id,
            'path'       => 'uploads/products/del1.webp',
            'sort_order' => 1,
            'is_primary' => 1,
        ]));
        $id2 = $imageModel->insert(new ProductImage([
            'product_id' => $product->id,
            'path'       => 'uploads/products/del2.webp',
            'sort_order' => 2,
            'is_primary' => 0,
        ]));

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->delete("api/v1/admin/products/{$product->id}/images/{$id1}");

        $result->assertStatus(200);

        $this->assertNull($imageModel->find($id1));
        $img2 = $imageModel->find($id2);
        $this->assertEquals(1, (int) $img2->is_primary);
    }
}
