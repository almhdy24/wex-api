<?php

namespace App\Services\Push;

class PushResult
{
    public function __construct(
        public readonly bool $success,
        public readonly bool $expired = false,
        public readonly ?string $error = null,
        public readonly ?int $statusCode = null
    ) {
    }

    public static function ok(?int $statusCode = 201): self
    {
        return new self(true, false, null, $statusCode);
    }

    public static function expired(?string $error = null, ?int $statusCode = 410): self
    {
        return new self(false, true, $error, $statusCode);
    }

    public static function failure(string $error, ?int $statusCode = null): self
    {
        return new self(false, false, $error, $statusCode);
    }
}
