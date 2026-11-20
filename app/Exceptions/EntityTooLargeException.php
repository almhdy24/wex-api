<?php

namespace App\Exceptions;

use Throwable;

/**
 * EntityTooLargeException (HTTP 413)
 *
 * Thrown when uploaded file or request payload exceeds post_max_size,
 * upload_max_filesize, or application size constraints.
 */
class EntityTooLargeException extends ApiException
{
    public function __construct(
        string $message = 'حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى',
        ?array $errors = null,
        ?Throwable $previous = null
    ) {
        if ($message === '') {
            $message = 'حجم الملف أكبر من المسموح، صغّر الصورة وحاول مرة أخرى';
        }
        parent::__construct($message, 413, $errors, $previous);
    }
}
