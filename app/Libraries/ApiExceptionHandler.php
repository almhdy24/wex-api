<?php

namespace App\Libraries;

use App\Exceptions\ApiException;
use App\Exceptions\TooManyRequestsException;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Throwable;

/**
 * Global Exception Handler for Wex API
 *
 * Catches all unhandled exceptions and formats them into
 * the standard JSON API error envelope.
 */
class ApiExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode
    ): void {
        $errors = null;
        $meta   = null;

        if ($exception instanceof ApiException) {
            $statusCode = $exception->getStatusCode();
            $message    = $exception->getMessage();
            $errors     = $exception->getErrors();

            if ($exception instanceof TooManyRequestsException) {
                $response->setHeader('Retry-After', (string) $exception->getRetryAfter());
            }
        } elseif ($exception instanceof PageNotFoundException) {
            $statusCode = 404;
            $message    = lang('Api.route_not_found');
        } else {
            // For general or system exceptions
            if ($statusCode < 400 || $statusCode > 599) {
                $statusCode = 500;
            }

            if (ENVIRONMENT === 'production') {
                $message = lang('Api.server_error');
            } else {
                $message = $exception->getMessage();
                if ($message === '') {
                    $message = lang('Api.server_error');
                }
                $meta = [
                    'exception' => get_class($exception),
                    'file'      => $exception->getFile(),
                    'line'      => $exception->getLine(),
                ];
            }

            // Log unexpected server errors (500)
            if ($statusCode >= 500) {
                log_message('error', '[{status}] {message} in {file}:{line}', [
                    'status'  => $statusCode,
                    'message' => $exception->getMessage(),
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                ]);
            }
        }

        $requestId = $request->getHeaderLine('X-Request-Id');
        if ($requestId === '') {
            $requestId = bin2hex(random_bytes(16));
        }

        $response->setHeader('X-Request-Id', $requestId);
        $response->setHeader('Content-Type', 'application/json; charset=UTF-8');
        $response->setStatusCode($statusCode);

        $payload = ApiResponse::format(
            success: false,
            message: $message,
            data: null,
            errors: $errors,
            meta: $meta
        );

        $response->setJSON($payload);

        // After-filters do not run for responses produced here, so apply the same CORS and
        // security headers explicitly (otherwise cross-origin clients see an opaque failure
        // and error pages lose nosniff/frame/CSP protection).
        try {
            (new \App\Filters\CorsFilter())->after($request, $response);
            (new \App\Filters\SecurityHeadersFilter())->after($request, $response);
        } catch (\Throwable) {
            // never let header decoration mask the original error
        }

        $response->send();

        if (ENVIRONMENT !== 'testing') {
            exit($exitCode);
        }
    }
}
