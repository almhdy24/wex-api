<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * CorsFilter
 *
 * Enforces CORS allowlist from CORS_ALLOWED_ORIGINS in .env.
 * Supports local development and LAN IP testing on Android devices.
 * Handles OPTIONS preflight requests immediately.
 */
class CorsFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');

        // Check if origin is allowed
        $allowed = $this->isOriginAllowed($origin);

        // Handle preflight OPTIONS request
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = service('response', null, false);

            if ($allowed && $origin !== '') {
                $response->setHeader('Access-Control-Allow-Origin', $origin);
                $response->setHeader('Access-Control-Allow-Credentials', 'true');
                $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Request-Id, Accept, Accept-Language, X-Requested-With, Idempotency-Key');
                $response->setHeader('Access-Control-Expose-Headers', 'Retry-After, X-Request-Id, Date');
                $response->setHeader('Access-Control-Max-Age', '86400');
                $response->setHeader('Vary', 'Origin');
            }

            return $response->setStatusCode(204);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $origin = $request->getHeaderLine('Origin');

        if ($origin !== '' && $this->isOriginAllowed($origin)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
            $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
            $response->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Request-Id, Accept, Accept-Language, X-Requested-With, Idempotency-Key');
            $response->setHeader('Access-Control-Expose-Headers', 'Retry-After, X-Request-Id, Date');
            $response->setHeader('Vary', 'Origin');
        }

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        if ($origin === '') {
            return false;
        }

        $rawAllowed = env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:8080,http://localhost:5371,http://127.0.0.1:5371,http://localhost:5173,http://127.0.0.1:5173');
        $origins    = array_map('trim', explode(',', $rawAllowed));

        if (in_array($origin, $origins, true)) {
            return true;
        }

        // In non-production environments, dynamically permit local/LAN development origins
        if (ENVIRONMENT !== 'production') {
            $parsed = parse_url($origin);
            if (isset($parsed['host'])) {
                $host = $parsed['host'];
                if ($host === 'localhost' || $host === '127.0.0.1' || $host === '10.0.2.2') {
                    return true;
                }
                // Check private network IP ranges: 192.168.0.0/16, 10.0.0.0/8, 172.16.0.0/12
                if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    if (preg_match('/^(?:192\.168\.|10\.|172\.(?:1[6-9]|2[0-9]|3[0-1])\.)/', $host)) {
                        return true;
                    }
                }
                // Support .local mDNS names
                if (str_ends_with($host, '.local')) {
                    return true;
                }
            }
        }

        return false;
    }
}
