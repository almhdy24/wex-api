<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\ProductModel;
use App\Models\SettingModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * HideRateTest
 *
 * Verifies that exchange rates and AED prices are strictly ADMIN-ONLY.
 * Public and customer responses must never leak AED keys, rates, or reference currency terms.
 * Admin endpoints must maintain AED and rate visibility.
 * Order line totals and order SDG immutability are verified.
 *
 * @internal
 */
final class HideRateTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected string $adminToken;
    protected string $customerToken;
    protected int $customerId;

    protected function setUp(): void
    {
        parent::setUp();

        $tokenService = new TokenService();
        $userModel    = new UserModel();

        // 1. Admin user
        $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        if (!$admin) {
            $userModel->insert([
                'name'      => 'مدير النظام',
                'email'     => 'admin@wextechnology.com',
                'role'      => 'admin',
                'is_active' => 1,
            ]);
            $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        }
        $this->adminToken = $tokenService->generateAccessToken($admin)['access_token'];

        // 2. Customer user
        $customer = $userModel->where('email', 'customer@example.com')->first();
        if (!$customer) {
            $this->customerId = (int) $userModel->insert([
                'email'         => 'customer@example.com',
                'name'          => 'محمد عثمان',
                'phone'         => '+249912345678',
                'role'          => 'customer',
                'is_active'     => 1,
                'created_at'    => gmdate('Y-m-d H:i:s'),
                'updated_at'    => gmdate('Y-m-d H:i:s'),
            ]);
            $customer = $userModel->find($this->customerId);
        } else {
            $this->customerId = (int) $customer->id;
        }

        $this->customerToken = $tokenService->generateAccessToken($customer)['access_token'];
    }

    /**
     * Recursively walks array / object to ensure NO forbidden keys or values exist.
     */
    private function assertNoCurrencyLeakage(mixed $data, string $context = ''): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $currentPath = $context ? "{$context}.{$key}" : (string) $key;

                // Forbidden key patterns: aed, rate, exchange, dirham (case-insensitive)
                $this->assertDoesNotMatchRegularExpression(
                    '/aed|rate|exchange|dirham/i',
                    (string) $key,
                    "Forbidden currency/rate key '{$key}' leaked in public/customer response at {$currentPath}"
                );

                $this->assertNoCurrencyLeakage($value, $currentPath);
            }
        } elseif (is_string($data)) {
            // Check forbidden substrings in customer-visible values
            $this->assertStringNotContainsStringIgnoringCase(
                'AED',
                $data,
                "Forbidden currency term 'AED' found in value at {$context}: '{$data}'"
            );
            $this->assertStringNotContainsString(
                'درهم',
                $data,
                "Forbidden currency term 'درهم' found in value at {$context}: '{$data}'"
            );
            $this->assertStringNotContainsString(
                'دولار',
                $data,
                "Forbidden currency term 'دولار' found in value at {$context}: '{$data}'"
            );
        }
    }

    // -----------------------------------------------------------------
    // 1. Public Endpoints Scanner Tests
    // -----------------------------------------------------------------

    public function testPublicShopDoesNotLeakRateOrAed(): void
    {
        $result = $this->get('api/v1/shop');
        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('prices_updated_at', $json['data']);
        $this->assertArrayHasKey('lock_hours', $json['data']);
        $this->assertArrayHasKey('app_discount_percent', $json['data']);

        $this->assertNoCurrencyLeakage($json['data'], 'shop');
    }

    public function testPublicCategoriesDoesNotLeakRateOrAed(): void
    {
        $result = $this->get('api/v1/categories');
        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertNoCurrencyLeakage($json['data'], 'categories');
    }

    public function testPublicBrandsDoesNotLeakRateOrAed(): void
    {
        $result = $this->get('api/v1/brands');
        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertNoCurrencyLeakage($json['data'], 'brands');
    }

    public function testPublicProductsListDoesNotLeakRateOrAed(): void
    {
        $result = $this->get('api/v1/products');
        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertNotEmpty($json['data']);

        $firstItem = $json['data'][0];
        $this->assertArrayHasKey('price_sdg', $firstItem);
        $this->assertArrayNotHasKey('price_aed', $firstItem);
        $this->assertArrayNotHasKey('rate_sdg', $firstItem);

        $this->assertNoCurrencyLeakage($json['data'], 'products_list');
    }

    public function testPublicProductDetailDoesNotLeakRateOrAed(): void
    {
        $product = (new ProductModel())->where('is_active', 1)->first();
        $this->assertNotNull($product);

        $result = $this->get("api/v1/products/{$product->slug}");
        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertArrayHasKey('price_sdg', $json['data']);
        $this->assertArrayNotHasKey('price_aed', $json['data']);
        $this->assertArrayNotHasKey('rate_sdg', $json['data']);

        $this->assertNoCurrencyLeakage($json['data'], 'product_detail');
    }

    public function testPublicShippingZonesDoesNotLeakRateOrAed(): void
    {
        $result = $this->get('api/v1/shipping-zones');
        $result->assertStatus(200);

        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['success']);
        $this->assertNoCurrencyLeakage($json['data'], 'shipping_zones');
    }

    // -----------------------------------------------------------------
    // 2. Customer Order Endpoints Scanner Tests
    // -----------------------------------------------------------------

    public function testCustomerOrderLifecycleDoesNotLeakRateOrAed(): void
    {
        $product = (new ProductModel())->where('stock_quantity >=', 2)->first();
        $this->assertNotNull($product);

        // A. Create Order
        $payload = [
            'order_type'    => 'pickup',
            'contact_phone' => '+249912345678',
            'items'         => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ];

        $createResult = $this->withHeaders([
            'Authorization' => "Bearer {$this->customerToken}",
        ])->post('api/v1/orders', $payload);

        $createResult->assertStatus(201);
        $createJson = json_decode($createResult->getJSON(), true);
        $this->assertTrue($createJson['success']);
        $orderData = $createJson['data'];
        $orderNumber = $orderData['order_number'];

        // Must have SDG fields and NOT AED/rate
        $this->assertArrayHasKey('subtotal_sdg', $orderData);
        $this->assertArrayHasKey('total_sdg', $orderData);
        $this->assertArrayNotHasKey('rate_sdg_snapshot', $orderData);
        $this->assertArrayNotHasKey('subtotal_aed', $orderData);

        // Verify items
        $this->assertNotEmpty($orderData['items']);
        $item = $orderData['items'][0];
        $this->assertArrayHasKey('unit_price_sdg', $item);
        $this->assertArrayHasKey('line_total_sdg', $item);
        $this->assertArrayNotHasKey('unit_price_aed', $item);
        $this->assertArrayNotHasKey('line_total_aed', $item);

        $this->assertNoCurrencyLeakage($orderData, 'order_create');

        // B. Get Order Detail
        $showResult = $this->withHeaders([
            'Authorization' => "Bearer {$this->customerToken}",
        ])->get("api/v1/orders/{$orderNumber}");

        $showResult->assertStatus(200);
        $showJson = json_decode($showResult->getJSON(), true);
        $this->assertNoCurrencyLeakage($showJson['data'], 'order_show');

        // C. List Customer Orders
        $listResult = $this->withHeaders([
            'Authorization' => "Bearer {$this->customerToken}",
        ])->get('api/v1/orders');

        $listResult->assertStatus(200);
        $listJson = json_decode($listResult->getJSON(), true);
        $this->assertNoCurrencyLeakage($listJson['data'], 'orders_list');

        // D. Cancel Order
        $cancelResult = $this->withHeaders([
            'Authorization' => "Bearer {$this->customerToken}",
        ])->post("api/v1/orders/{$orderNumber}/cancel", ['reason' => 'تجربة فحص']);

        $cancelResult->assertStatus(200);
        $cancelJson = json_decode($cancelResult->getJSON(), true);
        $this->assertNoCurrencyLeakage($cancelJson['data'], 'order_cancel');

        // E. Reorder
        $reorderResult = $this->withHeaders([
            'Authorization' => "Bearer {$this->customerToken}",
        ])->post("api/v1/orders/{$orderNumber}/reorder");

        $reorderResult->assertStatus(201);
        $reorderJson = json_decode($reorderResult->getJSON(), true);
        $this->assertNoCurrencyLeakage($reorderJson['data'], 'order_reorder');
    }

    public function testCustomerNotificationEndpointsDoNotLeakRateOrAed(): void
    {
        $result = $this->withHeaders([
            'Authorization' => "Bearer {$this->customerToken}",
        ])->get('api/v1/notifications');

        $result->assertStatus(200);
        $json = json_decode($result->getJSON(), true);
        $this->assertNoCurrencyLeakage($json['data'], 'notifications_list');

        $unreadResult = $this->withHeaders([
            'Authorization' => "Bearer {$this->customerToken}",
        ])->get('api/v1/notifications/unread-count');

        $unreadResult->assertStatus(200);
        $unreadJson = json_decode($unreadResult->getJSON(), true);
        $this->assertNoCurrencyLeakage($unreadJson['data'], 'notifications_unread');
    }

    // -----------------------------------------------------------------
    // 3. Admin Endpoints DO Contain AED and Rate
    // -----------------------------------------------------------------

    public function testAdminEndpointsDoContainAedAndRate(): void
    {
        // 1. Admin Dashboard
        $dashResult = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->get('api/v1/admin/dashboard');

        $dashResult->assertStatus(200);
        $dashJson = json_decode($dashResult->getJSON(), true);
        $this->assertArrayHasKey('exchange_rate', $dashJson['data']);
        $this->assertArrayHasKey('rate_sdg', $dashJson['data']['exchange_rate']);

        // 2. Admin Products list
        $prodListResult = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->get('api/v1/admin/products');

        $prodListResult->assertStatus(200);
        $prodListJson = json_decode($prodListResult->getJSON(), true);
        $firstAdminProd = $prodListJson['data'][0];
        $this->assertArrayHasKey('price_aed', $firstAdminProd);
        $this->assertArrayHasKey('price_sdg', $firstAdminProd);
        $this->assertArrayHasKey('rate_sdg', $firstAdminProd);

        // 3. Admin Single Product
        $prodId = $firstAdminProd['id'];
        $singleResult = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->get("api/v1/admin/products/{$prodId}");

        $singleResult->assertStatus(200);
        $singleJson = json_decode($singleResult->getJSON(), true);
        $this->assertArrayHasKey('price_aed', $singleJson['data']);
        $this->assertArrayHasKey('price_sdg', $singleJson['data']);
        $this->assertArrayHasKey('rate_sdg', $singleJson['data']);

        // 4. Admin Rates
        $ratesResult = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->get('api/v1/admin/rates');

        $ratesResult->assertStatus(200);
        $ratesJson = json_decode($ratesResult->getJSON(), true);
        $this->assertNotEmpty($ratesJson['data']);
        $this->assertArrayHasKey('rate_sdg', $ratesJson['data'][0]);
    }

    // -----------------------------------------------------------------
    // 4. Order Totals and Immutability Tests
    // -----------------------------------------------------------------

    public function testOrderTotalsEqualSumOfLineTotals(): void
    {
        $products = (new ProductModel())->where('stock_quantity >=', 3)->findAll(2);
        $this->assertCount(2, $products);

        $payload = [
            'order_type'    => 'pickup',
            'contact_phone' => '+249912345678',
            'items'         => [
                ['product_id' => $products[0]->id, 'quantity' => 2],
                ['product_id' => $products[1]->id, 'quantity' => 1],
            ],
        ];

        $res = $this->withHeaders([
            'Authorization' => "Bearer {$this->customerToken}",
        ])->post('api/v1/orders', $payload);

        $res->assertStatus(201);
        $order = json_decode($res->getJSON(), true)['data'];

        $calculatedSubtotal = 0;
        foreach ($order['items'] as $item) {
            $this->assertSame($item['unit_price_sdg'] * $item['quantity'], $item['line_total_sdg']);
            $calculatedSubtotal += $item['line_total_sdg'];
        }

        $this->assertSame($calculatedSubtotal, $order['subtotal_sdg'], 'Subtotal SDG must exactly equal sum of line totals');

        $expectedTotal = ($order['subtotal_sdg'] - $order['discount_sdg']) + $order['delivery_fee_sdg'];
        $this->assertSame($expectedTotal, $order['total_sdg'], 'Total SDG must equal subtotal - discount + delivery');
    }

    public function testRateChangeAfterOrderingDoesNotChangeExistingOrderSdgAmounts(): void
    {
        $product = (new ProductModel())->where('stock_quantity >=', 2)->first();

        // 1. Create Order at current rate (650)
        $res = $this->withHeaders([
            'Authorization' => "Bearer {$this->customerToken}",
        ])->post('api/v1/orders', [
            'order_type'    => 'pickup',
            'contact_phone' => '+249912345678',
            'items'         => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        $res->assertStatus(201);
        $originalOrder = json_decode($res->getJSON(), true)['data'];
        $orderNumber = $originalOrder['order_number'];

        $originalSubtotal = $originalOrder['subtotal_sdg'];
        $originalTotal    = $originalOrder['total_sdg'];
        $originalUnitPrice = $originalOrder['items'][0]['unit_price_sdg'];

        // 2. Admin changes exchange rate significantly (from 650 to 900)
        $rateRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->adminToken}",
        ])->post('api/v1/admin/rates', [
            'rate_sdg' => 900,
        ]);
        $rateRes->assertStatus(200);

        // 3. Customer fetches the existing order
        $fetchRes = $this->withHeaders([
            'Authorization' => "Bearer {$this->customerToken}",
        ])->get("api/v1/orders/{$orderNumber}");

        $fetchRes->assertStatus(200);
        $fetchedOrder = json_decode($fetchRes->getJSON(), true)['data'];

        // 4. Assert all SDG values are completely unchanged
        $this->assertSame($originalSubtotal, $fetchedOrder['subtotal_sdg']);
        $this->assertSame($originalTotal, $fetchedOrder['total_sdg']);
        $this->assertSame($originalUnitPrice, $fetchedOrder['items'][0]['unit_price_sdg']);
        $this->assertSame($originalOrder['items'][0]['line_total_sdg'], $fetchedOrder['items'][0]['line_total_sdg']);
    }

    // -----------------------------------------------------------------
    // 5. Email Templates Rendered Contain No AED/Rate Text
    // -----------------------------------------------------------------

    public function testEmailTemplatesContainNoAedOrRateText(): void
    {
        $templates = [
            'order_created'    => [
                'title'               => 'تم استلام طلبك بنجاح',
                'order_number'        => 'WEX-261120-0001',
                'delivery_type'       => 'pickup',
                'total_sdg'           => 650000,
                'lock_expires_at'     => '2026-11-20 18:00 UTC',
                'bank_account_name'   => 'Wex Technology',
                'bank_account_number' => '2849102',
            ],
            'lock_warning'     => [
                'title'               => 'تنبيه: اقتراب انتهاء فترة حجز السعر',
                'order_number'        => 'WEX-261120-0001',
                'lock_expires_at'     => '18:00 UTC',
                'total_sdg'           => 650000,
                'bank_account_number' => '2849102',
            ],
            'order_expired'    => [
                'title'        => 'انتهاء فترة حجز السعر للطلب',
                'order_number' => 'WEX-261120-0001',
            ],
            'payment_received' => [
                'title'        => 'تم استلام إشعار التحويل بنجاح',
                'order_number' => 'WEX-261120-0001',
            ],
            'payment_approved' => [
                'title'         => 'تم تأكيد دفع الطلب بنجاح',
                'order_number'  => 'WEX-261120-0001',
                'total_sdg'     => 650000,
                'delivery_type' => 'pickup',
            ],
            'payment_rejected' => [
                'title'        => 'تعذر تأكيد إشعار التحويل',
                'order_number' => 'WEX-261120-0001',
                'reason'       => 'المبلغ في الإشعار غير مطابق',
            ],
            'ready_for_pickup' => [
                'title'        => 'طلبك جاهز للاستلام من المحل',
                'order_number' => 'WEX-261120-0001',
                'shop_phone'   => '+249 912 345 678',
            ],
            'out_for_delivery' => [
                'title'            => 'طلبك في الطريق إليك مع المندوب',
                'order_number'     => 'WEX-261120-0001',
                'courier_name'     => 'أحمد مندوب',
                'courier_phone'    => '0912345678',
                'delivery_address' => 'الخرطوم - الرياض',
            ],
            'order_completed'  => [
                'title'        => 'تم تسليم طلبك بنجاح',
                'order_number' => 'WEX-261120-0001',
                'total_sdg'    => 650000,
            ],
        ];

        foreach ($templates as $templateName => $data) {
            $rendered = view("emails/{$templateName}", $data);

            $this->assertStringNotContainsStringIgnoringCase(
                'AED',
                $rendered,
                "Email template '{$templateName}' contains 'AED'"
            );
            $this->assertStringNotContainsString(
                'درهم',
                $rendered,
                "Email template '{$templateName}' contains 'درهم'"
            );
            $this->assertStringNotContainsString(
                'دولار',
                $rendered,
                "Email template '{$templateName}' contains 'دولار'"
            );
            $this->assertStringNotContainsString(
                'سعر الصرف',
                $rendered,
                "Email template '{$templateName}' contains 'سعر الصرف'"
            );
        }
    }
}
