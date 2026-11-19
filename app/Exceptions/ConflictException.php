<?php

namespace App\Exceptions;

use Throwable;

class ConflictException extends ApiException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        if ($message === '') {
            $message = lang('Api.conflict');
        }
        parent::__construct($message, 409, null, $previous);
    }
}
