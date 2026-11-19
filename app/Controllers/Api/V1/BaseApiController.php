<?php

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Exceptions\ApiException;
use App\Exceptions\ValidationFailedException;
use App\Libraries\ApiResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * BaseApiController
 *
 * Base controller for all API v1 endpoints. Thin controllers, HTTP concerns only.
 */
abstract class BaseApiController extends BaseController
{
    /**
     * CodeIgniter Controller remap hook.
     * Intercepts all action calls to catch any ApiException and convert
     * it to the standard JSON API error envelope.
     */
    public function _remap(string $method, ...$params)
    {
        try {
            return $this->{$method}(...$params);
        } catch (ApiException $e) {
            if ($e instanceof \App\Exceptions\TooManyRequestsException) {
                $this->response->setHeader('Retry-After', (string) $e->getRetryAfter());
            }

            return $this->respondError(
                message: $e->getMessage(),
                statusCode: $e->getStatusCode(),
                errors: $e->getErrors()
            );
        }
    }
    protected function respondSuccess(
        mixed $data = null,
        string $message = '',
        int $statusCode = 200,
        ?array $meta = null
    ): ResponseInterface {
        $payload = ApiResponse::format(
            success: true,
            message: $message,
            data: $data,
            errors: null,
            meta: $meta
        );

        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON($payload);
    }

    /**
     * Responds with standard 201 Created JSON envelope
     */
    protected function respondCreated(
        mixed $data = null,
        string $message = '',
        ?array $meta = null
    ): ResponseInterface {
        return $this->respondSuccess($data, $message, 201, $meta);
    }

    /**
     * Responds with standard 204 No Content
     */
    protected function respondNoContent(): ResponseInterface
    {
        return $this->response->setStatusCode(204);
    }

    /**
     * Responds with paginated data envelope
     */
    protected function respondPaginated(
        array $items,
        int $page,
        int $perPage,
        int $total,
        string $message = ''
    ): ResponseInterface {
        $meta = ApiResponse::pagination($page, $perPage, $total);

        return $this->respondSuccess($items, $message, 200, $meta);
    }

    /**
     * Responds with an error envelope
     */
    protected function respondError(
        string $message,
        int $statusCode = 400,
        ?array $errors = null,
        ?array $meta = null
    ): ResponseInterface {
        $payload = ApiResponse::format(
            success: false,
            message: $message,
            data: null,
            errors: $errors,
            meta: $meta
        );

        return $this->response
            ->setStatusCode($statusCode)
            ->setJSON($payload);
    }

    /**
     * Parses and validates JSON request body with maximum byte size check
     */
    protected function getJsonBody(int $maxSizeBytes = 1048576): array
    {
        $raw = $this->request->getBody();

        if (strlen($raw) > $maxSizeBytes) {
            throw new ApiException(lang('Api.validation_failed'), 413, [
                'body' => 'حجم البيانات المرسلة كبير جداً',
            ]);
        }

        if (trim($raw) === '') {
            return [];
        }

        try {
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) {
                throw new ValidationFailedException(['body' => 'صيغة البيانات غير صحيحة، يجب أن تكون كائن JSON']);
            }
            return $data;
        } catch (\JsonException $e) {
            throw new ValidationFailedException(['body' => 'صيغة JSON المرسلة غير صالحة']);
        }
    }

    /**
     * Extracts and validates pagination query parameters (?page=1&per_page=20)
     */
    protected function getPaginationParams(int $defaultPerPage = 20, int $maxPerPage = 50): array
    {
        $page = (int) $this->request->getGet('page');
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int) $this->request->getGet('per_page');
        if ($perPage < 1) {
            $perPage = $defaultPerPage;
        } elseif ($perPage > $maxPerPage) {
            $perPage = $maxPerPage;
        }

        return [$page, $perPage];
    }

    /**
     * Retrieves the authenticated user injected by AuthFilter
     */
    protected function getCurrentUser(): mixed
    {
        // AuthFilter sets 'auth_user' on the request
        return $this->request->auth_user ?? null;
    }

    /**
     * Alias for getCurrentUser()
     */
    protected function currentUser(): mixed
    {
        return $this->getCurrentUser();
    }
}
