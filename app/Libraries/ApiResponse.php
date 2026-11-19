<?php

namespace App\Libraries;

use CodeIgniter\HTTP\ResponseInterface;

/**
 * Standard API Response Formatter
 *
 * Enforces the uniform JSON envelope:
 * {
 *   "success": bool,
 *   "message": string,
 *   "data": array|object|null,
 *   "errors": array|null,
 *   "meta": array|null
 * }
 */
class ApiResponse
{
    /**
     * Builds standard envelope array
     */
    public static function format(
        bool $success,
        string $message,
        mixed $data = null,
        ?array $errors = null,
        ?array $meta = null
    ): array {
        return [
            'success' => $success,
            'message' => $message,
            'data'    => $data,
            'errors'  => $errors,
            'meta'    => $meta,
        ];
    }

    /**
     * Helper for pagination metadata
     */
    public static function pagination(int $page, int $perPage, int $total): array
    {
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return [
            'page'        => $page,
            'per_page'    => $perPage,
            'total'       => $total,
            'total_pages' => $totalPages,
        ];
    }
}
