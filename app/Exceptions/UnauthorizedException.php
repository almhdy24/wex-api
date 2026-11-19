<?php

namespace App\Exceptions;

use Throwable;

class UnauthorizedException extends ApiException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        if ($message === '') {
            $message = lang('Api.unauthorized');
        }
        parent::__construct($message, 401, null, $previous);
    }
}
