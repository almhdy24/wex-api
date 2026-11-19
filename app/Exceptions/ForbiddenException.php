<?php

namespace App\Exceptions;

use Throwable;

class ForbiddenException extends ApiException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        if ($message === '') {
            $message = lang('Api.forbidden');
        }
        parent::__construct($message, 403, null, $previous);
    }
}
