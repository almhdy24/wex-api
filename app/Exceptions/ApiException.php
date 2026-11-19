<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Base API Exception for Wex API
 *
 * All application-level HTTP errors inherit from this class.
 */
class ApiException extends RuntimeException
{
    protected int $statusCode;
    protected ?array $errors;

    public function __construct(
        string $message = '',
        int $statusCode = 400,
        ?array $errors = null,
        ?Throwable $previous = null
    ) {
        if ($message === '') {
            $message = lang('Api.server_error');
        }

        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errors     = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): ?array
    {
        return $this->errors;
    }
}
