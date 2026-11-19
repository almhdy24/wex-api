<?php

namespace App\Exceptions;

use Throwable;

class ValidationFailedException extends ApiException
{
    public function __construct(mixed $errors = null, mixed $message = '', ?Throwable $previous = null)
    {
        // Handle swapped parameters if message is passed first as string
        if (is_string($errors) && (is_array($message) || $message === '')) {
            $actualMessage = $errors;
            $actualErrors  = is_array($message) ? $message : null;
        } else {
            $actualErrors  = is_array($errors) ? $errors : null;
            $actualMessage = is_string($message) && $message !== '' ? $message : lang('Api.validation_failed');
        }

        parent::__construct($actualMessage, 422, $actualErrors, $previous);
    }
}
