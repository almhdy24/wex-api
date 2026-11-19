<?php

namespace App\Controllers\Api\V1;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * ErrorController
 *
 * Handles fallback and 404 routes, ensuring all non-matching routes
 * return the standard JSON envelope.
 */
class ErrorController extends BaseApiController
{
    public function notFound(): ResponseInterface
    {
        return $this->respondError(
            message: lang('Api.route_not_found'),
            statusCode: 404
        );
    }
}
