<?php

namespace Tests\Feature;

use App\Database\Seeds\AdminSeeder;
use App\Database\Seeds\DatabaseSeeder;
use App\Entities\User;
use App\Models\OrderModel;
use App\Models\UserModel;
use App\Services\SettingService;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use Config\Wex;

/**
 * @internal
 */
final class RecentPurchasesTest extends CIUnitTestCase
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

    public function testTickerDisabledReturnsEmptyArray(): void
    {
        $settings = new SettingService();
        $settings->set('ticker_enabled', '0');

        $res = $this->get('/api/v1/recent-purchases');
        $res->assertStatus(200);
        $body = json_decode($res->getJSON(), true);

        $this->assertTrue($body['success']);
        $this->assertSame([], $body['data']);
    }

    public function testRecentPurchasesAnonymizationAndHideToggle(): void
    {
        $db = Database::connect();
        $settings = new SettingService();
        $settings->set('ticker_enabled', '1');

        $now = gmdate('Y-m-d H:i:s');
        $futureNow = gmdate('Y-m-d H:i:s', time() + 7200);
        $orderNumber = 'WEX-TST-' . time();

        // Insert a completed order with newest timestamp so it is #1 in limit 8
        $db->table('orders')->insert([
            'order_number'                  => $orderNumber,
            'user_id'                       => $this->adminUser->id,
            'status'                        => Wex::ORDER_STATUS_COMPLETED,
            'delivery_type'                 => Wex::DELIVERY_TYPE_PICKUP,
            'delivery_phone'                => '0912345678',
            'rate_sdg_snapshot'             => 750,
            'app_discount_percent_snapshot' => 0,
            'subtotal_aed'                  => 1000,
            'subtotal_sdg'                  => 750000,
            'discount_sdg'                  => 0,
            'delivery_fee_sdg'              => 0,
            'total_sdg'                     => 750000,
            'lock_expires_at'               => gmdate('Y-m-d H:i:s', strtotime('+4 hours')),
            'ticker_hidden'                 => 0,
            'created_at'                    => $futureNow,
            'updated_at'                    => $futureNow,
        ]);
        $orderId = (int) $db->insertID();

        // Insert product item
        $db->table('order_items')->insert([
            'order_id'              => $orderId,
            'product_id'            => 1,
            'product_name_snapshot' => 'Lenovo ThinkPad T480 - Core i5',
            'product_type_snapshot' => 'laptop',
            'unit_price_aed'        => 1000,
            'quantity'              => 1,
            'line_total_aed'        => 1000,
        ]);

        // 1. Fetch public ticker
        $res = $this->get('/api/v1/recent-purchases');
        $res->assertStatus(200);
        $body = json_decode($res->getJSON(), true);

        $this->assertTrue($body['success']);
        $this->assertNotEmpty($body['data']);

        // Check anonymization across all ticker items:
        foreach ($body['data'] as $item) {
            $this->assertArrayHasKey('product_name', $item);
            $this->assertArrayHasKey('thumbnail', $item);
            $this->assertArrayHasKey('purchased_at', $item);

            // STRICT ANONYMIZATION CHECKS: Absolutely NO personal or sensitive order identifiers
            $this->assertArrayNotHasKey('order_number', $item);
            $this->assertArrayNotHasKey('customer_name', $item);
            $this->assertArrayNotHasKey('customer_phone', $item);
            $this->assertArrayNotHasKey('contact_phone', $item);
            $this->assertArrayNotHasKey('delivery_phone', $item);
            $this->assertArrayNotHasKey('user_id', $item);
            $this->assertArrayNotHasKey('total_sdg', $item);
        }

        $foundBefore = array_filter($body['data'], static fn($item) => $item['product_name'] === 'Lenovo ThinkPad T480 - Core i5');
        $this->assertNotEmpty($foundBefore);

        // 2. Admin toggle-hide
        $toggleRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->patch("/api/v1/admin/recent-purchases/{$orderId}/toggle-hide");
        $toggleRes->assertStatus(200);
        $toggleBody = json_decode($toggleRes->getJSON(), true);
        $this->assertTrue($toggleBody['data']['ticker_hidden']);

        // 3. Public ticker should no longer include this hidden order
        $afterRes = $this->get('/api/v1/recent-purchases');
        $afterBody = json_decode($afterRes->getJSON(), true);
        $matched = array_filter($afterBody['data'], static fn($item) => $item['product_name'] === 'Lenovo ThinkPad T480 - Core i5');
        $this->assertEmpty($matched);
    }

    // CON-01: the admin page sent {enabled:true}; the API silently stored "disabled".
    public function testTickerSettingsRequiresTickerEnabledField(): void
    {
        $admin = (new \App\Models\UserModel())->where('email', 'admin@wextechnology.com')->first();
        $token = (new \App\Services\TokenService())->generateAccessToken($admin)['access_token'];
        $h     = ['Authorization' => 'Bearer ' . $token, 'Content-Type' => 'application/json'];

        $this->withHeaders($h)->withBody(json_encode(['enabled' => true]))
            ->patch('/api/v1/admin/recent-purchases/settings')->assertStatus(422);

        $ok = $this->withHeaders($h)->withBody(json_encode(['ticker_enabled' => true]))
            ->patch('/api/v1/admin/recent-purchases/settings');
        $ok->assertStatus(200);
        $this->assertTrue(json_decode($ok->getJSON(), true)['data']['ticker_enabled']);
        $this->assertSame('1', (string) (new \App\Services\SettingService())->get('ticker_enabled'));
    }
}
