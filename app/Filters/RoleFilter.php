<?php

namespace App\Filters;

use App\Exceptions\ForbiddenException;
use App\Exceptions\UnauthorizedException;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RoleFilter
 *
 * Verifies that the authenticated user possesses one of the required roles.
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $user = $request->auth_user ?? null;

        if (!$user) {
            return \Config\Services::response()
                ->setStatusCode(401)
                ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                ->setJSON(\App\Libraries\ApiResponse::format(false, lang('Api.unauthorized')));
        }

        if (empty($arguments)) {
            return null;
        }

        // Arguments can be passed like ['admin'] or ['staff', 'admin'] or 'staff,admin'
        $allowedRoles = [];
        foreach ($arguments as $arg) {
            foreach (explode(',', $arg) as $role) {
                $trimmed = trim($role);
                if ($trimmed !== '') {
                    $allowedRoles[] = $trimmed;
                }
            }
        }

        if (!in_array($user->role, $allowedRoles, true)) {
            return \Config\Services::response()
                ->setStatusCode(403)
                ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                ->setJSON(\App\Libraries\ApiResponse::format(false, lang('Api.forbidden')));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
