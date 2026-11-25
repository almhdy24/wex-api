<?php

namespace Tests\Unit;

use App\Exceptions\ValidationFailedException;
use App\Services\ImageService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * SEC-UPL-01: no pixel limit before decode (decompression bomb).
 * SEC-UPL-02: files GD could not decode were copied to storage unchanged.
 *
 * @requires extension gd
 * @internal
 */
final class ImageUploadHardeningTest extends CIUnitTestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/wex_img_' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0777, true);
    }

    private function png(int $w, int $h): string
    {
        $im = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($im);
        $png = ob_get_clean();
        // Rewrite IHDR width/height (bytes 16..23); getimagesize reads them without decoding.
        return substr($png, 0, 16) . pack('N', $w) . pack('N', $h) . substr($png, 24);
    }

    private function upload(string $bytes): UploadedFile
    {
        $path = $this->dir . '/up_' . bin2hex(random_bytes(3));
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'receipt.png', 'image/png', strlen($bytes), UPLOAD_ERR_OK);
    }

    private function proofCount(): int
    {
        return count(glob(WRITEPATH . 'uploads/proofs/*') ?: []);
    }

    public function testRejectsPixelBombBeforeDecoding(): void
    {
        $before = $this->proofCount();
        try {
            (new ImageService())->storePaymentProof($this->upload($this->png(20000, 20000)));
            $this->fail('A 400-megapixel image must be rejected');
        } catch (ValidationFailedException $e) {
            $this->assertArrayHasKey('proof_image', $e->getErrors());
        }
        $this->assertSame($before, $this->proofCount());
    }

    public function testUndecodableImageIsRejectedNotCopied(): void
    {
        $truncated = substr($this->png(10, 10), 0, 40); // valid signature + IHDR, no image data
        $before = $this->proofCount();
        try {
            (new ImageService())->storePaymentProof($this->upload($truncated));
            $this->fail('An undecodable image must be rejected');
        } catch (ValidationFailedException) {
            // expected
        }
        $this->assertSame($before, $this->proofCount(), 'nothing may be stored');
    }
}
