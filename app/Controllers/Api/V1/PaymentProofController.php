<?php

namespace App\Controllers\Api\V1;

use App\Services\PaymentProofService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * PaymentProofController
 *
 * Customer payment receipt upload and authorized receipt image streaming.
 */
class PaymentProofController extends BaseApiController
{
    protected PaymentProofService $proofService;

    public function __construct(?PaymentProofService $proofService = null)
    {
        $this->proofService = $proofService ?? new PaymentProofService();
    }

    /**
     * POST /api/v1/orders/{order_number}/payment-proof
     *
     * Upload Bankak transfer receipt screenshot for an order.
     */
    public function upload(string $orderNumber): ResponseInterface
    {
        $user = $this->currentUser();
        $file = $this->request->getFile('proof_image') ?? $this->request->getFile('image');
        $senderName = $this->request->getPost('sender_name');
        $refNumber  = $this->request->getPost('reference_number');

        $result = $this->proofService->uploadProof(
            orderNumber: $orderNumber,
            userId: (int) $user->id,
            uploadedFile: $file,
            senderName: $senderName,
            referenceNumber: $refNumber
        );

        return $this->respondSuccess($result, 'تم رفع إشعار التحويل البنكي بنجاح وجاري مراجعته', ResponseInterface::HTTP_CREATED);
    }

    /**
     * GET /api/v1/orders/{order_number}/payment-proof/{id}/image
     *
     * Authorized image streaming. Serves receipt image only to order owner or staff/admin.
     */
    public function streamImage(string $orderNumber, int $proofId): ResponseInterface
    {
        $user = $this->currentUser();
        $isAdminOrStaff = $user && in_array($user->role, ['admin', 'staff'], true);

        $fileInfo = $this->proofService->getProofForStream(
            orderNumber: $orderNumber,
            proofId: $proofId,
            userId: $user ? (int) $user->id : null,
            isAdminOrStaff: $isAdminOrStaff
        );

        $contents = file_get_contents($fileInfo['path']);

        return $this->response
            ->setStatusCode(200)
            ->setContentType($fileInfo['mime'])
            ->setHeader('Content-Length', (string) $fileInfo['size'])
            ->setHeader('Cache-Control', 'private, no-cache, no-store, must-revalidate')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('Expires', '0')
            ->setBody($contents);
    }
}
