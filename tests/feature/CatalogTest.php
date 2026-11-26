<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * @internal
 */
final class CatalogTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    public function testListPublicProductsReturnsPaginatedJson(): void
    {
        $result = $this->get('api/v1/products?page=1&per_page=5');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertTrue($body['success']);
        $this->assertCount(5, $body['data']);
        $this->assertSame(1, $body['meta']['page']);
        $this->assertSame(5, $body['meta']['per_page']);
        $this->assertSame(14, $body['meta']['total']);

        // Check currency structure on item: customer sees ONLY SDG amounts, never AED or rate
        $item = $body['data'][0];
        $this->assertArrayNotHasKey('price_aed', $item);
        $this->assertArrayNotHasKey('rate_sdg', $item);
        $this->assertArrayHasKey('price_sdg', $item);
        $this->assertArrayHasKey('stock_status', $item);
    }

    public function testFilterProductsByType(): void
    {
        // 1. Filter laptops only (8 seeded)
        $result = $this->get('api/v1/products?type=laptop');
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertCount(8, $body['data']);

        // 2. Filter accessories only (6 seeded)
        $accResult = $this->get('api/v1/products?type=accessory');
        $accResult->assertStatus(200);
        $accBody = json_decode($accResult->getJSON(), true);
        $this->assertCount(6, $accBody['data']);
    }

    public function testFilterProductsBySearchQuery(): void
    {
        $result = $this->get('api/v1/products?q=ThinkPad');
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertCount(1, $body['data']);
        $this->assertSame('lenovo-thinkpad-t480', $body['data'][0]['slug']);
    }

    public function testProductDetailReturnsAccessoriesAndSpecs(): void
    {
        $result = $this->get('api/v1/products/dell-latitude-7490');
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertTrue($body['success']);
        $prod = $body['data'];
        $this->assertSame('Dell Latitude 7490', $prod['name']);
        $this->assertIsArray($prod['specs']);
        $this->assertSame('Intel Core i5-8350U', $prod['specs']['cpu']);

        $this->assertIsArray($prod['accessories']);
        $this->assertCount(6, $prod['accessories']);

        // Test fallback lookup by numeric id
        $idResult = $this->get('api/v1/products/' . $prod['id']);
        $idResult->assertStatus(200);
        $idBody = json_decode($idResult->getJSON(), true);
        $this->assertSame('dell-latitude-7490', $idBody['data']['slug']);
    }

    public function testAdminRoutesForbiddenForRegularCustomer(): void
    {
        $userModel = new UserModel();
        // Create or get customer
        $customer = $userModel->where('role', Wex::ROLE_CUSTOMER)->first();
        if (!$customer) {
            $id = $userModel->insert([
                'name'      => 'زبون عادي',
                'email'     => 'cust_' . uniqid() . '@wex.sd',
                'role'      => Wex::ROLE_CUSTOMER,
                'is_active' => 1,
            ]);
            $customer = $userModel->find($id);
        }

        $tokenService = new TokenService();
        $access = $tokenService->generateAccessToken($customer);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $access['access_token']])
            ->get('api/v1/admin/products');

        $result->assertStatus(403);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
    }

    /** The admin product list had no image field: every row showed a placeholder. */
    public function testAdminProductListIncludesPrimaryImage(): void
    {
        $db = \Config\Database::connect();
        $product = $db->table('products')->orderBy('id', 'ASC')->get()->getRow();
        $db->table('product_images')->where('product_id', $product->id)->delete();
        $db->table('product_images')->insert([
            'product_id' => $product->id, 'path' => '/uploads/products/t.jpg', 'thumb_path' => '/uploads/products/t_thumb.jpg',
            'sort_order' => 0, 'is_primary' => 1, 'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $admin = (new UserModel())->where('role', Wex::ROLE_ADMIN)->first();
        $access = (new TokenService())->generateAccessToken($admin);

        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $access['access_token']])
            ->get('api/v1/admin/products?per_page=100');
        $result->assertStatus(200);
        $items = json_decode($result->getJSON(), true)['data'];
        foreach ($items as $item) {
            $this->assertArrayHasKey('image', $item);
        }
        $row = array_values(array_filter($items, static fn ($i) => (int) $i['id'] === (int) $product->id))[0];
        $this->assertSame('/uploads/products/t_thumb.jpg', $row['image']['thumb_path']);
    }

    public function testAdminCanCreateAndSoftDeleteProduct(): void
    {
        $userModel = new UserModel();
        $admin = $userModel->where('role', Wex::ROLE_ADMIN)->first();

        $tokenService = new TokenService();
        $access = $tokenService->generateAccessToken($admin);

        // 1. Create a product
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $access['access_token']])
            ->withBody(json_encode([
                'name'           => 'لابتوب تجريبي للاختبار',
                'type'           => 'laptop',
                'category_id'    => 1,
                'condition'      => 'new',
                'price_aed'      => 1000,
                'stock_quantity' => 5,
            ]))
            ->post('api/v1/admin/products');

        $result->assertStatus(201);
        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
        $newId = $body['data']['id'];

        // 2. Soft delete the product
        $delResult = $this->withHeaders(['Authorization' => 'Bearer ' . $access['access_token']])
            ->delete("api/v1/admin/products/{$newId}");

        $delResult->assertStatus(200);

        // 3. Confirm not returned in public listing
        $pubResult = $this->get("api/v1/products/{$body['data']['slug']}");
        $pubResult->assertStatus(404);
    }
}
