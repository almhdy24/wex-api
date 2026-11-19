<?php

namespace App\Libraries;

/**
 * PhoneValidator
 *
 * Validates and normalizes Sudanese mobile numbers to international format (+249XXXXXXXXX).
 */
class PhoneValidator
{
    /**
     * Normalizes a Sudanese phone number to +249XXXXXXXXX format, or returns null if invalid.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        // Strip spaces, dashes, and parentheses
        $cleaned = preg_replace('/[\s\-\(\)]+/', '', trim($phone));

        // Pattern 1: +249 followed by 9 or 1 and 8 digits
        if (preg_match('/^\+249(9|1)\d{8}$/', $cleaned)) {
            return $cleaned;
        }

        // Pattern 2: 00249 followed by 9 or 1 and 8 digits
        if (preg_match('/^00249(9|1)\d{8}$/', $cleaned, $matches)) {
            return '+249' . substr($cleaned, 5);
        }

        // Pattern 3: 249 followed by 9 or 1 and 8 digits
        if (preg_match('/^249(9|1)\d{8}$/', $cleaned)) {
            return '+' . $cleaned;
        }

        // Pattern 4: 09XXXXXXXX or 01XXXXXXXX (10 digits)
        if (preg_match('/^0(9|1)\d{8}$/', $cleaned)) {
            return '+249' . substr($cleaned, 1);
        }

        // Pattern 5: 9XXXXXXXX or 1XXXXXXXX (9 digits without leading 0)
        if (preg_match('/^(9|1)\d{8}$/', $cleaned)) {
            return '+249' . $cleaned;
        }

        return null;
    }

    /**
     * Checks if a phone number is a valid Sudanese mobile number.
     */
    public static function isValid(?string $phone): bool
    {
        return self::normalize($phone) !== null;
    }
}
