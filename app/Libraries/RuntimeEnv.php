<?php

namespace App\Libraries;

/** Single production check for test-only features (honours CI_ENVIRONMENT set at runtime). */
final class RuntimeEnv
{
    public static function isProduction(): bool
    {
        $env = strtolower(trim((string) (getenv('CI_ENVIRONMENT') ?: (defined('ENVIRONMENT') ? ENVIRONMENT : ''))));
        return $env === 'production' || (defined('ENVIRONMENT') && ENVIRONMENT === 'production');
    }
}
