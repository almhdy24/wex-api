<?php

namespace App\Libraries;

/**
 * Input coercion helpers for request payloads.
 */
final class Input
{
    /**
     * Strict boolean parse: true/1/"1"/"true"/"on"/"yes" => true, everything else false.
     * A plain (bool) cast turns the string "false" into true.
     */
    public static function bool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
