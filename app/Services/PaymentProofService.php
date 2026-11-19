<?php

namespace App\Services;

use App\Entities\OrderEvent;
use App\Entities\PaymentProof;
use App\Exceptions\ApiException;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Models\OrderEventModel;
use App\Models\OrderModel;
use App\Models\PaymentProofModel;
use Config\Database;
use Config\Wex;

/**
 * PaymentProofService
 *
 * Handles Bankak manual transfer proof uploads, authorized streaming,
 * and admin/staff approval/rejection workflows.
 */
class PaymentProofService
{
    protected PaymentProofModel $paymentProofs;
    protected OrderModel $orders;
    protected OrderEventModel $orderEvents;
    protected OrderService $orderService;
    protected ImageService $imageService;
    protected NotificationService $notificationService;

    public function __construct(
        ?PaymentProofModel $paymentProofs = null,
        ?OrderModel $orders = null,
        ?OrderEventModel $orderEvents = null,
        ?OrderService $orderService = null,
        ?ImageService $imageService = null,
        ?NotificationService $notificationService = null
    ) {
        $this->paymentProofs       = $paymentProofs ?? new PaymentProofModel();
        $this->orders              = $orders ?? new OrderModel();
        $this->orderEvents         = $orderEvents ?? new OrderEventModel();
        $this->orderService        = $orderService ?? new OrderService();
        $this->imageService        = $imageService ?? new ImageService();
        $this->notificationService = $notificationService ?? new NotificationService();
    }

    /**
     * Upload Bankak receipt image for an order.
     *
     * @param string $orderNumber
     * @param int $userId Customer ID
     * @param mixed $uploadedFile CI4 UploadedFile instance
     * @param string|null $senderName
     * @param string|null $referenceNumber
     * @throws NotFoundException|ForbiddenException|ConflictException|ValidationFailedException
     */
    public function uploadProof(
        string $orderNumber,
        int $userId,
        mixed $uploadedFile,
        ?string $senderName = null,
        ?string $referenceNumber = null
    ): array {
        $order = $this->orders->where('order_number', $orderNumber)->first();
        if (!$order) {
            throw new NotFoundException('الطلب غير موجود.');
        }

        if ((int) $order->user_id !== $userId) {
            throw new ForbiddenException('غير مصرح لك برفع إشعار تحويل لهذا الطلب.');
        }

        // Lazy lock expiry check
        $this->orderService->refreshExpiry($order);

        // Can only upload if pending_payment (or if payment_review after prior rejection)
        if ($order->status !== Wex::ORDER_STATUS_PENDING_PAYMENT) {
            if ($order->status === Wex::ORDER_STATUS_EXPIRED) {
                throw new ConflictException('انتهت فترة حجز السعر لهذا الطلب. يرجى إنشاء طلب جديد.');
            }
            throw new ConflictException('لا يمكن رفع إشعار تحويل لهذا الطلب نظراً لحالته الحالية: ' . OrderStateMachine::getStatusLabel($order->status));
        }

        $isValid = $uploadedFile && (
            $uploadedFile->isValid() ||
            (ENVIRONMENT === 'testing' && $uploadedFile->getError() === UPLOAD_ERR_OK && file_exists($uploadedFile->getTempName()))
        );

        if (!$isValid) {
            throw new ValidationFailedException(['proof_image' => 'يرجى إرفاق صورة إشعار التحويل البنكي.'], 'ملف إشعار التحويل غير صالح أو لم يتم اختياره.');
        }

        // Store image privately using ImageService
        $relativePath = $this->imageService->savePaymentProof($uploadedFile);

        $nowStr = gmdate('Y-m-d H:i:s');
        $db = Database::connect();
        $db->transBegin();

        try {
            // Insert PaymentProof record
            $proof = new PaymentProof([
                'order_id'         => $order->id,
                'file_path'        => $relativePath,
                'sender_name'      => $senderName ? mb_substr(trim($senderName), 0, 150) : null,
                'reference_number' => $referenceNumber ? mb_substr(trim($referenceNumber), 0, 100) : null,
                'status'           => Wex::PROOF_STATUS_PENDING,
                'created_at'       => $nowStr,
            ]);

            $proofId = $this->paymentProofs->insert($proof);

            // Transition order status to payment_review
            $this->orders->update($order->id, [
                'status'     => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                'updated_at' => $nowStr,
            ]);

            // Add timeline event
            $note = 'قام العميل برفع إشعار التحويل البنكي للمراجعة';
            if ($senderName) {
                $note .= " (اسم المحول: {$senderName})";
            }
            if ($referenceNumber) {
                $note .= " (رقم العملية: {$referenceNumber})";
            }

            $this->orderEvents->insert(new OrderEvent([
                'order_id'      => $order->id,
                'from_status'   => Wex::ORDER_STATUS_PENDING_PAYMENT,
                'to_status'     => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                'actor_user_id' => $userId,
                'note'          => $note,
                'created_at'    => $nowStr,
            ]));

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        // Notify customer that proof was received and is under review
        try {
            $this->notificationService->notify(
                userId: $userId,
                type: 'payment_proof_uploaded',
                title: 'تم استلام إشعار التحويل البنكي',
                body: "تم استلام إشعار التحويل للطلب رقم {$orderNumber} وجاري مراجعته وتأكيده.",
                orderId: (int) $order->id,
                emailTemplate: 'payment_received',
                emailPayload: ['order_number' => $orderNumber]
            );
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to dispatch payment_proof_uploaded notification: {msg}', ['msg' => $e->getMessage()]);
        }

        return [
            'proof_id'      => $proofId,
            'status'        => Wex::PROOF_STATUS_PENDING,
            'order_status'  => Wex::ORDER_STATUS_PAYMENT_REVIEW,
            'image_url'     => "/api/v1/orders/{$order->order_number}/payment-proof/{$proofId}/image",
            'uploaded_at'   => gmdate('Y-m-d\TH:i:s\Z', strtotime($nowStr)),
        ];
    }

    /**
     * Resolves and verifies an authorized image for streaming.
     * Only the order owner or staff/admin can stream the image.
     *
     * @throws NotFoundException|ForbiddenException
     */
    public function getProofForStream(
        string $orderNumber,
        int $proofId,
        ?int $userId,
        bool $isAdminOrStaff = false
    ): array {
        $order = $this->orders->where('order_number', $orderNumber)->first();
        if (!$order) {
            throw new NotFoundException('الطلب غير موجود.');
        }

        if (!$isAdminOrStaff && (int) $order->user_id !== $userId) {
            throw new ForbiddenException('غير مصرح لك بعرض إشعار التحويل لهذا الطلب.');
        }

        $proof = $this->paymentProofs
            ->where('id', $proofId)
            ->where('order_id', $order->id)
            ->first();

        if (!$proof) {
            throw new NotFoundException('إشعار التحويل غير موجود.');
        }

        $absolutePath = WRITEPATH . ltrim($proof->file_path, '/');
        if (!file_exists($absolutePath) || !is_readable($absolutePath)) {
            throw new NotFoundException('ملف إشعار التحويل غير متوفر على الخادم.');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $absolutePath) ?: 'image/jpeg';
        finfo_close($finfo);

        return [
            'path'     => $absolutePath,
            'mime'     => $mime,
            'filename' => basename($absolutePath),
            'size'     => filesize($absolutePath),
        ];
    }

    /**
     * Admin/Staff reviews a payment proof (approve or reject).
     *
     * @param string $orderNumber
     * @param int $proofId
     * @param string $action 'approve' | 'reject'
     * @param string|null $note
     * @param int $reviewerUserId
     * @throws NotFoundException|ConflictException|ValidationFailedException
     */
    public function reviewProof(
        string $orderNumber,
        int $proofId,
        string $action,
        ?string $note,
        int $reviewerUserId
    ): array {
        if (!in_array($action, ['approve', 'reject'], true)) {
            throw new ValidationFailedException('إجراء المراجعة غير صحيح. يجب أن يكون approve أو reject.', [
                'action' => 'الإجراء المطلوب غير صالح.',
            ]);
        }

        $order = $this->orders->where('order_number', $orderNumber)->first();
        if (!$order) {
            throw new NotFoundException('الطلب غير موجود.');
        }

        $proof = $this->paymentProofs
            ->where('id', $proofId)
            ->where('order_id', $order->id)
            ->first();

        if (!$proof) {
            throw new NotFoundException('إشعار التحويل غير موجود.');
        }

        if ($proof->status !== Wex::PROOF_STATUS_PENDING) {
            throw new ConflictException('تمت مراجعة هذا الإشعار مسبقاً بحالة: ' . $proof->status);
        }

        $nowStr = gmdate('Y-m-d H:i:s');
        $reviewNote = $note ? mb_substr(trim($note), 0, 500) : null;

        $db = Database::connect();
        $db->transBegin();

        try {
            if ($action === 'approve') {
                // Update proof
                $this->paymentProofs->update($proof->id, [
                    'status'      => Wex::PROOF_STATUS_APPROVED,
                    'review_note' => $reviewNote,
                    'reviewed_by' => $reviewerUserId,
                    'reviewed_at' => $nowStr,
                ]);

                // Transition order to confirmed
                $this->orders->update($order->id, [
                    'status'     => Wex::ORDER_STATUS_CONFIRMED,
                    'updated_at' => $nowStr,
                ]);

                // Add event
                $eventNote = 'تمت مراجعة التحويل البنكي والموافقة عليه وتأكيد الطلب.';
                if ($reviewNote) {
                    $eventNote .= ' ملاحظة: ' . $reviewNote;
                }

                $this->orderEvents->insert(new OrderEvent([
                    'order_id'      => $order->id,
                    'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                    'to_status'     => Wex::ORDER_STATUS_CONFIRMED,
                    'actor_user_id' => $reviewerUserId,
                    'note'          => $eventNote,
                    'created_at'    => $nowStr,
                ]));
            } else {
                // Reject
                $this->paymentProofs->update($proof->id, [
                    'status'      => Wex::PROOF_STATUS_REJECTED,
                    'review_note' => $reviewNote,
                    'reviewed_by' => $reviewerUserId,
                    'reviewed_at' => $nowStr,
                ]);

                // Check lock expiry
                $nowTime = time();
                $lockExpiresTime = strtotime($order->lock_expires_at);

                if ($nowTime <= $lockExpiresTime) {
                    // Lock still valid: return to pending_payment for re-upload
                    $this->orders->update($order->id, [
                        'status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'updated_at' => $nowStr,
                    ]);

                    $eventNote = 'تم رفض إشعار التحويل البنكي. يرجى رفع إشعار تحويل صحيح قبل انتهاء فترة حجز السعر.';
                    if ($reviewNote) {
                        $eventNote .= ' سبب الرفض: ' . $reviewNote;
                    }

                    $this->orderEvents->insert(new OrderEvent([
                        'order_id'      => $order->id,
                        'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'actor_user_id' => $reviewerUserId,
                        'note'          => $eventNote,
                        'created_at'    => $nowStr,
                    ]));
                } else {
                    // Lock expired: transition to expired and restore stock
                    $this->orders->update($order->id, [
                        'status'     => Wex::ORDER_STATUS_EXPIRED,
                        'updated_at' => $nowStr,
                    ]);

                    // Restore reserved stock
                    $orderItems = (new \App\Models\OrderItemModel())->where('order_id', $order->id)->findAll();
                    foreach ($orderItems as $item) {
                        $db->query(
                            'UPDATE products SET stock_quantity = stock_quantity + ?, updated_at = ? WHERE id = ?',
                            [$item->quantity, $nowStr, $item->product_id]
                        );
                    }

                    $eventNote = 'تم رفض إشعار التحويل البنكي، ونظراً لانتهاء فترة حجز السعر فقد تم إلغاء الطلب واسترجاع المخزون.';
                    if ($reviewNote) {
                        $eventNote .= ' سبب الرفض: ' . $reviewNote;
                    }

                    $this->orderEvents->insert(new OrderEvent([
                        'order_id'      => $order->id,
                        'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'to_status'     => Wex::ORDER_STATUS_EXPIRED,
                        'actor_user_id' => $reviewerUserId,
                        'note'          => $eventNote,
                        'created_at'    => $nowStr,
                    ]));
                }
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }

        // Send review notification
        try {
            if ($action === 'approve') {
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'payment_approved',
                    title: 'تم تأكيد دفع الطلب بنجاح',
                    body: "تمت مراجعة التحويل البنكي وتأكيد طلبك رقم {$orderNumber}.",
                    orderId: (int) $order->id,
                    emailTemplate: 'payment_approved',
                    emailPayload: [
                        'order_number'  => $orderNumber,
                        'total_sdg'     => (int) $order->total_sdg,
                        'delivery_type' => $order->delivery_type,
                    ]
                );
            } else {
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'payment_rejected',
                    title: 'تعذر تأكيد إشعار التحويل البنكي',
                    body: "تم رفض إشعار التحويل للطلب رقم {$orderNumber}." . ($reviewNote ? " السبب: {$reviewNote}" : ''),
                    orderId: (int) $order->id,
                    emailTemplate: 'payment_rejected',
                    emailPayload: [
                        'order_number' => $orderNumber,
                        'reason'       => $reviewNote,
                    ]
                );
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to dispatch payment review notification: {msg}', ['msg' => $e->getMessage()]);
        }

        return $this->orderService->getOrder($orderNumber, null, true);
    }
}
