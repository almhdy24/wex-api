<?php

namespace App\Services;

use App\Exceptions\EntityTooLargeException;
use App\Exceptions\ValidationFailedException;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * ImageService
 *
 * Handles secure image uploads: MIME & extension validation, GD re-encoding
 * (stripping EXIF metadata and embedded scripts), resizing, and thumbnail generation.
 */
class ImageService
{
    protected array $allowedMimes = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    protected array $allowedExtensions = [
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    protected int $maxSizeBytes;

    public function __construct()
    {
        $maxMb = (int) env('UPLOAD_MAX_MB', 5);
        $this->maxSizeBytes = max(1, $maxMb) * 1024 * 1024;
    }

    /**
     * Processes and stores a product image:
     * - Re-encodes with GD to strip EXIF data.
     * - Resizes to max 1600px width/height.
     * - Generates a 400px thumbnail.
     * - Saves into public/uploads/products/.
     *
     * @return array{path: string, thumb_path: string}
     * @throws ValidationFailedException
     */
    public function storeProductImage(UploadedFile $file): array
    {
        $this->validateFile($file);

        $uploadDir = FCPATH . 'uploads/products';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = strtolower($file->getExtension());
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $baseName  = bin2hex(random_bytes(16));
        $mainName  = $baseName . '.' . $extension;
        $thumbName = $baseName . '_thumb.' . $extension;

        $mainPath  = $uploadDir . '/' . $mainName;
        $thumbPath = $uploadDir . '/' . $thumbName;

        // Process with GD: Re-encode, resize main (max 1600px), create thumb (max 400px)
        $this->reencodeAndResize($file->getTempName(), $mainPath, 1600, 1600);
        $this->reencodeAndResize($file->getTempName(), $thumbPath, 400, 400);

        return [
            'path'       => 'uploads/products/' . $mainName,
            'thumb_path' => 'uploads/products/' . $thumbName,
        ];
    }

    /**
     * Processes and stores a private payment proof image in writable/uploads/proofs/.
     * Validates by real finfo MIME type, never by client filename/extension.
     *
     * @return string Relative file path
     * @throws ValidationFailedException|EntityTooLargeException
     */
    public function storePaymentProof(UploadedFile $file): string
    {
        $extension = $this->validateProofFile($file);

        $uploadDir = WRITEPATH . 'uploads/proofs';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0700, true);
        }

        $fileName = bin2hex(random_bytes(20)) . '.' . $extension;
        $destPath = $uploadDir . '/' . $fileName;

        // Re-encode with GD to strip EXIF and prevent execution (falls back to direct copy if GD fails)
        $this->reencodeAndResize($file->getTempName(), $destPath, 2000, 2000, 'proof_image');

        return 'uploads/proofs/' . $fileName;
    }

    /**
     * Alias for storePaymentProof
     */
    public function savePaymentProof(UploadedFile $file): string
    {
        return $this->storePaymentProof($file);
    }

    /**
     * Validates payment proof upload size and real finfo MIME type.
     * Accepts files without extensions (e.g. filename "blob" or "receipt").
     *
     * @return string Normalized extension ('jpg', 'png', 'webp') derived from real MIME.
     * @throws ValidationFailedException|EntityTooLargeException
     */
    public function validateProofFile(UploadedFile $file): string
    {
        $isValid = $file->isValid() || (ENVIRONMENT === 'testing' && $file->getError() === UPLOAD_ERR_OK && file_exists($file->getTempName()));
        if (!$isValid) {
            $err = $file->getError();
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                throw new EntityTooLargeException('حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى');
            }
            throw new ValidationFailedException(['proof_image' => 'الملف المرفق ليس صورة صالحة، يرجى رفع صورة بصيغة JPG أو PNG أو WebP'], 'الملف المرفق ليس صورة صالحة، يرجى رفع صورة بصيغة JPG أو PNG أو WebP');
        }

        if ($file->getSize() > $this->maxSizeBytes) {
            throw new EntityTooLargeException('حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى');
        }

        // Check real MIME type using FileInfo, never relying on client-supplied extension
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getTempName());

        if (!in_array($realMime, $this->allowedMimes, true)) {
            throw new ValidationFailedException(['proof_image' => 'الملف المرفق ليس صورة صالحة، يرجى رفع صورة بصيغة JPG أو PNG أو WebP'], 'الملف المرفق ليس صورة صالحة، يرجى رفع صورة بصيغة JPG أو PNG أو WebP');
        }

        return match ($realMime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
    }

    /**
     * Validates product image upload size, real MIME type via finfo, and extension.
     */
    protected function validateFile(UploadedFile $file): void
    {
        $isValid = $file->isValid() || (ENVIRONMENT === 'testing' && $file->getError() === UPLOAD_ERR_OK && file_exists($file->getTempName()));
        if (!$isValid) {
            $err = $file->getError();
            if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
                throw new EntityTooLargeException('حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى');
            }
            throw new ValidationFailedException(['image' => 'فشل في رفع الملف: ' . $file->getErrorString()]);
        }

        if ($file->getSize() > $this->maxSizeBytes) {
            throw new EntityTooLargeException('حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى');
        }

        // Check real MIME type using FileInfo
        $finfo    = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($file->getTempName());

        if (!in_array($realMime, $this->allowedMimes, true)) {
            throw new ValidationFailedException(['image' => 'نوع الملف غير مدعوم، يرجى رفع صورة بصيغة JPG أو PNG أو WebP']);
        }

        $ext = strtolower($file->getExtension());
        if (!in_array($ext, $this->allowedExtensions, true)) {
            throw new ValidationFailedException(['image' => 'امتداد الملف غير مسموح به']);
        }
    }

    /**
     * Reads image dimensions WITHOUT decoding, rejects oversized pixel counts
     * (decompression bombs), then re-encodes with GD (strips EXIF and any payload).
     * There is deliberately no "copy the original" fallback: a file GD cannot decode
     * is rejected, never stored as-is.
     */
    protected function reencodeAndResize(string $sourcePath, string $targetPath, int $maxWidth, int $maxHeight, string $field = 'image'): void
    {
        $reject = static function (string $msg) use ($field): void {
            throw new ValidationFailedException([$field => $msg], $msg);
        };

        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo || empty($imageInfo[0]) || empty($imageInfo[1])) {
            $reject('تعذّرت قراءة الصورة، يرجى رفع صورة بصيغة JPG أو PNG أو WebP.');
        }

        $maxPixels = max(1, (int) env('UPLOAD_MAX_PIXELS', 40_000_000));
        if ((int) $imageInfo[0] * (int) $imageInfo[1] > $maxPixels) {
            $reject('أبعاد الصورة كبيرة جداً، يرجى رفع صورة أصغر.');
        }

        if (!extension_loaded('gd')) {
            log_message('critical', 'GD extension is not loaded; image uploads are rejected.');
            $reject('تعذّرت معالجة الصورة على الخادم حالياً، يرجى المحاولة لاحقاً.');
        }

        $srcWidth  = $imageInfo[0];
        $srcHeight = $imageInfo[1];
        $mime      = $imageInfo['mime'];

        // Create GD resource from source
        $srcImg = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : null,
            'image/png'  => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : null,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            default      => null,
        };

        if (!$srcImg) {
            log_message('warning', 'GD could not decode an uploaded {mime} image; rejected.', ['mime' => $mime]);
            $reject('تعذّرت قراءة الصورة، يرجى رفع صورة بصيغة JPG أو PNG أو WebP.');
        }

        // Calculate aspect-ratio preserving dimensions
        $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight, 1.0);
        $dstWidth  = max(1, (int) round($srcWidth * $ratio));
        $dstHeight = max(1, (int) round($srcHeight * $ratio));

        $dstImg = imagecreatetruecolor($dstWidth, $dstHeight);

        // Preserve PNG / WebP transparency
        if ($mime === 'image/png' || $mime === 'image/webp') {
            imagealphablending($dstImg, false);
            imagesavealpha($dstImg, true);
            $transparent = imagecolorallocatealpha($dstImg, 255, 255, 255, 127);
            imagefilledrectangle($dstImg, 0, 0, $dstWidth, $dstHeight, $transparent);
        }

        imagecopyresampled($dstImg, $srcImg, 0, 0, 0, 0, $dstWidth, $dstHeight, $srcWidth, $srcHeight);

        // Save re-encoded target
        $saved = match ($mime) {
            'image/jpeg' => function_exists('imagejpeg') ? @imagejpeg($dstImg, $targetPath, 85) : false,
            'image/png'  => function_exists('imagepng') ? @imagepng($dstImg, $targetPath, 8) : false,
            'image/webp' => function_exists('imagewebp') ? @imagewebp($dstImg, $targetPath, 85) : (function_exists('imagejpeg') ? @imagejpeg($dstImg, $targetPath, 85) : false),
            default      => false,
        };

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        if (!$saved) {
            log_message('error', 'GD failed to write re-encoded {mime} image; rejected.', ['mime' => $mime]);
            @unlink($targetPath);
            $reject('تعذّر حفظ الصورة، يرجى المحاولة مرة أخرى.');
        }
    }
}
