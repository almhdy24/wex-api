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
            throw new ForbiddenException('لا تملك صلاحية الوصول إلى هذا الطلب');
        }

        // Lazy lock expiry check
        $this->orderService->refreshExpiry($order);

        // Can upload if pending_payment or if payment_review (replacement)
        if ($order->status === Wex::ORDER_STATUS_EXPIRED) {
            throw new ConflictException('انتهت مهلة تثبيت السعر لهذا الطلب');
        }

        if ($order->lock_expires_at && strtotime($order->lock_expires_at) < time()) {
            throw new ConflictException('انتهت مهلة تثبيت السعر لهذا الطلب');
        }

        if ($order->status === Wex::ORDER_STATUS_PAYMENT_REVIEW) {
            // Replacement upload while review is pending and lock not expired
        } elseif ($order->status !== Wex::ORDER_STATUS_PENDING_PAYMENT) {
            throw new ConflictException('لا يمكن رفع إشعار تحويل لهذا الطلب في حالته الحالية');
        }

        // Rate limit: max 5 uploads per order per hour to protect against disk exhaustion
        $oneHourAgo = gmdate('Y-m-d H:i:s', time() - 3600);
        $recentUploads = $this->paymentProofs->where('order_id', $order->id)
            ->where('created_at >=', $oneHourAgo)
            ->countAllResults();
        if ($recentUploads >= 5) {
            throw new \App\Exceptions\TooManyRequestsException(3600, 'تجاوزت الحد المسموح من المحاولات');
        }

        $isValid = $uploadedFile && (
            $uploadedFile->isValid() ||
            (ENVIRONMENT === 'testing' && $uploadedFile->getError() === UPLOAD_ERR_OK && file_exists($uploadedFile->getTempName()))
        );

        if (!$isValid) {
            $err = $uploadedFile ? $uploadedFile->getError() : UPLOAD_ERR_NO_FILE;
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                throw new \App\Exceptions\EntityTooLargeException('حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى');
            }
            throw new ValidationFailedException(['proof_image' => 'الملف المرفق ليس صورة صالحة، يرجى رفع صورة بصيغة JPG أو PNG أو WebP'], 'يرجى إرفاق صورة إشعار التحويل البنكي.');
        }

        // Store image privately using ImageService
        $relativePath = null;
        $nowStr = gmdate('Y-m-d H:i:s');
        $isReplacement = ($order->status === Wex::ORDER_STATUS_PAYMENT_REVIEW);
        $db = Database::connect();

        try {
            $relativePath = $this->imageService->savePaymentProof($uploadedFile);

            $db->transBegin();

            if ($isReplacement) {
                // Mark previous pending proof(s) as replaced
                $this->paymentProofs
                    ->where('order_id', $order->id)
                    ->where('status', Wex::PROOF_STATUS_PENDING)
                    ->set(['status' => Wex::PROOF_STATUS_REPLACED])
                    ->update();
            }

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
            if (!$proofId) {
                throw new \RuntimeException('فشل في حفظ سجل إشعار التحويل.');
            }

            if (!$isReplacement) {
                // Transition order status to payment_review (compare-and-set: the order may have
                // been cancelled/expired concurrently and must not be resurrected).
                $this->orderService->casStatus($order, Wex::ORDER_STATUS_PENDING_PAYMENT, Wex::ORDER_STATUS_PAYMENT_REVIEW, $nowStr);
            }

            // Add structured timeline event
            if ($isReplacement) {
                $fromStatus = Wex::ORDER_STATUS_PAYMENT_REVIEW;
                $eventType  = 'payment_proof_replaced';
                $note       = 'قام العميل بتحديث إشعار التحويل واستبدال الإشعار السابق للمراجعة';
            } else {
                $fromStatus = Wex::ORDER_STATUS_PENDING_PAYMENT;
                $eventType  = 'payment_proof_uploaded';
                $note       = 'قام العميل برفع إشعار التحويل البنكي للمراجعة';
            }

            if ($senderName) {
                $note .= " (اسم المحول: {$senderName})";
            }
            if ($referenceNumber) {
                $note .= " (رقم العملية: {$referenceNumber})";
            }

            $eventMeta = [
                'proof_id'         => (int) $proofId,
                'sender_name'      => $senderName ? mb_substr(trim($senderName), 0, 150) : null,
                'reference_number' => $referenceNumber ? mb_substr(trim($referenceNumber), 0, 100) : null,
            ];

            $this->orderEvents->insert(new OrderEvent([
                'order_id'      => $order->id,
                'from_status'   => $fromStatus,
                'to_status'     => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                'actor_user_id' => $userId,
                'event_type'    => $eventType,
                'meta'          => json_encode($eventMeta),
                'note'          => $note,
                'created_at'    => $nowStr,
            ]));

            if ($db->transStatus() === false) {
                $db->transRollback();
                throw new \RuntimeException('فشل في إتمام عملية رفع إشعار التحويل.');
            }

            $db->transCommit();
        } catch (\Throwable $e) {
            if (isset($db) && $db->transStatus() !== false) {
                $db->transRollback();
            }
            if ($relativePath) {
                $fileToDel = WRITEPATH . ltrim($relativePath, '/');
                if (file_exists($fileToDel)) {
                    @unlink($fileToDel);
                }
            }
            throw $e;
        }

        // Notify customer that proof was received and is under review
        try {
            $tpl = \Config\PushTemplates::render('payment_proof_uploaded', ['order_number' => $orderNumber]);
            $this->notificationService->notify(
                userId: $userId,
                type: 'payment_proof_uploaded',
                title: $tpl['title'],
                body: $tpl['body'],
                orderId: (int) $order->id,
                emailTemplate: 'payment_received',
                emailPayload: ['order_number' => $orderNumber],
                pushOptions: [
                    'order_number'          => $orderNumber,
                    'url'                   => "/orders/{$orderNumber}",
                    'expected_order_status' => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                ]
            );

            $adminTpl = \Config\PushTemplates::render('admin_receipt_uploaded', ['order_number' => $orderNumber]);
            (new PushService())->sendToRole([Wex::ROLE_ADMIN, Wex::ROLE_STAFF], [
                'type'          => 'admin_receipt_uploaded',
                'title'         => $adminTpl['title'],
                'body'          => $adminTpl['body'],
                'order_id'      => (int) $order->id,
                'order_number'  => $orderNumber,
                'url'           => "/admin/orders/{$orderNumber}",
                'actor_user_id' => $userId,
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to dispatch payment_proof_uploaded notification: {msg}', ['msg' => $e->getMessage()]);
        }

        $createdProof = [
            'id'               => (int) $proofId,
            'order_id'         => (int) $order->id,
            'status'           => Wex::PROOF_STATUS_PENDING,
            'sender_name'      => $senderName ? mb_substr(trim($senderName), 0, 150) : null,
            'reference_number' => $referenceNumber ? mb_substr(trim($referenceNumber), 0, 100) : null,
            'review_note'      => null,
            'image_url'        => "/api/v1/orders/{$order->order_number}/payment-proof/{$proofId}/image",
            'created_at'       => gmdate('Y-m-d\TH:i:s\Z', strtotime($nowStr)),
            'uploaded_at'      => gmdate('Y-m-d\TH:i:s\Z', strtotime($nowStr)),
        ];

        $allProofs = $this->paymentProofs->where('order_id', $order->id)->orderBy('id', 'DESC')->findAll();
        $order->status = Wex::ORDER_STATUS_PAYMENT_REVIEW;
        $paymentBlock = PaymentStateService::computePaymentBlock($order, $allProofs);

        return [
            'proof'         => $createdProof,
            'proof_id'      => (int) $proofId,
            'status'        => Wex::PROOF_STATUS_PENDING,
            'order_status'  => Wex::ORDER_STATUS_PAYMENT_REVIEW,
            'image_url'     => "/api/v1/orders/{$order->order_number}/payment-proof/{$proofId}/image",
            'uploaded_at'   => gmdate('Y-m-d\TH:i:s\Z', strtotime($nowStr)),
            'payment'       => $paymentBlock,
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
            throw new ConflictException('تمت مراجعة هذا الإشعار مسبقاً.');
        }

        // A proof can only be reviewed while its order is awaiting review. Without this an
        // admin could "approve" a proof on an order that was already cancelled/expired (stock
        // released), resurrecting it as confirmed and overselling.
        if ($order->status !== Wex::ORDER_STATUS_PAYMENT_REVIEW) {
            throw new ConflictException('لا يمكن مراجعة الإشعار لأن الطلب ليس في مرحلة مراجعة الدفع.');
        }

        $nowStr = gmdate('Y-m-d H:i:s');
        $reviewNote = $note ? mb_substr(trim($note), 0, 500) : null;

        $db = Database::connect();
        $db->transBegin();

        try {
            // Compare-and-set the proof: two reviewers acting at once must not both win.
            $this->paymentProofs->builder()
                ->where('id', $proof->id)
                ->where('status', Wex::PROOF_STATUS_PENDING)
                ->update([
                    'status'      => $action === 'approve' ? Wex::PROOF_STATUS_APPROVED : Wex::PROOF_STATUS_REJECTED,
                    'review_note' => $reviewNote,
                    'reviewed_by' => $reviewerUserId,
                    'reviewed_at' => $nowStr,
                ]);
            if ($db->affectedRows() !== 1) {
                throw new ConflictException('تمت مراجعة هذا الإشعار مسبقاً.');
            }

            if ($action === 'approve') {
                // Transition order to confirmed (compare-and-set on payment_review)
                $this->orderService->casStatus($order, Wex::ORDER_STATUS_PAYMENT_REVIEW, Wex::ORDER_STATUS_CONFIRMED, $nowStr);

                // Add event
                $eventNote = 'تمت مراجعة التحويل البنكي والموافقة عليه وتأكيد الطلب.';
                if ($reviewNote) {
                    $eventNote .= ' ملاحظة: ' . $reviewNote;
                }

                $eventMeta = [
                    'proof_id'    => (int) $proof->id,
                    'review_note' => $reviewNote,
                ];

                $this->orderEvents->insert(new OrderEvent([
                    'order_id'      => $order->id,
                    'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                    'to_status'     => Wex::ORDER_STATUS_CONFIRMED,
                    'actor_user_id' => $reviewerUserId,
                    'event_type'    => 'payment_approved',
                    'meta'          => json_encode($eventMeta),
                    'note'          => $eventNote,
                    'created_at'    => $nowStr,
                ]));
            } else {
                // Reject (proof already marked rejected above)
                // Check lock expiry
                $nowTime = time();
                $lockExpiresTime = strtotime($order->lock_expires_at);
                $eventMeta = [
                    'proof_id'    => (int) $proof->id,
                    'review_note' => $reviewNote,
                ];

                if ($nowTime <= $lockExpiresTime) {
                    // Lock still valid: return to pending_payment for re-upload
                    $this->orderService->casStatus($order, Wex::ORDER_STATUS_PAYMENT_REVIEW, Wex::ORDER_STATUS_PENDING_PAYMENT, $nowStr);

                    $eventNote = 'تم رفض إشعار التحويل البنكي. يرجى رفع إشعار تحويل صحيح قبل انتهاء فترة حجز السعر.';
                    if ($reviewNote) {
                        $eventNote .= ' سبب الرفض: ' . $reviewNote;
                    }

                    $this->orderEvents->insert(new OrderEvent([
                        'order_id'      => $order->id,
                        'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'to_status'     => Wex::ORDER_STATUS_PENDING_PAYMENT,
                        'actor_user_id' => $reviewerUserId,
                        'event_type'    => 'payment_rejected',
                        'meta'          => json_encode($eventMeta),
                        'note'          => $eventNote,
                        'created_at'    => $nowStr,
                    ]));
                } else {
                    // Lock expired: transition to expired and restore stock
                    $this->orderService->casStatus($order, Wex::ORDER_STATUS_PAYMENT_REVIEW, Wex::ORDER_STATUS_EXPIRED, $nowStr);

                    // Restore reserved stock
                    $this->orderService->restoreStock((int) $order->id);

                    $eventNote = 'تم رفض إشعار التحويل البنكي، ونظراً لانتهاء فترة حجز السعر فقد تم إلغاء الطلب وإعادة المنتجات إلى المخزون.';
                    if ($reviewNote) {
                        $eventNote .= ' سبب الرفض: ' . $reviewNote;
                    }

                    $this->orderEvents->insert(new OrderEvent([
                        'order_id'      => $order->id,
                        'from_status'   => Wex::ORDER_STATUS_PAYMENT_REVIEW,
                        'to_status'     => Wex::ORDER_STATUS_EXPIRED,
                        'actor_user_id' => $reviewerUserId,
                        'event_type'    => 'payment_rejected',
                        'meta'          => json_encode($eventMeta),
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

        try {
            (new AuditLogService())->log($reviewerUserId, 'payment_proof_' . ($action === 'approve' ? 'approved' : 'rejected'), 'order', (int) $order->id, [
                'order_number' => $orderNumber,
                'proof_id'     => (int) $proof->id,
                'note'         => $reviewNote,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'Audit log write failed for proof review: {msg}', ['msg' => $e->getMessage()]);
        }

        // Send review notification
        try {
            if ($action === 'approve') {
                $tpl = \Config\PushTemplates::render('payment_approved', ['order_number' => $orderNumber]);
                $this->notificationService->notify(
                    userId: (int) $order->user_id,
                    type: 'payment_approved',
                    title: $tpl['title'],
                    body: $tpl['body'],
                    orderId: (int) $order->id,
                    emailTemplate: 'payment_approved',
                    emailPayload: [
                        'order_number'  => $orderNumber,
                        'total_sdg'     => (int) $order->total_sdg,
                        'delivery_type' => $order->delivery_type,
                    ],
                    pushOptions: [
                        'order_number'          => $orderNumber,
                        'url'                   => "/orders/{$orderNumber}",
                        'actor_user_id'         => $reviewerUserId,
                        'expected_order_status' => Wex::ORDER_STATUS_CONFIRMED,
                    ]
                );
            } else {
                $pushTpl = \Config\PushTemplates::render('payment_rejected', ['order_number' => $orderNumber]);
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
                    ],
                    pushOptions: [
                        'order_number'  => $orderNumber,
                        'url'           => "/orders/{$orderNumber}",
                        'actor_user_id' => $reviewerUserId,
                        'push_title'    => $pushTpl['title'],
                        'push_body'     => $pushTpl['body'],
                    ]
                );
            }
        } catch (\Throwable $e) {
            log_message('warning', 'Failed to dispatch payment review notification: {msg}', ['msg' => $e->getMessage()]);
        }

        return $this->orderService->getOrder($orderNumber, null, true);
    }
}
