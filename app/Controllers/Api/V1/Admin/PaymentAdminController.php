<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Exceptions\ValidationFailedException;
use App\Presenters\AdminOrderPresenter;
use App\Services\PaymentProofService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * PaymentAdminController
 *
 * Review customer bank transfer receipts (approve / reject). Accessible by Admin and Staff.
 */
class PaymentAdminController extends BaseApiController
{
    protected PaymentProofService $proofService;

    public function __construct(?PaymentProofService $proofService = null)
    {
        $this->proofService = $proofService ?? new PaymentProofService();
    }

    /**
     * POST /api/v1/admin/orders/{order_number}/proofs/{id}/review
     *
     * Review receipt screenshot: approve (confirms order) or reject (returns to pending or expires).
     */
    public function review(string $orderNumber, int $proofId): ResponseInterface
    {
        $payload = $this->request->getJSON(true) ?? $this->request->getPost();
        if (!is_array($payload) || empty($payload['action'])) {
            throw new ValidationFailedException('إجراء المراجعة مطلوب.', [
                'action' => 'يرجى تحديد الإجراء (approve أو reject).',
            ]);
        }

        $action = trim((string) $payload['action']);
        $note   = isset($payload['note']) ? trim((string) $payload['note']) : null;
        $user   = $this->currentUser();

        $result = $this->proofService->reviewProof(
            orderNumber: $orderNumber,
            proofId: $proofId,
            action: $action,
            note: $note,
            reviewerUserId: (int) $user->id
        );

        $msg = ($action === 'approve')
            ? 'تم قبول إشعار التحويل البنكي وتأكيد الطلب بنجاح'
            : 'تم رفض إشعار التحويل البنكي وتحديث حالة الطلب';

        $presented = AdminOrderPresenter::present($result);

        return $this->respondSuccess($presented, $msg);
    }
}
