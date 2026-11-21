<?php

namespace App\Services;

use App\Entities\OrderEvent;
use App\Models\OrderEventModel;
use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\PaymentProofModel;
use App\Models\ProductModel;
use Config\Database;
use Config\Wex;

/**
 * IntegrityService
 *
 * Scans, verifies, and safely repairs order and payment proof data consistency.
 */
class IntegrityService
{
    protected OrderModel $orders;
    protected PaymentProofModel $proofs;
    protected OrderEventModel $orderEvents;
    protected OrderItemModel $orderItems;
    protected ProductModel $products;
    protected OrderService $orderService;

    public function __construct(
        ?OrderModel $orders = null,
        ?PaymentProofModel $proofs = null,
        ?OrderEventModel $orderEvents = null,
        ?OrderItemModel $orderItems = null,
        ?ProductModel $products = null,
        ?OrderService $orderService = null
    ) {
        $this->orders       = $orders ?? new OrderModel();
        $this->proofs       = $proofs ?? new PaymentProofModel();
        $this->orderEvents  = $orderEvents ?? new OrderEventModel();
        $this->orderItems   = $orderItems ?? new OrderItemModel();
        $this->products     = $products ?? new ProductModel();
        $this->orderService = $orderService ?? new OrderService();
    }

    /**
     * Check all system consistency invariants.
     */
    public function checkIntegrity(): array
    {
        $db = Database::connect();
        $nowStr = gmdate('Y-m-d H:i:s');

        $violations = [
            'payment_review_without_pending_proof' => [],
            'pending_proof_not_in_payment_review'  => [],
            'proof_file_missing'                  => [],
            'confirmed_without_approved_proof'     => [],
            'approved_proof_unconfirmed_order'     => [],
            'pending_payment_past_lock'            => [],
            'order_events_inconsistent'            => [],
            'stock_mismatches'                     => [],
        ];

        // 1. payment_review orders without a pending proof
        $orphanOrders = $db->query("
            SELECT o.id, o.order_number, o.status, o.lock_expires_at
            FROM orders o
            WHERE o.status = 'payment_review'
              AND (
                  SELECT COUNT(*)
                  FROM payment_proofs p
                  WHERE p.order_id = o.id AND p.status = 'pending'
              ) = 0
        ")->getResultArray();

        foreach ($orphanOrders as $row) {
            $violations['payment_review_without_pending_proof'][] = [
                'order_id'        => (int) $row['id'],
                'order_number'    => $row['order_number'],
                'status'          => $row['status'],
                'lock_expires_at' => $row['lock_expires_at'],
                'message'         => "الطلب {$row['order_number']} بحالة مراجعة الدفع بدون إشعار معلق للمراجعة",
            ];
        }

        // 2. pending proofs on orders not in payment_review
        $mismatchedPendingProofs = $db->query("
            SELECT p.id as proof_id, p.order_id, p.status as proof_status, o.order_number, o.status as order_status
            FROM payment_proofs p
            JOIN orders o ON o.id = p.order_id
            WHERE p.status = 'pending'
              AND o.status != 'payment_review'
        ")->getResultArray();

        foreach ($mismatchedPendingProofs as $row) {
            $violations['pending_proof_not_in_payment_review'][] = [
                'proof_id'     => (int) $row['proof_id'],
                'order_number' => $row['order_number'],
                'order_status' => $row['order_status'],
                'message'      => "إشعار معلق #{$row['proof_id']} لطلب بحالة {$row['order_status']} بدلاً من payment_review",
            ];
        }

        // 3. proofs whose file is missing
        $allProofs = $this->proofs->findAll();
        foreach ($allProofs as $p) {
            $path = WRITEPATH . ltrim($p->file_path, '/');
            if (!file_exists($path) || !is_readable($path)) {
                $violations['proof_file_missing'][] = [
                    'proof_id'  => (int) $p->id,
                    'order_id'  => (int) $p->order_id,
                    'file_path' => $p->file_path,
                    'message'   => "ملف الإشعار #{$p->id} مفقود على المسار: {$p->file_path}",
                ];
            }
        }

        // 4. confirmed or later orders without an approved proof
        $confirmedStatuses = [
            Wex::ORDER_STATUS_CONFIRMED,
            'processing',
            Wex::ORDER_STATUS_READY_FOR_PICKUP,
            Wex::ORDER_STATUS_OUT_FOR_DELIVERY,
            Wex::ORDER_STATUS_COMPLETED,
            'delivered',
        ];
        $confirmedStatusSql = "'" . implode("','", $confirmedStatuses) . "'";

        $unprovenConfirmed = $db->query("
            SELECT o.id, o.order_number, o.status
            FROM orders o
            WHERE o.status IN ({$confirmedStatusSql})
              AND (
                  SELECT COUNT(*)
                  FROM payment_proofs p
                  WHERE p.order_id = o.id AND p.status = 'approved'
              ) = 0
        ")->getResultArray();

        foreach ($unprovenConfirmed as $row) {
            $violations['confirmed_without_approved_proof'][] = [
                'order_id'     => (int) $row['id'],
                'order_number' => $row['order_number'],
                'status'       => $row['status'],
                'message'      => "طلب مؤكد {$row['order_number']} بدون إشعار تحويل معتمد (approved)",
            ];
        }

        // 5. approved proofs on unconfirmed orders
        $rogueApprovedProofs = $db->query("
            SELECT p.id as proof_id, p.order_id, o.order_number, o.status as order_status
            FROM payment_proofs p
            JOIN orders o ON o.id = p.order_id
            WHERE p.status = 'approved'
              AND o.status NOT IN ({$confirmedStatusSql})
        ")->getResultArray();

        foreach ($rogueApprovedProofs as $row) {
            $violations['approved_proof_unconfirmed_order'][] = [
                'proof_id'     => (int) $row['proof_id'],
                'order_number' => $row['order_number'],
                'order_status' => $row['order_status'],
                'message'      => "إشعار معتمد #{$row['proof_id']} لطلب غير مؤكد ({$row['order_status']})",
            ];
        }

        // 6. orders pending_payment past lock_expires_at
        $pastLockOrders = $db->query("
            SELECT o.id, o.order_number, o.lock_expires_at
            FROM orders o
            WHERE o.status = 'pending_payment'
              AND o.lock_expires_at < '{$nowStr}'
        ")->getResultArray();

        foreach ($pastLockOrders as $row) {
            $violations['pending_payment_past_lock'][] = [
                'order_id'        => (int) $row['id'],
                'order_number'    => $row['order_number'],
                'lock_expires_at' => $row['lock_expires_at'],
                'message'         => "الطلب {$row['order_number']} في حالة انتظار الدفع بعد انتهاء مهلة التثبيت",
            ];
        }

        // 7. order_events inconsistent with current status
        $allOrders = $this->orders->findAll();
        foreach ($allOrders as $ord) {
            $latestEvent = $this->orderEvents
                ->where('order_id', $ord->id)
                ->orderBy('id', 'DESC')
                ->first();

            if (!$latestEvent) {
                $violations['order_events_inconsistent'][] = [
                    'order_id'     => (int) $ord->id,
                    'order_number' => $ord->order_number,
                    'message'      => "الطلب {$ord->order_number} ليس له أي سجل أحداث (timeline)",
                ];
            } elseif ($latestEvent->to_status !== $ord->status) {
                $violations['order_events_inconsistent'][] = [
                    'order_id'        => (int) $ord->id,
                    'order_number'    => $ord->order_number,
                    'order_status'    => $ord->status,
                    'event_to_status' => $latestEvent->to_status,
                    'message'         => "آخر حدث للطلب {$ord->order_number} حالته ({$latestEvent->to_status}) تختلف عن حالة الطلب الحالية ({$ord->status})",
                ];
            }
        }

        // Calculate total count
        $total = 0;
        foreach ($violations as $vList) {
            $total += count($vList);
        }

        return [
            'total_violations' => $total,
            'violations'       => $violations,
        ];
    }

    /**
     * Cheap query check for dashboard notification count.
     */
    public function getCheapInconsistencyCount(): int
    {
        $db = Database::connect();
        $nowStr = gmdate('Y-m-d H:i:s');

        // 1. payment_review with 0 pending proofs
        $c1 = (int) $db->query("
            SELECT COUNT(*) as cnt
            FROM orders o
            WHERE o.status = 'payment_review'
              AND (SELECT COUNT(*) FROM payment_proofs p WHERE p.order_id = o.id AND p.status = 'pending') = 0
        ")->getRow()->cnt;

        // 2. pending_payment past lock
        $c2 = (int) $db->query("
            SELECT COUNT(*) as cnt
            FROM orders o
            WHERE o.status = 'pending_payment'
              AND o.lock_expires_at < '{$nowStr}'
        ")->getRow()->cnt;

        // 3. pending proofs on non-review orders
        $c3 = (int) $db->query("
            SELECT COUNT(*) as cnt
            FROM payment_proofs p
            JOIN orders o ON o.id = p.order_id
            WHERE p.status = 'pending' AND o.status != 'payment_review'
        ")->getRow()->cnt;

        return $c1 + $c2 + $c3;
    }

    /**
     * Repair safe integrity violations.
     *
     * @param bool $dryRun
     * @param bool $force
     * @return array Log of repair actions
     */
    public function repairIntegrity(bool $dryRun = true, bool $force = false): array
    {
        $isProd = (defined('ENVIRONMENT') && ENVIRONMENT === 'production')
            || getenv('CI_ENVIRONMENT') === 'production'
            || ($_ENV['CI_ENVIRONMENT'] ?? '') === 'production'
            || ($_SERVER['CI_ENVIRONMENT'] ?? '') === 'production';

        if ($isProd && !$force) {
            throw new \RuntimeException('لا يمكن تنفيذ الإصلاح في بيئة الإنتاج بدون استخدام الخيار --force.');
        }

        $report = $this->checkIntegrity();
        $actions = [];
        $db = Database::connect();
        $nowStr = gmdate('Y-m-d H:i:s');
        $now = time();

        // Safe Repair 1: Orphan payment_review orders
        foreach ($report['violations']['payment_review_without_pending_proof'] as $orphan) {
            $order = $this->orders->find($orphan['order_id']);
            if (!$order) {
                continue;
            }

            $expiresAt = strtotime($order->lock_expires_at);
            if ($expiresAt > $now) {
                $targetStatus = Wex::ORDER_STATUS_PENDING_PAYMENT;
                $actionDesc = "إعادة الطلب {$order->order_number} إلى انتظار التحويل (pending_payment) لسريان مهلة الحجز";
                if (!$dryRun) {
                    $this->orders->update($order->id, [
                        'status'     => $targetStatus,
                        'updated_at' => $nowStr,
                    ]);
                    $this->orderEvents->insert(new OrderEvent([
                        'order_id'      => $order->id,
                        'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'to_status'     => $targetStatus,
                        'actor_user_id' => null,
                        'event_type'    => 'orphan_order_reset',
                        'meta'          => json_encode(['repaired_by' => 'integrity_repair']),
                        'note'          => 'إعادة الطلب إلى بانتظار التحويل لعدم وجود إشعار مرفوع',
                        'created_at'    => $nowStr,
                    ]));
                }
            } else {
                $targetStatus = Wex::ORDER_STATUS_EXPIRED;
                $actionDesc = "إلغاء الطلب {$order->order_number} (expired) لانتهاء مهلة الحجز واستعادة المخزون";
                if (!$dryRun) {
                    $this->orders->update($order->id, [
                        'status'     => $targetStatus,
                        'updated_at' => $nowStr,
                    ]);
                    // Restore stock
                    $orderItems = $this->orderItems->where('order_id', $order->id)->findAll();
                    foreach ($orderItems as $item) {
                        $db->query(
                            'UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = ? WHERE id = ?',
                            [$item->quantity, $nowStr, $item->product_id]
                        );
                    }
                    $this->orderEvents->insert(new OrderEvent([
                        'order_id'      => $order->id,
                        'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'to_status'     => $targetStatus,
                        'actor_user_id' => null,
                        'event_type'    => 'orphan_order_expired',
                        'meta'          => json_encode(['repaired_by' => 'integrity_repair']),
                        'note'          => 'إلغاء الطلب لانتهاء مهلة حجز السعر بدون إشعار دفع',
                        'created_at'    => $nowStr,
                    ]));
                }
            }

            $actions[] = [
                'type'        => 'orphan_order_repaired',
                'order_id'    => $order->id,
                'order_number'=> $order->order_number,
                'action'      => $actionDesc,
                'dry_run'     => $dryRun,
            ];
        }

        // Safe Repair 2: Expire overdue pending_payment orders
        foreach ($report['violations']['pending_payment_past_lock'] as $past) {
            $order = $this->orders->find($past['order_id']);
            if (!$order) {
                continue;
            }

            $actionDesc = "إلغاء الطلب المتأخر {$order->order_number} واستعادة المخزون";
            if (!$dryRun) {
                $this->orders->update($order->id, [
                    'status'     => Wex::ORDER_STATUS_EXPIRED,
                    'updated_at' => $nowStr,
                ]);
                $orderItems = $this->orderItems->where('order_id', $order->id)->findAll();
                foreach ($orderItems as $item) {
                    $db->query(
                        'UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = ? WHERE id = ?',
                        [$item->quantity, $nowStr, $item->product_id]
                    );
                }
                $this->orderEvents->insert(new OrderEvent([
                    'order_id'      => $order->id,
                    'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                    'to_status'     => Wex::ORDER_STATUS_EXPIRED,
                    'actor_user_id' => null,
                    'event_type'    => 'order_expired',
                    'meta'          => json_encode(['repaired_by' => 'integrity_repair']),
                    'note'          => 'انتهت فترة حجز السعر المحددة للطلب وتم إلغاء الحجز وإعادة المنتجات إلى المخزون.',
                    'created_at'    => $nowStr,
                ]));
            }

            $actions[] = [
                'type'        => 'overdue_order_expired',
                'order_id'    => $order->id,
                'order_number'=> $order->order_number,
                'action'      => $actionDesc,
                'dry_run'     => $dryRun,
            ];
        }

        // Safe Repair 3: Pending proofs on confirmed orders -> approve them
        foreach ($report['violations']['pending_proof_not_in_payment_review'] as $pr) {
            $order = $this->orders->where('order_number', $pr['order_number'])->first();
            if ($order && in_array($order->status, [Wex::ORDER_STATUS_CONFIRMED, Wex::ORDER_STATUS_COMPLETED], true)) {
                $actionDesc = "اعتماد الإشعار #{$pr['proof_id']} التابع للطلب المؤكد {$pr['order_number']}";
                if (!$dryRun) {
                    $this->proofs->update($pr['proof_id'], [
                        'status'      => Wex::PROOF_STATUS_APPROVED,
                        'reviewed_at' => $nowStr,
                    ]);
                }
                $actions[] = [
                    'type'     => 'pending_proof_approved',
                    'proof_id' => $pr['proof_id'],
                    'action'   => $actionDesc,
                    'dry_run'  => $dryRun,
                ];
            }
        }

        return [
            'dry_run'        => $dryRun,
            'actions_count'  => count($actions),
            'actions'        => $actions,
        ];
    }
}
