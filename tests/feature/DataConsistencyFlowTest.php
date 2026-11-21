<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\Order;
use App\Entities\User;
use App\Models\OrderEventModel;
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
final class DataConsistencyFlowTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected User $adminUser;
    protected User $customerUser;
    protected string $adminToken;
    protected string $customerToken;

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

        $customer = $userModel->where('email', 'consistency_cust@example.com')->first();
        if (!$customer) {
            $userModel->insert(new User([
                'name'      => 'عثمان أحمد',
                'email'     => 'consistency_cust@example.com',
                'phone'     => '+249912345678',
                'role'      => Wex::ROLE_CUSTOMER,
                'is_active' => true,
            ]));
            $customer = $userModel->where('email', 'consistency_cust@example.com')->first();
        }
        $this->customerUser  = $customer;
        $this->customerToken = $tokenService->generateAccessToken($customer)['access_token'];
    }

    protected function createTestOrder(string $status = 'pending_payment'): Order
    {
        $orderModel = new OrderModel();
        $orderNumber = 'WEX-CONS-' . bin2hex(random_bytes(4));
        $now = time();

        $id = $orderModel->insert([
            'order_number' => $orderNumber,
            'user_id' => $this->customerUser->id,
            'status' => $status,
            'delivery_type' => 'pickup',
            'delivery_phone' => '+249912345678',
            'rate_sdg_snapshot' => 650,
            'app_discount_percent_snapshot' => 0,
            'subtotal_aed' => 50,
            'subtotal_sdg' => 32500,
            'discount_sdg' => 0,
            'delivery_fee_sdg' => 0,
            'total_sdg' => 32500,
            'lock_expires_at' => gmdate('Y-m-d H:i:s', $now + 7200),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        return $orderModel->find($id);
    }

    public function testAdminOrderDetailIncludesCustomerBlockAndProofsAndPaymentBlock(): void
    {
        $order = $this->createTestOrder('payment_review');
        $proofModel = new PaymentProofModel();
        $proofModel->insert([
            'order_id' => $order->id,
            'file_path' => 'uploads/proofs/test_consistency.jpg',
            'status' => 'pending',
            'sender_name' => 'عثمان أحمد',
            'reference_number' => 'REF-1234',
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get("/api/v1/admin/orders/{$order->order_number}");

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $data = $body['data'];

        // Assert customer block exists with name and phone
        $this->assertArrayHasKey('customer', $data, 'Admin order detail must contain customer block');
        $this->assertSame('عثمان أحمد', $data['customer']['name']);
        $this->assertSame('+249912345678', $data['customer']['phone']);

        // Assert proofs array exists
        $this->assertArrayHasKey('proofs', $data, 'Admin order detail must contain proofs array');
        $this->assertCount(1, $data['proofs']);
        $this->assertSame('pending', $data['proofs'][0]['status']);

        // Assert payment block exists as single source of truth
        $this->assertArrayHasKey('payment', $data, 'Admin order detail must contain payment block');
        $this->assertSame('under_review', $data['payment']['state']);
        $this->assertTrue($data['payment']['can_review']);
        $this->assertFalse($data['payment']['can_upload']);
        $this->assertSame(1, $data['payment']['proofs_count']);
        $this->assertNotNull($data['payment']['latest_proof']);
    }

    public function testCustomerOrderIncludesPaymentBlock(): void
    {
        $order = $this->createTestOrder('pending_payment');

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->get("/api/v1/orders/{$order->order_number}");

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $data = $body['data'];

        $this->assertArrayHasKey('payment', $data, 'Customer order detail must contain payment block');
        $this->assertSame('awaiting_proof', $data['payment']['state']);
        $this->assertTrue($data['payment']['can_upload']);
        $this->assertFalse($data['payment']['can_review']);
        $this->assertSame(0, $data['payment']['proofs_count']);
    }

    public function testOrderEventsDoNotContainUtcString(): void
    {
        $order = $this->createTestOrder('pending_payment');

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get("/api/v1/admin/orders/{$order->order_number}");

        $body = json_decode($result->getJSON(), true);
        $timeline = $body['data']['timeline'] ?? [];

        foreach ($timeline as $event) {
            $this->assertStringNotContainsString('UTC', $event['note'] ?? '');
        }
    }
}
