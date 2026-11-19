<?php

namespace App\Exceptions;

use Throwable;

class ValidationFailedException extends ApiException
{
    public function __construct(?array $errors = null, string $message = '', ?Throwable $previous = null)
    {
        if ($message === '') {
            $message = lang('Api.validation_failed');
        }
        parent::__construct($message, 422, $errors, $previous);
    }
}
