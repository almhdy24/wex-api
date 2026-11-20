<?php

namespace App\Exceptions;

use Throwable;

class TooManyRequestsException extends ApiException
{
    protected int $retryAfter;

    public function __construct(int|string $retryAfter = 60, string $message = '', ?Throwable $previous = null)
    {
        if (is_string($retryAfter) && $message === '') {
            $message = $retryAfter;
            $retryAfter = 60;
        } elseif ($message === '') {
            $message = 'تجاوزت الحد المسموح من المحاولات';
        }
        parent::__construct($message, 429, null, $previous);
        $this->retryAfter = (int) $retryAfter;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }
}
