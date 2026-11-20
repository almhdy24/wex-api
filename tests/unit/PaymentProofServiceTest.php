<?php

namespace Tests\Unit;

use App\Entities\Order;
use App\Entities\PaymentProof;
use App\Entities\User;
use App\Exceptions\ConflictException;
use App\Exceptions\EntityTooLargeException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\TooManyRequestsException;
use App\Exceptions\ValidationFailedException;
use App\Models\OrderEventModel;
use App\Models\OrderModel;
use App\Models\PaymentProofModel;
use App\Services\ImageService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\PaymentProofService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Wex;

/**
 * @internal
 */
final class PaymentProofServiceTest extends CIUnitTestCase
{
    protected string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/wex_proof_tests_' . bin2hex(random_bytes(4));
        if (!is_dir($this->tmpDir)) {
            mkdir($this->tmpDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tmpDir)) {
            $files = glob($this->tmpDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($this->tmpDir);
        }
    }

    public function testValidateProofFileAcceptsValidBlobWithoutExtension(): void
    {
        $imageService = new ImageService();

        // 1x1 8-bit PNG
        $pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tmpPath = $this->tmpDir . '/blob';
        file_put_contents($tmpPath, $pngData);

        $file = new UploadedFile($tmpPath, 'blob', 'application/octet-stream', filesize($tmpPath), UPLOAD_ERR_OK);
        $ext = $imageService->validateProofFile($file);

        $this->assertSame('png', $ext);
    }

    public function testValidateProofFileAcceptsValidReceiptWithoutExtension(): void
    {
        $imageService = new ImageService();

        // 1x1 JPEG
        $jpgData = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');
        $tmpPath = $this->tmpDir . '/receipt';
        file_put_contents($tmpPath, $jpgData);

        $file = new UploadedFile($tmpPath, 'receipt', 'application/octet-stream', filesize($tmpPath), UPLOAD_ERR_OK);
        $ext = $imageService->validateProofFile($file);

        $this->assertSame('jpg', $ext);
    }

    public function testValidateProofFileRejectsNonImageDisguised(): void
    {
        $imageService = new ImageService();

        $tmpPath = $this->tmpDir . '/fake.jpg';
        file_put_contents($tmpPath, '<?php echo "evil"; ?>');

        $file = new UploadedFile($tmpPath, 'fake.jpg', 'image/jpeg', filesize($tmpPath), UPLOAD_ERR_OK);

        $this->expectException(ValidationFailedException::class);
        $this->expectExceptionMessage('الملف المرفق ليس صورة صالحة، يرجى رفع صورة بصيغة JPG أو PNG أو WebP');
        $imageService->validateProofFile($file);
    }

    public function testValidateProofFileThrowsEntityTooLargeWhenExceedingLimit(): void
    {
        $imageService = new ImageService();

        $tmpPath = $this->tmpDir . '/large.jpg';
        // Create 6 MB dummy file
        $fp = fopen($tmpPath, 'wb');
        fseek($fp, 6 * 1024 * 1024);
        fwrite($fp, "\0");
        fclose($fp);

        $file = new UploadedFile($tmpPath, 'large.jpg', 'image/jpeg', filesize($tmpPath), UPLOAD_ERR_OK);

        $this->expectException(EntityTooLargeException::class);
        $this->expectExceptionMessage('حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى');
        $imageService->validateProofFile($file);
    }
}
