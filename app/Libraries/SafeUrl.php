<?php

namespace App\Libraries;

/**
 * SafeUrl
 *
 * Validates admin/staff-supplied link targets rendered as href on the storefront.
 * Allowed: same-site absolute paths ("/products/x") or http(s) URLs with a host.
 * Rejected: javascript:/data:/vbscript: and any other scheme, protocol-relative
 * ("//evil"), backslash tricks ("/\evil"), and control characters.
 */
final class SafeUrl
{
    public static function isSafeLink(?string $url): bool
    {
        if ($url === null || $url === '') {
            return true;
        }
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return false;
        }
        if ($url[0] === '/') {
            return !str_starts_with($url, '//');
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && !empty($parts['host'])
            && !isset($parts['user']);
    }
}
