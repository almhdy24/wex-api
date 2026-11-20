<?php

namespace App\Controllers\Api\V1;

use App\Exceptions\EntityTooLargeException;
use App\Exceptions\ValidationFailedException;
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
        // Detect post_max_size overflow where PHP empties $_POST and $_FILES
        $contentLength = (int) ($this->request->getHeaderLine('Content-Length') ?: ($_SERVER['CONTENT_LENGTH'] ?? 0));
        $postMaxSizeStr = ini_get('post_max_size') ?: '64M';
        $postMaxBytes = $this->parseIniBytes($postMaxSizeStr);
        if ($contentLength > $postMaxBytes && empty($_FILES) && empty($_POST)) {
            throw new EntityTooLargeException('حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى');
        }

        $user = $this->currentUser();

        // Enforce field name 'proof_image' only
        $file = $this->request->getFile('proof_image');
        if (!$file) {
            $allFiles = $this->request->getFiles();
            if (!empty($allFiles)) {
                throw new ValidationFailedException(
                    ['proof_image' => 'حقل الملف غير صحيح. الحقل المطلوب هو proof_image'],
                    'حقل الملف غير صحيح. الحقل المطلوب هو proof_image'
                );
            }
        }

        if ($file && ($file->getError() === UPLOAD_ERR_INI_SIZE || $file->getError() === UPLOAD_ERR_FORM_SIZE)) {
            throw new EntityTooLargeException('حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى');
        }

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

    private function parseIniBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $num = (int) $val;
        switch ($last) {
            case 'g':
                $num *= 1024 * 1024 * 1024;
                break;
            case 'm':
                $num *= 1024 * 1024;
                break;
            case 'k':
                $num *= 1024;
                break;
        }
        return $num;
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
