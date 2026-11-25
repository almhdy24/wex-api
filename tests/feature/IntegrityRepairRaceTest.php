<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\Order;
use App\Entities\User;
use App\Models\OrderModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Services\IntegrityService;
use App\Services\OrderService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Wex;

/**
 * API-INT-01: integrity repair wrote order status unconditionally, so a repair racing
 * an admin cancel (or lazy expiry) restored stock a second time.
 *
 * @internal
 */
final class IntegrityRepairRaceTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    public function testRepairDoesNotDoubleRestoreStockAfterConcurrentCancel(): void
    {
        $productId = 5;
        $products  = new ProductModel();
        $products->update($productId, ['stock_quantity' => 5, 'is_active' => 1]);

        $users = new UserModel();
        $email = 'integrity_race@example.com';
        if (!$users->where('email', $email)->first()) {
            $users->insert(new User(['name' => 'سباق', 'email' => $email, 'role' => Wex::ROLE_CUSTOMER, 'is_active' => true]));
        }
        $customer = $users->where('email', $email)->first();

        $service = new OrderService();
        $created = $service->createOrder((int) $customer->id, [
            'order_type'    => 'pickup',
            'contact_phone' => '0912345678',
            'items'         => [['product_id' => $productId, 'quantity' => 1]],
        ]);
        $this->assertSame(4, (int) $products->find($productId)->stock_quantity);

        $orders = new OrderModel();
        $order  = $orders->where('order_number', $created['order_number'])->first();
        // Orphan: payment_review, no pending proof, lock already expired.
        $orders->update($order->id, [
            'status'          => Wex::ORDER_STATUS_PAYMENT_REVIEW,
            'lock_expires_at' => gmdate('Y-m-d H:i:s', time() - 60),
        ]);
        $stale = $orders->find($order->id);

        // The repair job read the order; meanwhile an admin cancels it (stock -> 5).
        $service->updateOrderStatus($created['order_number'], Wex::ORDER_STATUS_CANCELLED, 1);
        $this->assertSame(5, (int) $products->find($productId)->stock_quantity);

        $staleOrders = new class ($stale) extends OrderModel {
            public function __construct(private Order $stale)
            {
                parent::__construct();
            }

            public function find($id = null)
            {
                return (int) $id === (int) $this->stale->id ? clone $this->stale : parent::find($id);
            }
        };

        $report = ['violations' => [
            'payment_review_without_pending_proof' => [['order_id' => (int) $order->id]],
            'pending_payment_past_lock'            => [],
            'pending_proof_not_in_payment_review'  => [],
        ]];
        $repair = new class ($staleOrders, $report) extends IntegrityService {
            public function __construct(OrderModel $orders, private array $fixedReport)
            {
                parent::__construct($orders);
            }

            public function checkIntegrity(): array
            {
                return $this->fixedReport;
            }
        };

        $repair->repairIntegrity(false, true);

        $this->assertSame(5, (int) $products->find($productId)->stock_quantity, 'stock must be restored exactly once');
        $this->assertSame(Wex::ORDER_STATUS_CANCELLED, $orders->find($order->id)->status);
    }
}
