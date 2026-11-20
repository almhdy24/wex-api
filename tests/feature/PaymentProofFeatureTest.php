<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\Order;
use App\Entities\User;
use App\Models\OrderModel;
use App\Models\PaymentProofModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * @internal
 */
final class PaymentProofFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected User $adminUser;
    protected User $customerUser;
    protected User $otherCustomer;
    protected string $adminToken;
    protected string $customerToken;
    protected string $otherCustomerToken;

    protected function setUp(): void
    {
        parent::setUp();
        $_FILES = [];
        $_POST  = [];
        if (function_exists('service')) {
            service('superglobals')->setFilesArray([]);
            \Config\Services::resetSingle('superglobals');
            \Config\Services::resetSingle('request');
            \Config\Services::resetSingle('incomingrequest');
        }

        $tokenService = new TokenService();
        $userModel    = new UserModel();

        $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        $this->adminUser  = $admin;
        $this->adminToken = $tokenService->generateAccessToken($admin)['access_token'];

        $customer = $userModel->where('email', 'proof_cust@example.com')->first();
        if (!$customer) {
            $userModel->insert(new User([
                'name'      => 'أحمد التجاني',
                'email'     => 'proof_cust@example.com',
                'role'      => Wex::ROLE_CUSTOMER,
                'is_active' => true,
            ]));
            $customer = $userModel->where('email', 'proof_cust@example.com')->first();
        }
        $this->customerUser  = $customer;
        $this->customerToken = $tokenService->generateAccessToken($customer)['access_token'];

        $other = $userModel->where('email', 'proof_other@example.com')->first();
        if (!$other) {
            $userModel->insert(new User([
                'name'      => 'خالد عثمان',
                'email'     => 'proof_other@example.com',
                'role'      => Wex::ROLE_CUSTOMER,
                'is_active' => true,
            ]));
            $other = $userModel->where('email', 'proof_other@example.com')->first();
        }
        $this->otherCustomer      = $other;
        $this->otherCustomerToken = $tokenService->generateAccessToken($other)['access_token'];
    }

    protected function tearDown(): void
    {
        $_FILES = [];
        $_POST  = [];
        if (function_exists('service')) {
            service('superglobals')->setFilesArray([]);
            \Config\Services::resetSingle('superglobals');
            \Config\Services::resetSingle('request');
            \Config\Services::resetSingle('incomingrequest');
        }
        parent::tearDown();
    }

    protected function createOrder(int $userId, string $status = 'pending_payment', int $lockOffset = 3600): Order
    {
        $orderModel = new OrderModel();
        $orderNumber = 'WEX-TST-' . bin2hex(random_bytes(4));
        $now = time();
        $lockTime = gmdate('Y-m-d H:i:s', $now + $lockOffset);

        $id = $orderModel->insert([
            'order_number' => $orderNumber,
            'user_id' => $userId,
            'status' => $status,
            'delivery_type' => 'pickup',
            'delivery_phone' => '0912345678',
            'rate_sdg_snapshot' => 2950,
            'app_discount_percent_snapshot' => 0,
            'subtotal_aed' => 500,
            'subtotal_sdg' => 1475000,
            'discount_sdg' => 0,
            'delivery_fee_sdg' => 0,
            'total_sdg' => 1475000,
            'lock_expires_at' => $lockTime,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $orderModel->find($id);
    }

    public function testMissingProofImageFieldReturns422(): void
    {
        $order = $this->createOrder((int) $this->customerUser->id);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->post("/api/v1/orders/{$order->order_number}/payment-proof");

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
        $this->assertSame('يرجى إرفاق صورة إشعار التحويل البنكي.', $body['message']);
    }

    public function testNonOwnerAccessReturns403(): void
    {
        $order = $this->createOrder((int) $this->customerUser->id);

        // Try accessing as otherCustomer
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->otherCustomerToken,
        ])->post("/api/v1/orders/{$order->order_number}/payment-proof");

        $result->assertStatus(403);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('لا تملك صلاحية الوصول إلى هذا الطلب', $body['message']);
    }

    public function testExpiredOrderUploadReturns409(): void
    {
        $order = $this->createOrder((int) $this->customerUser->id, 'expired', -3600);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->post("/api/v1/orders/{$order->order_number}/payment-proof");

        $result->assertStatus(409);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
        $this->assertStringContainsString('انتهت مهلة تثبيت السعر لهذا الطلب', $body['message']);
    }

    public function testCorsPreflightReturns204(): void
    {
        $result = $this->withHeaders([
            'Origin' => 'http://localhost:5173',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'authorization,content-type',
        ])->options('/api/v1/orders/WEX-TEST/payment-proof');

        $result->assertStatus(204);
        $result->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');
        $result->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
