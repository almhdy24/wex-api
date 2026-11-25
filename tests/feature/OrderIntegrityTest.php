<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\User;
use App\Exceptions\ConflictException;
use App\Models\AuditLogModel;
use App\Models\OrderModel;
use App\Models\PaymentProofModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Services\OrderService;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * Regression tests for review findings API-ORD-01..05 (order state integrity).
 *
 * @internal
 */
final class OrderIntegrityTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    private User $admin;
    private User $customer;
    private string $adminToken;
    private string $customerToken;
    private int $productId = 3;

    protected function setUp(): void
    {
        parent::setUp();
        $tokens = new TokenService();
        $users  = new UserModel();

        $this->admin      = $users->where('email', 'admin@wextechnology.com')->first();
        $this->adminToken = $tokens->generateAccessToken($this->admin)['access_token'];

        $email    = 'integrity_cust@example.com';
        $customer = $users->where('email', $email)->first();
        if (!$customer) {
            $users->insert(new User(['name' => 'عميل الاختبار', 'email' => $email, 'role' => Wex::ROLE_CUSTOMER, 'is_active' => true]));
            $customer = $users->where('email', $email)->first();
        }
        $this->customer      = $customer;
        $this->customerToken = $tokens->generateAccessToken($customer)['access_token'];

        (new ProductModel())->update($this->productId, ['stock_quantity' => 5, 'is_active' => 1]);
    }

    private function stock(): int
    {
        return (int) (new ProductModel())->find($this->productId)->stock_quantity;
    }

    private function createOrder(string $type = 'pickup'): string
    {
        $payload = [
            'order_type'    => $type,
            'contact_phone' => '0912345678',
            'items'         => [['product_id' => $this->productId, 'quantity' => 1]],
        ];
        if ($type === 'delivery') {
            $payload['shipping_zone_id'] = 1;
            $payload['delivery_address'] = 'الخرطوم، شارع الستين، عمارة 12';
        }

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->customerToken, 'Content-Type' => 'application/json'])
            ->withBody(json_encode($payload))->post('/api/v1/orders');
        $res->assertStatus(201);

        return json_decode($res->getJSON(), true)['data']['order_number'];
    }

    private function adminSetStatus(string $orderNumber, string $status)
    {
        return $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken, 'Content-Type' => 'application/json'])
            ->withBody(json_encode(['status' => $status]))
            ->patch("/api/v1/admin/orders/{$orderNumber}/status");
    }

    // API-ORD-01: a transition computed from a stale read must not restore stock twice.
    public function testConcurrentCancelDoesNotRestoreStockTwice(): void
    {
        $orderNumber = $this->createOrder();
        $this->assertSame(4, $this->stock());

        $staleOrder = (new OrderModel())->where('order_number', $orderNumber)->first();

        // Request A wins: order cancelled, stock back to 5.
        $this->adminSetStatus($orderNumber, Wex::ORDER_STATUS_CANCELLED)->assertStatus(200);
        $this->assertSame(5, $this->stock());

        // Request B read the order before A committed (still pending_payment).
        $staleModel = new class ($staleOrder) extends OrderModel {
            public function __construct(private object $stale)
            {
                parent::__construct();
            }

            public function first()
            {
                $this->builder()->resetQuery();

                return clone $this->stale;
            }
        };

        $service = new OrderService(orders: $staleModel);

        try {
            $service->updateOrderStatus($orderNumber, Wex::ORDER_STATUS_CANCELLED, (int) $this->admin->id);
            $this->fail('Stale concurrent cancel must be rejected');
        } catch (ConflictException) {
            // expected
        }

        $this->assertSame(5, $this->stock(), 'Stock must be restored exactly once');
    }

    // API-ORD-02: cancelling after "ready for pickup" must release the reserved unit.
    public function testCancelFromReadyForPickupRestoresStock(): void
    {
        $orderNumber = $this->createOrder();
        $this->assertSame(4, $this->stock());

        (new OrderModel())->where('order_number', $orderNumber)->set(['status' => Wex::ORDER_STATUS_READY_FOR_PICKUP])->update();

        $this->adminSetStatus($orderNumber, Wex::ORDER_STATUS_CANCELLED)->assertStatus(200);
        $this->assertSame(5, $this->stock());
    }

    // API-ORD-03: fulfilment status must match the delivery type.
    public function testPickupOrderCannotGoOutForDelivery(): void
    {
        $orderNumber = $this->createOrder('pickup');
        (new OrderModel())->where('order_number', $orderNumber)->set(['status' => Wex::ORDER_STATUS_CONFIRMED])->update();

        $this->adminSetStatus($orderNumber, Wex::ORDER_STATUS_OUT_FOR_DELIVERY)->assertStatus(409);
        $this->adminSetStatus($orderNumber, Wex::ORDER_STATUS_READY_FOR_PICKUP)->assertStatus(200);
    }

    // API-ORD-04: a pending proof on a cancelled order must not be approvable (resurrection/oversell).
    public function testCannotApproveProofOfCancelledOrder(): void
    {
        $orderNumber = $this->createOrder();
        $order = (new OrderModel())->where('order_number', $orderNumber)->first();

        $proofs  = new PaymentProofModel();
        $proofId = $proofs->insert([
            'order_id'   => $order->id,
            'file_path'  => 'uploads/proofs/none.jpg',
            'status'     => Wex::PROOF_STATUS_PENDING,
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        (new OrderModel())->update($order->id, ['status' => Wex::ORDER_STATUS_PAYMENT_REVIEW]);

        $this->adminSetStatus($orderNumber, Wex::ORDER_STATUS_CANCELLED)->assertStatus(200);
        $this->assertSame(5, $this->stock());

        $res = $this->withHeaders(['Authorization' => 'Bearer ' . $this->adminToken, 'Content-Type' => 'application/json'])
            ->withBody(json_encode(['action' => 'approve']))
            ->post("/api/v1/admin/orders/{$orderNumber}/proofs/{$proofId}/review");

        $res->assertStatus(409);
        $this->assertSame(Wex::ORDER_STATUS_CANCELLED, (new OrderModel())->find($order->id)->status);
    }

    // API-ORD-05: every admin order mutation leaves an audit entry with actor and diff.
    public function testAdminStatusChangeIsAudited(): void
    {
        $orderNumber = $this->createOrder();
        $order = (new OrderModel())->where('order_number', $orderNumber)->first();

        $this->adminSetStatus($orderNumber, Wex::ORDER_STATUS_CANCELLED)->assertStatus(200);

        $log = (new AuditLogModel())->where('action', 'order_status_changed')->where('entity_id', $order->id)->first();
        $this->assertNotNull($log);
        $this->assertSame((int) $this->admin->id, (int) $log->user_id);
        $details = is_string($log->details) ? json_decode($log->details, true) : (array) $log->details;
        $this->assertSame('pending_payment', $details['from']);
        $this->assertSame('cancelled', $details['to']);
    }
}
