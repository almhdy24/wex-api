<?php

namespace App\Filters;

use App\Exceptions\TooManyRequestsException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * ThrottleFilter
 *
 * Implements token-bucket rate limiting via CodeIgniter Throttler.
 * Returns 429 Too Many Requests when limits are exceeded.
 */
class ThrottleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Don't throttle in testing environment or local development on loopback
        $ip = $request->getIPAddress();
        if (ENVIRONMENT === 'testing' || (ENVIRONMENT === 'development' && ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost'))) {
            return null;
        }

        $throttler = Services::throttler();

        $capacity = isset($arguments[0]) ? (int) $arguments[0] : 120;
        $seconds  = isset($arguments[1]) ? (int) $arguments[1] : 60;
        $name     = isset($arguments[2]) ? (string) $arguments[2] : 'global';

        $ip  = $request->getIPAddress();
        $key = md5('throttle_' . $name . '_' . $ip);

        if ($throttler->check($key, $capacity, $seconds) === false) {
            $retryAfter = $throttler->getTokenTime();
            if ($retryAfter < 1) {
                $retryAfter = 60;
            }

            return Services::response()
                ->setStatusCode(429)
                ->setHeader('Retry-After', (string) $retryAfter)
                ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                ->setJSON(\App\Libraries\ApiResponse::format(false, lang('Api.too_many_requests')));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
