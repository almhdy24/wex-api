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
        // Don't throttle in testing environment
        if (ENVIRONMENT === 'testing') {
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

            throw new TooManyRequestsException($retryAfter);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
