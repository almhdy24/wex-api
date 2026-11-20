<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\User;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * @internal
 */
final class OrderTest extends CIUnitTestCase
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

        $tokenService = new TokenService();
        $userModel    = new UserModel();

        // Admin
        $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        $this->adminUser  = $admin;
        $this->adminToken = $tokenService->generateAccessToken($admin)['access_token'];

        // Customer
        $customer = $userModel->where('email', 'order_cust@example.com')->first();
        if (!$customer) {
            $userModel->insert(new User([
                'name'      => 'عمر الزبير',
                'email'     => 'order_cust@example.com',
                'role'      => Wex::ROLE_CUSTOMER,
                'is_active' => true,
            ]));
            $customer = $userModel->where('email', 'order_cust@example.com')->first();
        }
        $this->customerUser  = $customer;
        $this->customerToken = $tokenService->generateAccessToken($customer)['access_token'];

        // Other customer
        $other = $userModel->where('email', 'other_cust@example.com')->first();
        if (!$other) {
            $userModel->insert(new User([
                'name'      => 'طارق البشير',
                'email'     => 'other_cust@example.com',
                'role'      => Wex::ROLE_CUSTOMER,
                'is_active' => true,
            ]));
            $other = $userModel->where('email', 'other_cust@example.com')->first();
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

    public function testCreateOrderPickupWithAtomicStockReservation(): void
    {
        $productModel = new ProductModel();
        $product = $productModel->find(1);
        $initialStock = (int) $product->stock_quantity;
        $this->assertGreaterThanOrEqual(1, $initialStock);

        $payload = [
            'order_type'     => 'pickup',
            'contact_phone'  => '0912345678',
            'customer_notes' => 'سأحضر للمحل صباحاً',
            'items'          => [
                ['product_id' => 1, 'quantity' => 1],
            ],
        ];

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->post('/api/v1/orders');

        $result->assertStatus(201);
        $body = json_decode($result->getJSON(), true);

        $this->assertTrue($body['success']);
        $order = $body['data'];
        $this->assertNotEmpty($order['order_number']);
        $this->assertSame('pending_payment', $order['status']);
        $this->assertSame('pickup', $order['delivery_type']);
        $this->assertSame(0, $order['delivery_fee_sdg']);
        $this->assertGreaterThan(0, $order['total_sdg']);
        $this->assertGreaterThan(0, $order['seconds_remaining']);
        $this->assertArrayHasKey('bank_details', $order);
        $this->assertSame($order['total_sdg'], $order['bank_details']['amount_sdg']);

        // Check stock was decremented in database
        $updatedProduct = $productModel->find(1);
        $this->assertSame($initialStock - 1, (int) $updatedProduct->stock_quantity);
    }

    public function testInsufficientStockRejectsOrderWithConflict(): void
    {
        $payload = [
            'order_type'    => 'pickup',
            'contact_phone' => '0912345678',
            'items'         => [
                ['product_id' => 1, 'quantity' => 10], // quantity above available stock
            ],
        ];

        // Ensure stock is lower than 10
        $productModel = new ProductModel();
        $productModel->update(1, ['stock_quantity' => 2]);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->post('/api/v1/orders');

        $result->assertStatus(409);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
    }

    public function testCreateOrderDeliveryIncludesShippingFee(): void
    {
        $payload = [
            'order_type'       => 'delivery',
            'shipping_zone_id' => 1, // Khartoum (fee 5,000)
            'delivery_address' => 'الخرطوم، شارع الستين، عمارة 12',
            'contact_phone'    => '0912345678',
            'items'            => [
                ['product_id' => 9, 'quantity' => 1], // accessory
            ],
        ];

        $productModel = new ProductModel();
        $productModel->update(9, ['stock_quantity' => 10]);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->post('/api/v1/orders');

        $result->assertStatus(201);
        $body = json_decode($result->getJSON(), true);

        $this->assertTrue($body['success']);
        $this->assertSame(5000, $body['data']['delivery_fee_sdg']);
        $this->assertSame('delivery', $body['data']['delivery_type']);
    }

    public function testCustomerCanCancelPendingOrderAndStockIsRestored(): void
    {
        $productModel = new ProductModel();
        $productModel->update(2, ['stock_quantity' => 5]);

        // 1. Create order for product 2
        $payload = [
            'order_type'    => 'pickup',
            'contact_phone' => '0912345678',
            'items'         => [
                ['product_id' => 2, 'quantity' => 2],
            ],
        ];

        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->post('/api/v1/orders');

        $createRes->assertStatus(201);
        $order = json_decode($createRes->getJSON(), true)['data'];
        $orderNumber = $order['order_number'];

        // Stock decreased to 3
        $this->assertSame(3, (int) $productModel->find(2)->stock_quantity);

        // 2. Cancel order
        $cancelRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['reason' => 'تغيير رأي']))->post("/api/v1/orders/{$orderNumber}/cancel");

        $cancelRes->assertStatus(200);
        $cancelledOrder = json_decode($cancelRes->getJSON(), true)['data'];
        $this->assertSame('cancelled', $cancelledOrder['status']);

        // Stock restored to 5
        $this->assertSame(5, (int) $productModel->find(2)->stock_quantity);
    }

    public function testPaymentProofUploadAndAdminReviewWorkflow(): void
    {
        $productModel = new ProductModel();
        $productModel->update(3, ['stock_quantity' => 5]);

        // 1. Create order
        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'order_type'    => 'pickup',
            'contact_phone' => '0912345678',
            'items'         => [['product_id' => 3, 'quantity' => 1]],
        ]))->post('/api/v1/orders');

        $orderNumber = json_decode($createRes->getJSON(), true)['data']['order_number'];

        // 2. Upload dummy payment proof image
        $tmpImage = tempnam(sys_get_temp_dir(), 'proof_') . '.jpg';
        $img = imagecreatetruecolor(100, 100);
        imagejpeg($img, $tmpImage);
        imagedestroy($img);

        $filesArray = [
            'proof_image' => [
                'name'     => 'receipt.jpg',
                'type'     => 'image/jpeg',
                'size'     => filesize($tmpImage),
                'tmp_name' => $tmpImage,
                'error'    => UPLOAD_ERR_OK,
            ],
        ];
        $_FILES = $filesArray;
        service('superglobals')->setFilesArray($filesArray);
        \Config\Services::resetSingle('request');

        $uploadRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->post("/api/v1/orders/{$orderNumber}/payment-proof", [
            'sender_name'      => 'عمر الزبير',
            'reference_number' => 'MB-892104',
        ]);

        $uploadRes->assertStatus(201);
        $uploadJson = json_decode($uploadRes->getJSON(), true)['data'];
        $proofId = $uploadJson['proof_id'];
        $this->assertSame('payment_review', $uploadJson['order_status']);

        // 3. Authorized customer streams receipt image
        $streamRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->get("/api/v1/orders/{$orderNumber}/payment-proof/{$proofId}/image");

        $streamRes->assertStatus(200);

        // 4. Unauthorized other customer cannot stream image (403)
        $unauthStream = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->otherCustomerToken,
        ])->get("/api/v1/orders/{$orderNumber}/payment-proof/{$proofId}/image");

        $unauthStream->assertStatus(403);

        // 5. Admin approves payment proof
        $reviewRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'action' => 'approve',
            'note'   => 'تم مطابقة التحويل في كشف حساب بنكك بنجاح',
        ]))->post("/api/v1/admin/orders/{$orderNumber}/proofs/{$proofId}/review");

        $reviewRes->assertStatus(200);
        $reviewedOrder = json_decode($reviewRes->getJSON(), true)['data'];
        $this->assertSame('confirmed', $reviewedOrder['status']);
    }

    public function testPriceLockLazyExpiryRestoresStock(): void
    {
        $productModel = new ProductModel();
        $productModel->update(4, ['stock_quantity' => 5]);

        // 1. Create order
        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'order_type'    => 'pickup',
            'contact_phone' => '0912345678',
            'items'         => [['product_id' => 4, 'quantity' => 2]],
        ]))->post('/api/v1/orders');

        $orderNumber = json_decode($createRes->getJSON(), true)['data']['order_number'];
        $this->assertSame(3, (int) $productModel->find(4)->stock_quantity);

        // 2. Artificially set lock_expires_at to 1 hour ago in DB
        $orderModel = new OrderModel();
        $orderModel->where('order_number', $orderNumber)->set([
            'lock_expires_at' => gmdate('Y-m-d H:i:s', time() - 3600),
        ])->update();

        // 3. View order -> triggers lazy expiry check
        $getRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->get("/api/v1/orders/{$orderNumber}");

        $getRes->assertStatus(200);
        $orderData = json_decode($getRes->getJSON(), true)['data'];
        $this->assertSame('expired', $orderData['status']);
        $this->assertSame(0, $orderData['seconds_remaining']);

        // Stock restored back to 5
        $this->assertSame(5, (int) $productModel->find(4)->stock_quantity);
    }

    public function testAdminCanUpdateOrderStatusThroughLifecycle(): void
    {
        $productModel = new ProductModel();
        $productModel->update(5, ['stock_quantity' => 5]);

        // 1. Create order
        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'order_type'    => 'pickup',
            'contact_phone' => '0912345678',
            'items'         => [['product_id' => 5, 'quantity' => 1]],
        ]))->post('/api/v1/orders');

        $orderNumber = json_decode($createRes->getJSON(), true)['data']['order_number'];

        // 2. Set to confirmed
        $orderModel = new OrderModel();
        $orderModel->where('order_number', $orderNumber)->set(['status' => 'confirmed'])->update();

        // 3. Admin updates status to ready_for_pickup
        $readyRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['status' => 'ready_for_pickup', 'note' => 'تم تجهيز الجهاز في المحل']))->patch("/api/v1/admin/orders/{$orderNumber}/status");

        $readyRes->assertStatus(200);
        $this->assertSame('ready_for_pickup', json_decode($readyRes->getJSON(), true)['data']['status']);

        // 4. Admin updates status to completed
        $doneRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['status' => 'completed', 'note' => 'استلم العميل الطلب بنجاح']))->patch("/api/v1/admin/orders/{$orderNumber}/status");

        $doneRes->assertStatus(200);
        $this->assertSame('completed', json_decode($doneRes->getJSON(), true)['data']['status']);
    }

    public function testAdminCanExtendPriceLock(): void
    {
        // 1. Create order
        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'order_type'    => 'pickup',
            'contact_phone' => '0912345678',
            'items'         => [['product_id' => 6, 'quantity' => 1]],
        ]))->post('/api/v1/orders');

        $orderData = json_decode($createRes->getJSON(), true)['data'];
        $orderNumber = $orderData['order_number'];
        $initialExpiry = strtotime($orderData['lock_expires_at']);

        // 2. Admin extends lock by 6 hours
        $extendRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['hours' => 6]))->post("/api/v1/admin/orders/{$orderNumber}/lock");

        $extendRes->assertStatus(200);
        $extendedOrder = json_decode($extendRes->getJSON(), true)['data'];
        $newExpiry = strtotime($extendedOrder['lock_expires_at']);

        $this->assertGreaterThan($initialExpiry, $newExpiry);
    }

    public function testCustomerCanReorderFromCancelledOrder(): void
    {
        $productModel = new ProductModel();
        $productModel->update(7, ['stock_quantity' => 5]);

        // 1. Create order
        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'order_type'    => 'pickup',
            'contact_phone' => '0912345678',
            'items'         => [['product_id' => 7, 'quantity' => 1]],
        ]))->post('/api/v1/orders');

        $orderNumber = json_decode($createRes->getJSON(), true)['data']['order_number'];

        // 2. Cancel order
        $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->post("/api/v1/orders/{$orderNumber}/cancel");

        // 3. Reorder
        $reorderRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->post("/api/v1/orders/{$orderNumber}/reorder");

        $reorderRes->assertStatus(201);
        $newOrder = json_decode($reorderRes->getJSON(), true)['data'];
        $this->assertNotSame($orderNumber, $newOrder['order_number']);
        $this->assertSame('pending_payment', $newOrder['status']);
        $this->assertCount(1, $newOrder['items']);
    }
}
