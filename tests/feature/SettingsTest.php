<?php

namespace Tests\Feature;

use App\Database\Seeds\AdminSeeder;
use App\Database\Seeds\SettingsSeeder;
use App\Database\Seeds\ShippingZonesSeeder;
use App\Entities\User;
use App\Models\AuditLogModel;
use App\Models\ExchangeRateModel;
use App\Models\SettingModel;
use App\Models\ShippingZoneModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * @internal
 */
final class SettingsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace = 'App';
    protected $migrateOnce = true;
    protected $refresh = false;
    protected $seed = \App\Database\Seeds\DatabaseSeeder::class;

    protected User $adminUser;
    protected User $customerUser;
    protected string $adminToken;
    protected string $customerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $tokenService = new TokenService();
        $userModel    = new UserModel();

        // Find or create admin
        $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        if (!$admin) {
            \Config\Database::seeder()->call(\App\Database\Seeds\AdminSeeder::class);
            $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        }
        $this->adminUser  = $admin;
        $this->adminToken = $tokenService->generateAccessToken($admin)['access_token'];

        // Find or create customer
        $customer = $userModel->where('email', 'customer_settings@example.com')->first();
        if (!$customer) {
            $userModel->insert(new User([
                'name'      => 'زبون تجريبي',
                'email'     => 'customer_settings@example.com',
                'role'      => 'customer',
                'is_active' => true,
            ]));
            $customer = $userModel->where('email', 'customer_settings@example.com')->first();
        }
        $this->customerUser  = $customer;
        $this->customerToken = $tokenService->generateAccessToken($customer)['access_token'];
    }

    public function testCustomerCannotAccessAdminSettings(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->get('/api/v1/admin/settings');

        $result->assertStatus(403);
        $json = json_decode($result->getJSON(), true);
        $this->assertFalse($json['success']);
    }

    public function testAdminCanGetSettings(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('/api/v1/admin/settings');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('exchange_rate_sdg', $json['data']);
        $this->assertArrayHasKey('lock_hours', $json['data']);
        $this->assertArrayHasKey('shop_name', $json['data']);
    }

    public function testAdminCanUpdateSettings(): void
    {
        $payload = [
            'shop_phone' => '+249 912 345 678',
            'lock_hours' => 6,
        ];

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->patch('/api/v1/admin/settings');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertSame('+249 912 345 678', $json['data']['shop_phone']);
        $this->assertSame(6, $json['data']['lock_hours']);

        // Verify in DB
        $settingModel = new SettingModel();
        $phoneRow = $settingModel->where('key', 'shop_phone')->first();
        $this->assertSame('+249 912 345 678', $phoneRow->value);

        $lockRow = $settingModel->where('key', 'lock_hours')->first();
        $this->assertSame('6', $lockRow->value);
    }

    public function testAdminCanUpdateExchangeRateAndAuditHistory(): void
    {
        $payload = [
            'rate_sdg' => 750,
        ];

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->post('/api/v1/admin/rates');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertSame(750, $json['data']['rate_sdg']);
        $this->assertNotEmpty($json['data']['updated_at']);

        // Check setting updated
        $settingModel = new SettingModel();
        $rateRow = $settingModel->where('key', 'exchange_rate_sdg')->first();
        $this->assertSame('750', $rateRow->value);

        // Check exchange_rates table contains entry
        $rateModel = new ExchangeRateModel();
        $latest = $rateModel->orderBy('id', 'DESC')->first();
        $this->assertNotNull($latest);
        $this->assertSame(750, (int) $latest->rate_sdg);
        $this->assertSame((int) $this->adminUser->id, (int) $latest->changed_by);

        // Check audit log recorded
        $auditModel = new AuditLogModel();
        $audit = $auditModel->where('action', 'rate_updated')->orderBy('id', 'DESC')->first();
        $this->assertNotNull($audit);
        $this->assertSame((int) $this->adminUser->id, (int) $audit->user_id);

        // Check listing history endpoint
        $historyResult = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('/api/v1/admin/rates?page=1&per_page=10');

        $historyResult->assertStatus(200);
        $historyJson = json_decode($historyResult->getJSON(), true);
        $this->assertTrue($historyJson['success']);
        $this->assertNotEmpty($historyJson['data']);
        $this->assertSame(750, $historyJson['data'][0]['rate_sdg']);
        $this->assertNotNull($historyJson['meta']);
    }

    public function testAdminShippingZonesCrud(): void
    {
        // 1. Admin lists shipping zones
        $listResult = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('/api/v1/admin/shipping-zones');

        $listResult->assertStatus(200);
        $listJson = json_decode($listResult->getJSON(), true);
        $this->assertTrue($listJson['success']);
        $this->assertGreaterThanOrEqual(18, count($listJson['data']));

        // 2. Admin creates a new test zone
        $uniqueName = 'منطقة خاصة ' . uniqid();
        $createPayload = [
            'state_name_ar' => $uniqueName,
            'fee_sdg'       => 15000,
            'is_active'     => true,
        ];

        $createResult = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($createPayload))->post('/api/v1/admin/shipping-zones');

        $createResult->assertStatus(201);
        $createJson = json_decode($createResult->getJSON(), true);
        $this->assertTrue($createJson['success']);
        $zoneId = $createJson['data']['id'];
        $this->assertNotNull($zoneId);

        // 3. Admin updates zone
        $updatePayload = [
            'fee_sdg' => 18000,
        ];

        $updateResult = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($updatePayload))->patch("/api/v1/admin/shipping-zones/{$zoneId}");

        $updateResult->assertStatus(200);
        $updateJson = json_decode($updateResult->getJSON(), true);
        $this->assertSame(18000, $updateJson['data']['fee_sdg']);

        // 4. Admin deletes zone
        $deleteResult = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->delete("/api/v1/admin/shipping-zones/{$zoneId}");

        $deleteResult->assertStatus(200);

        // Check zone is removed
        $zoneModel = new ShippingZoneModel();
        $this->assertNull($zoneModel->find($zoneId));
    }

    // API-XSS-01: shop_map_link is rendered as href in the storefront footer.
    /**
     * Turning orders off from the admin panel (JSON false) stored '' which every reader took
     * as "on": the storefront kept selling and the API kept accepting orders.
     */
    public function testOrdersToggleOffReachesStorefrontAndBlocksOrders(): void
    {
        $patch = function (array $body) {
            return $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken])
                ->withBodyFormat('json')->patch('/api/v1/admin/settings', $body);
        };

        $patch(['orders_enabled' => false, 'ticker_enabled' => true])->assertStatus(200);
        $this->assertSame('0', (new SettingModel())->where('key', 'orders_enabled')->first()->value ?? null);

        $shop = json_decode($this->get('/api/v1/shop')->getJSON(), true)['data'];
        $this->assertFalse($shop['orders_enabled']);
        $this->assertTrue($shop['ticker_enabled']);

        $order = $this->withHeaders(['Authorization' => 'Bearer ' . $this->customerToken, 'Idempotency-Key' => 'settings-toggle-key-1'])
            ->withBodyFormat('json')->post('/api/v1/orders', ['order_type' => 'pickup', 'contact_phone' => '0912345678', 'items' => [['product_id' => 1, 'quantity' => 1]]]);
        $order->assertStatus(422);

        // Rows written as '' before the fix also read as off.
        $db = \Config\Database::connect();
        $db->table('settings')->where('key', 'orders_enabled')->update(['value' => '']);
        $shop = json_decode($this->get('/api/v1/shop')->getJSON(), true)['data'];
        $this->assertFalse($shop['orders_enabled']);

        // Every admin-editable storefront field must come back from /shop.
        $patch(['shop_map_link' => 'https://maps.google.com/?q=Test', 'footer_text' => 'نص التذييل', 'social_facebook' => 'https://facebook.com/wex', 'app_discount_percent' => 5, 'lock_hours' => 6])->assertStatus(200);
        $shop = json_decode($this->get('/api/v1/shop')->getJSON(), true)['data'];
        $this->assertSame('https://maps.google.com/?q=Test', $shop['shop_map_link']);
        $this->assertSame('نص التذييل', $shop['footer_text']);
        $this->assertSame('https://facebook.com/wex', $shop['social_facebook']);
        $this->assertSame(5, $shop['app_discount_percent']);
        $this->assertSame(6, $shop['lock_hours']);

        $patch(['orders_enabled' => true, 'ticker_enabled' => false])->assertStatus(200);
        $shop = json_decode($this->get('/api/v1/shop')->getJSON(), true)['data'];
        $this->assertTrue($shop['orders_enabled']);
        $this->assertFalse($shop['ticker_enabled']);
    }

    public function testShopMapLinkRejectsJavascriptUrl(): void
    {
        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['shop_map_link' => 'javascript:alert(1)']))->patch('/api/v1/admin/settings');

        $res->assertStatus(422);
    }
}
