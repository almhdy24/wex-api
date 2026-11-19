<?php

namespace App\Services;

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
     *
     * @return string Relative file path
     * @throws ValidationFailedException
     */
    public function storePaymentProof(UploadedFile $file): string
    {
        $this->validateFile($file);

        $uploadDir = WRITEPATH . 'uploads/proofs';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0700, true);
        }

        $extension = strtolower($file->getExtension());
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }

        $fileName = bin2hex(random_bytes(20)) . '.' . $extension;
        $destPath = $uploadDir . '/' . $fileName;

        // Re-encode with GD to strip EXIF and prevent execution
        $this->reencodeAndResize($file->getTempName(), $destPath, 2000, 2000);

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
     * Validates upload size, real MIME type via finfo, and extension.
     */
    protected function validateFile(UploadedFile $file): void
    {
        $isValid = $file->isValid() || (ENVIRONMENT === 'testing' && $file->getError() === UPLOAD_ERR_OK && file_exists($file->getTempName()));
        if (!$isValid) {
            throw new ValidationFailedException(['image' => 'فشل في رفع الملف: ' . $file->getErrorString()]);
        }

        if ($file->getSize() > $this->maxSizeBytes) {
            $maxMb = (int) ($this->maxSizeBytes / (1024 * 1024));
            throw new ValidationFailedException(['image' => "حجم الملف يتجاوز الحد المسموح به ({$maxMb} ميجابايت)"]);
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
     * Reads image, resizes if larger than max dimensions, and writes fresh re-encoded image.
     */
    protected function reencodeAndResize(string $sourcePath, string $targetPath, int $maxWidth, int $maxHeight): void
    {
        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new ValidationFailedException(['image' => 'ملف الصورة غير صالح أو تالف']);
        }

        $srcWidth  = $imageInfo[0];
        $srcHeight = $imageInfo[1];
        $mime      = $imageInfo['mime'];

        // Create GD resource from source
        $srcImg = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png'  => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : null,
            default      => null,
        };

        if (!$srcImg) {
            throw new ValidationFailedException(['image' => 'تعذر معالجة الصورة عبر محرك الرسومات']);
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
            'image/jpeg' => imagejpeg($dstImg, $targetPath, 85),
            'image/png'  => imagepng($dstImg, $targetPath, 8),
            'image/webp' => function_exists('imagewebp') ? imagewebp($dstImg, $targetPath, 85) : imagejpeg($dstImg, $targetPath, 85),
            default      => false,
        };

        imagedestroy($srcImg);
        imagedestroy($dstImg);

        if (!$saved) {
            throw new ValidationFailedException(['image' => 'فشل في حفظ الصورة المعالجة']);
        }
    }
}
