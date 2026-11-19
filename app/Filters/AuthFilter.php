<?php

namespace App\Filters;

use App\Exceptions\UnauthorizedException;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AuthFilter
 *
 * Validates the JWT Bearer access token, verifies the user is active,
 * and injects the authenticated user into the request.
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $header = $request->getHeaderLine('Authorization');

        if ($header === '' || !preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return \Config\Services::response()
                ->setStatusCode(401)
                ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                ->setJSON(\App\Libraries\ApiResponse::format(false, lang('Api.unauthorized')));
        }

        $token = trim($matches[1]);
        $tokenService = new TokenService();

        try {
            $payload = $tokenService->decodeAccessToken($token);
        } catch (\Throwable $e) {
            return \Config\Services::response()
                ->setStatusCode(401)
                ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                ->setJSON(\App\Libraries\ApiResponse::format(false, lang('Api.unauthorized')));
        }

        if (empty($payload->sub)) {
            return \Config\Services::response()
                ->setStatusCode(401)
                ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                ->setJSON(\App\Libraries\ApiResponse::format(false, lang('Api.unauthorized')));
        }

        $userModel = new UserModel();
        $user = $userModel->find($payload->sub);

        if (!$user) {
            return \Config\Services::response()
                ->setStatusCode(401)
                ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                ->setJSON(\App\Libraries\ApiResponse::format(false, lang('Api.unauthorized')));
        }

        if (!$user->is_active) {
            return \Config\Services::response()
                ->setStatusCode(401)
                ->setHeader('Content-Type', 'application/json; charset=UTF-8')
                ->setJSON(\App\Libraries\ApiResponse::format(false, lang('Api.account_inactive')));
        }

        // Inject the active authenticated user into the request object
        $request->auth_user = $user;

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return $response;
    }
}
