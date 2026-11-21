<?php

namespace Tests\Unit;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\Order;
use App\Models\OrderEventModel;
use App\Models\OrderModel;
use App\Models\PaymentProofModel;
use App\Models\UserModel;
use App\Services\IntegrityService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\Wex;

/**
 * @internal
 */
final class IntegrityCheckTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected IntegrityService $service;
    protected OrderModel $orderModel;
    protected PaymentProofModel $proofModel;
    protected OrderEventModel $eventModel;

    protected array $createdOrderIds = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->service    = new IntegrityService();
        $this->orderModel = new OrderModel();
        $this->proofModel = new PaymentProofModel();
        $this->eventModel = new OrderEventModel();
    }

    protected function tearDown(): void
    {
        if (!empty($this->createdOrderIds)) {
            $this->orderModel->whereIn('id', $this->createdOrderIds)->delete();
            $this->eventModel->whereIn('order_id', $this->createdOrderIds)->delete();
            $this->createdOrderIds = [];
        }
        parent::tearDown();
    }

    public function testDetectsOrphanPaymentReviewOrder(): void
    {
        $orderNumber = 'WEX-INT-ORPHAN-' . bin2hex(random_bytes(3));
        $now = time();

        $orderId = $this->orderModel->insert([
            'order_number' => $orderNumber,
            'user_id' => 1,
            'status' => Wex::ORDER_STATUS_PAYMENT_REVIEW,
            'delivery_type' => 'pickup',
            'delivery_phone' => '+249912345678',
            'rate_sdg_snapshot' => 650,
            'app_discount_percent_snapshot' => 0,
            'subtotal_aed' => 50,
            'subtotal_sdg' => 32500,
            'discount_sdg' => 0,
            'delivery_fee_sdg' => 0,
            'total_sdg' => 32500,
            'lock_expires_at' => gmdate('Y-m-d H:i:s', $now + 3600),
            'created_at' => gmdate('Y-m-d H:i:s'),
        ]);
        $this->createdOrderIds[] = $orderId;

        $report = $this->service->checkIntegrity();
        $orphans = $report['violations']['payment_review_without_pending_proof'];

        $found = false;
        foreach ($orphans as $o) {
            if ($o['order_number'] === $orderNumber) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Integrity check must detect orphan payment_review order');

        // Test repair dry run
        $dryResult = $this->service->repairIntegrity(dryRun: true);
        $this->assertGreaterThanOrEqual(1, $dryResult['actions_count']);
        $this->assertSame(Wex::ORDER_STATUS_PAYMENT_REVIEW, $this->orderModel->find($orderId)->status);

        // Test repair apply
        $applyResult = $this->service->repairIntegrity(dryRun: false);
        $this->assertGreaterThanOrEqual(1, $applyResult['actions_count']);
        $this->assertSame(Wex::ORDER_STATUS_PENDING_PAYMENT, $this->orderModel->find($orderId)->status);
    }

    public function testDetectsOverduePendingPaymentOrder(): void
    {
        $orderNumber = 'WEX-INT-OVERDUE-' . bin2hex(random_bytes(3));
        $now = time();

        $orderId = $this->orderModel->insert([
            'order_number' => $orderNumber,
            'user_id' => 1,
            'status' => Wex::ORDER_STATUS_PENDING_PAYMENT,
            'delivery_type' => 'pickup',
            'delivery_phone' => '+249912345678',
            'rate_sdg_snapshot' => 650,
            'app_discount_percent_snapshot' => 0,
            'subtotal_aed' => 50,
            'subtotal_sdg' => 32500,
            'discount_sdg' => 0,
            'delivery_fee_sdg' => 0,
            'total_sdg' => 32500,
            'lock_expires_at' => gmdate('Y-m-d H:i:s', $now - 3600), // past
            'created_at' => gmdate('Y-m-d H:i:s', $now - 7200),
        ]);
        $this->createdOrderIds[] = $orderId;

        $report = $this->service->checkIntegrity();
        $overdue = $report['violations']['pending_payment_past_lock'];

        $found = false;
        foreach ($overdue as $o) {
            if ($o['order_number'] === $orderNumber) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Integrity check must detect overdue pending_payment order');

        // Apply repair
        $this->service->repairIntegrity(dryRun: false);
        $this->assertSame(Wex::ORDER_STATUS_EXPIRED, $this->orderModel->find($orderId)->status);
    }

    public function testRefusesProductionRepairWithoutForce(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('لا يمكن تنفيذ الإصلاح في بيئة الإنتاج بدون استخدام الخيار --force');

        // Temporarily override environment check
        putenv('CI_ENVIRONMENT=production');
        $_ENV['CI_ENVIRONMENT'] = 'production';
        $_SERVER['CI_ENVIRONMENT'] = 'production';
        try {
            $this->service->repairIntegrity(dryRun: false, force: false);
        } finally {
            putenv('CI_ENVIRONMENT=testing');
            $_ENV['CI_ENVIRONMENT'] = 'testing';
            $_SERVER['CI_ENVIRONMENT'] = 'testing';
        }
    }
}
