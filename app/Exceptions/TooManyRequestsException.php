<?php

namespace App\Exceptions;

use Throwable;

class TooManyRequestsException extends ApiException
{
    protected int $retryAfter;

    public function __construct(int $retryAfter = 60, string $message = '', ?Throwable $previous = null)
    {
        if ($message === '') {
            $message = lang('Api.too_many_requests');
        }
        parent::__construct($message, 429, null, $previous);
        $this->retryAfter = $retryAfter;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
