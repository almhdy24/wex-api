<?php

namespace App\Services;

use App\Entities\Order;
use Config\Wex;

/**
 * PaymentStateService
 *
 * Single server-side source of truth for payment state across customer & admin.
 * Computes:
 *   - state: awaiting_proof | under_review | rejected | approved | expired | not_required
 *   - can_upload: bool
 *   - can_review: bool
 *   - latest_proof: { id, status, uploaded_at, review_note, image_url } | null
 *   - proofs_count: int
 */
class PaymentStateService
{
    /**
     * Compute unified payment state block for an order.
     */
    public static function computePaymentBlock(array|Order $order, array $proofs = [], ?int $now = null): array
    {
        $orderArr = is_array($order) ? $order : $order->toArray();
        $currentTime = $now ?? time();

        $status = (string) ($orderArr['status'] ?? '');
        $orderNumber = (string) ($orderArr['order_number'] ?? '');

        // Sort proofs newest first (by id DESC)
        usort($proofs, static function ($a, $b) {
            $aId = is_array($a) ? ($a['id'] ?? 0) : ($a->id ?? 0);
            $bId = is_array($b) ? ($b['id'] ?? 0) : ($b->id ?? 0);
            return $bId <=> $aId;
        });

        $proofsCount = count($proofs);
        $latestRaw = $proofsCount > 0 ? $proofs[0] : null;
        $latestProof = null;

        if ($latestRaw) {
            $pArr = is_array($latestRaw) ? $latestRaw : $latestRaw->toArray();
            $uploadedAt = null;
            if (!empty($pArr['created_at'])) {
                $uploadedAt = gmdate('Y-m-d\TH:i:s\Z', strtotime($pArr['created_at']));
            }
            $latestProof = [
                'id'               => (int) ($pArr['id'] ?? 0),
                'status'           => (string) ($pArr['status'] ?? ''),
                'sender_name'      => $pArr['sender_name'] ?? null,
                'reference_number' => $pArr['reference_number'] ?? null,
                'review_note'      => $pArr['review_note'] ?? null,
                'image_url'        => (string) ($pArr['image_url'] ?? "/api/v1/orders/{$orderNumber}/payment-proof/{$pArr['id']}/image"),
                'uploaded_at'      => $uploadedAt,
                'created_at'       => $uploadedAt,
                'reviewed_at'      => !empty($pArr['reviewed_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime($pArr['reviewed_at'])) : null,
            ];
        }

        $lockExpiresAt = !empty($orderArr['lock_expires_at']) ? strtotime($orderArr['lock_expires_at']) : 0;
        $isLockExpired = ($lockExpiresAt > 0 && $lockExpiresAt <= $currentTime);

        // State machine mapping
        $approvedStatuses = [
            Wex::ORDER_STATUS_CONFIRMED,
            'processing',
            Wex::ORDER_STATUS_READY_FOR_PICKUP,
            Wex::ORDER_STATUS_OUT_FOR_DELIVERY,
            Wex::ORDER_STATUS_COMPLETED,
            'delivered',
        ];

        if (in_array($status, $approvedStatuses, true)) {
            $state = 'approved';
        } elseif ($status === Wex::ORDER_STATUS_EXPIRED || $status === Wex::ORDER_STATUS_CANCELLED) {
            $state = 'expired';
        } elseif ($status === Wex::ORDER_STATUS_PAYMENT_REVIEW) {
            $state = 'under_review';
        } elseif ($status === Wex::ORDER_STATUS_PENDING_PAYMENT) {
            if ($isLockExpired) {
                $state = 'expired';
            } elseif ($latestProof && $latestProof['status'] === Wex::PROOF_STATUS_REJECTED) {
                $state = 'rejected';
            } else {
                $state = 'awaiting_proof';
            }
        } elseif ($latestProof && $latestProof['status'] === Wex::PROOF_STATUS_REJECTED) {
            $state = 'rejected';
        } else {
            $state = 'awaiting_proof';
        }

        // Capabilities
        // can_upload: true only when customer should upload (awaiting_proof or rejected, within lock)
        $canUpload = ($state === 'awaiting_proof' || $state === 'rejected');

        // can_review: true only when under_review AND a pending proof actually exists
        $canReview = ($state === 'under_review' && $latestProof !== null && $latestProof['status'] === Wex::PROOF_STATUS_PENDING);

        return [
            'state'        => $state,
            'can_upload'   => $canUpload,
            'can_review'   => $canReview,
            'latest_proof' => $latestProof,
            'proofs_count' => $proofsCount,
        ];
    }
}
