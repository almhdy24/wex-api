<?php

namespace App\Controllers\Api\V1;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * PingController
 *
 * Basic health check endpoint for monitoring and smoke tests.
 */
class PingController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->respondSuccess(
            data: [
                'service'   => 'wex-api',
                'version'   => '1.0.0',
                'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
                'env'       => ENVIRONMENT,
            ],
            message: lang('Api.ping_success')
        );
    }
}
