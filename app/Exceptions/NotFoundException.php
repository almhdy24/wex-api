<?php

namespace App\Exceptions;

use Throwable;

class NotFoundException extends ApiException
{
    public function __construct(string $message = '', ?Throwable $previous = null)
    {
        if ($message === '') {
            $message = lang('Api.not_found');
        }
        parent::__construct($message, 404, null, $previous);
    }
}
