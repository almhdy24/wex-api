<?php

namespace App\Controllers\Api\V1;

use App\Libraries\SpecSchema;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * GET /api/v1/spec-schema — the single source of truth for product specifications.
 * The admin form renders its inputs from it; the server validates against the same schema.
 */
class SpecSchemaController extends BaseApiController
{
    public function index(): ResponseInterface
    {
        return $this->respondSuccess(array_values(SpecSchema::all()), 'نماذج مواصفات المنتجات');
    }
}
