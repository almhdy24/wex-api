<?php

namespace App\Libraries;

/**
 * PushEndpointPolicy
 *
 * Web Push endpoints are URLs supplied by the client that the server later POSTs to.
 * Accepting any URL turns subscription into an SSRF primitive (internal services,
 * cloud metadata). Only HTTPS endpoints on the browser vendors' push services pass.
 * Extra hosts can be allowed with PUSH_ENDPOINT_EXTRA_HOSTS (comma separated suffixes).
 */
final class PushEndpointPolicy
{
    /** Host suffixes of the public Web Push services used by mainstream browsers. */
    private const HOST_SUFFIXES = [
        'fcm.googleapis.com',               // Chrome, Edge (Chromium), Samsung, Opera
        'android.googleapis.com',
        'updates.push.services.mozilla.com', // Firefox
        'push.services.mozilla.com',
        'notify.windows.com',               // legacy Edge / WNS
        'push.apple.com',                   // Safari (web.push.apple.com)
    ];

    public static function isAllowed(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return false;
        }
        if (isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            return false;
        }

        $host     = strtolower(rtrim($parts['host'], '.'));
        $suffixes = self::HOST_SUFFIXES;
        $extra    = (string) env('PUSH_ENDPOINT_EXTRA_HOSTS', '');
        foreach (array_filter(array_map('trim', explode(',', $extra))) as $s) {
            $suffixes[] = strtolower($s);
        }

        foreach ($suffixes as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return true;
            }
        }

        return false;
    }
}
