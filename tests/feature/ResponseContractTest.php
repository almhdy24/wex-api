<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\User;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * D-10: response-field contract. Each list below is what wex-web pages actually read
 * (see docs/SYSTEM_MAP.md §4 and `grep -o "order\.[a-z_]*" src/`). A renamed or
 * dropped field fails here instead of silently rendering blanks/fallbacks.
 *
 * @internal
 */
final class ResponseContractTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    private const PRODUCT_LIST_ITEM = ['id', 'slug', 'name', 'type', 'condition', 'price_sdg', 'stock_status', 'is_featured', 'image'];
    private const PRODUCT_DETAIL    = ['id', 'slug', 'name', 'type', 'condition', 'price_sdg', 'stock_status', 'warranty_text', 'specs', 'images', 'category', 'brand', 'accessories', 'short_description', 'description'];
    private const SHOP              = ['name', 'address', 'phone', 'whatsapp', 'working_hours', 'lock_hours', 'orders_enabled', 'orders_disabled_message', 'ticker_enabled', 'prices_updated_at'];
    private const ORDER_DETAIL      = ['order_number', 'status', 'status_label', 'delivery_type', 'delivery_address', 'shipping_zone_name', 'delivery_phone', 'total_sdg', 'subtotal_sdg', 'discount_sdg', 'delivery_fee_sdg', 'lock_expires_at', 'seconds_remaining', 'items', 'timeline', 'proofs', 'payment', 'payment_accounts', 'created_at'];
    private const ORDER_ITEM        = ['name', 'quantity', 'unit_price_sdg', 'line_total_sdg'];
    private const ORDER_LIST_ITEM   = ['order_number', 'status', 'status_label', 'delivery_type', 'total_sdg', 'payment', 'seconds_remaining', 'created_at'];
    private const ADMIN_ORDER       = ['order_number', 'status', 'customer_name', 'customer_phone', 'customer', 'delivery_type', 'delivery_address', 'shipping_zone_name', 'items', 'proofs', 'payment_proofs', 'total_sdg', 'subtotal_sdg', 'delivery_fee_sdg'];
    private const ADMIN_PRODUCT     = ['id', 'name', 'brand_id', 'brand_name', 'category_id', 'price_sdg', 'stock_quantity', 'is_active'];

    private string $customerToken;
    private string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $users = new UserModel();
        $tokens = new TokenService();
        $email = 'contract_cust@example.com';
        if (!$users->where('email', $email)->first()) {
            $users->insert(new User(['name' => 'عميل العقد', 'email' => $email, 'role' => Wex::ROLE_CUSTOMER, 'is_active' => true]));
        }
        $this->customerToken = $tokens->generateAccessToken($users->where('email', $email)->first())['access_token'];
        $this->adminToken = $tokens->generateAccessToken($users->where('email', 'admin@wextechnology.com')->first())['access_token'];
    }

    private function getJson(string $url, ?string $token = null): array
    {
        $req = $token ? $this->withHeaders(['Authorization' => 'Bearer ' . $token]) : $this;
        $res = $req->get($url);
        $res->assertStatus(200);

        return json_decode($res->getJSON(), true)['data'];
    }

    private function assertKeys(array $expected, array $actual, string $where): void
    {
        foreach ($expected as $k) {
            $this->assertArrayHasKey($k, $actual, "{$where} is missing '{$k}'");
        }
    }

    /** Customer surfaces never expose AED amounts or the exchange rate, at any depth. */
    private function assertNoInternalCurrency(array $data, string $where): void
    {
        array_walk_recursive($data, function ($v, $k) use ($where) {
            $this->assertDoesNotMatchRegularExpression('/(_aed$|^rate|exchange)/i', (string) $k, "{$where} exposes '{$k}'");
        });
    }

    public function testCatalogContract(): void
    {
        $list = $this->getJson('/api/v1/products?per_page=5');
        $this->assertNotEmpty($list);
        $this->assertKeys(self::PRODUCT_LIST_ITEM, $list[0], 'GET /products[0]');
        $this->assertNoInternalCurrency($list, 'GET /products');

        $detail = $this->getJson('/api/v1/products/' . $list[0]['slug']);
        $this->assertKeys(self::PRODUCT_DETAIL, $detail, 'GET /products/:slug');
        $this->assertNoInternalCurrency($detail, 'GET /products/:slug');

        $shop = $this->getJson('/api/v1/shop');
        $this->assertKeys(self::SHOP, $shop, 'GET /shop');
        $this->assertNoInternalCurrency($shop, 'GET /shop');
    }

    public function testOrderContractsForCustomerAndAdmin(): void
    {
        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->customerToken, 'Content-Type' => 'application/json'])
            ->withBody(json_encode([
                'order_type' => 'delivery', 'shipping_zone_id' => 1, 'delivery_address' => 'الخرطوم، شارع الستين، عمارة 12',
                'contact_phone' => '0912345678', 'items' => [['product_id' => 6, 'quantity' => 1]],
            ]))->post('/api/v1/orders');
        $res->assertStatus(201);
        $created = json_decode($res->getJSON(), true)['data'];
        $number = $created['order_number'];

        $order = $this->getJson("/api/v1/orders/{$number}", $this->customerToken);
        $this->assertKeys(self::ORDER_DETAIL, $order, 'GET /orders/:n');
        $this->assertKeys(self::ORDER_ITEM, $order['items'][0], 'GET /orders/:n items[0]');
        $this->assertSame('delivery', $order['delivery_type']);
        $this->assertNotEmpty($order['shipping_zone_name']);
        $this->assertNoInternalCurrency($order, 'GET /orders/:n');

        $list = $this->getJson('/api/v1/orders', $this->customerToken);
        $this->assertKeys(self::ORDER_LIST_ITEM, $list[0], 'GET /orders[0]');
        $this->assertNoInternalCurrency($list, 'GET /orders');

        $admin = $this->getJson("/api/v1/admin/orders/{$number}", $this->adminToken);
        $this->assertKeys(self::ADMIN_ORDER, $admin, 'GET /admin/orders/:n');
        $this->assertSame('الخرطوم، شارع الستين، عمارة 12', $admin['delivery_address']);
        $this->assertNotEmpty($admin['shipping_zone_name']);
    }

    public function testAdminProductListCarriesBrandName(): void
    {
        $list = $this->getJson('/api/v1/admin/products?per_page=50', $this->adminToken);
        $withBrand = array_values(array_filter($list, static fn ($p) => !empty($p['brand_id'])));
        $this->assertNotEmpty($withBrand, 'seed should contain branded products');
        $this->assertKeys(self::ADMIN_PRODUCT, $withBrand[0], 'GET /admin/products[0]');
        $this->assertNotEmpty($withBrand[0]['brand_name']);
    }
}
