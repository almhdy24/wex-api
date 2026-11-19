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
            $response = Services::response();

            if ($allowed && $origin !== '') {
                $response->setHeader('Access-Control-Allow-Origin', $origin);
                $response->setHeader('Access-Control-Allow-Credentials', 'true');
                $response->setHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
                $response->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Request-Id, Accept, Accept-Language');
                $response->setHeader('Access-Control-Max-Age', '86400');
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
            $response->setHeader('Access-Control-Allow-Headers', 'Authorization, Content-Type, X-Request-Id, Accept, Accept-Language');
        }

        return $response;
    }

    private function isOriginAllowed(string $origin): bool
    {
        if ($origin === '') {
            return false;
        }

        $rawAllowed = env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000,http://localhost:8080');
        $origins    = array_map('trim', explode(',', $rawAllowed));

        return in_array($origin, $origins, true);
    }
}
