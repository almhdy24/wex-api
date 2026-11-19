<?php

namespace App\Controllers\Api\V1;

use App\Exceptions\ValidationFailedException;
use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * AuthController
 *
 * Passwordless authentication endpoints (Google Sign-In & Email OTP).
 */
class AuthController extends BaseApiController
{
    protected AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * POST /api/v1/auth/google
     */
    public function google(): ResponseInterface
    {
        $body = $this->getJsonBody();

        if (empty($body['id_token'])) {
            throw new ValidationFailedException(['id_token' => 'رمز Google ID مطلوب']);
        }

        $userAgent = $this->request->getUserAgent()->getAgentString();
        $ip        = $this->request->getIPAddress();

        $data = $this->authService->signInWithGoogle(
            (string) $body['id_token'],
            $userAgent,
            $ip
        );

        return $this->respondSuccess($data, 'تم تسجيل الدخول بنجاح');
    }

    /**
     * POST /api/v1/auth/email/request
     */
    public function requestEmailOtp(): ResponseInterface
    {
        $body = $this->getJsonBody();

        if (empty($body['email'])) {
            throw new ValidationFailedException(['email' => 'البريد الإلكتروني مطلوب']);
        }

        $ip = $this->request->getIPAddress();

        $this->authService->requestEmailOtp((string) $body['email'], $ip);

        // Always return generic success message to prevent user enumeration
        return $this->respondSuccess(null, lang('Api.otp_sent'));
    }

    /**
     * POST /api/v1/auth/email/verify
     */
    public function verifyEmailOtp(): ResponseInterface
    {
        $body = $this->getJsonBody();

        $errors = [];
        if (empty($body['email'])) {
            $errors['email'] = 'البريد الإلكتروني مطلوب';
        }
        if (empty($body['code'])) {
            $errors['code'] = 'رمز التحقق مطلوب';
        }

        if (!empty($errors)) {
            throw new ValidationFailedException($errors);
        }

        $userAgent = $this->request->getUserAgent()->getAgentString();
        $ip        = $this->request->getIPAddress();
        $name      = isset($body['name']) ? (string) $body['name'] : null;

        $data = $this->authService->verifyEmailOtp(
            (string) $body['email'],
            (string) $body['code'],
            $name,
            $userAgent,
            $ip
        );

        return $this->respondSuccess($data, 'تم تأكيد الرمز وتسجيل الدخول بنجاح');
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh(): ResponseInterface
    {
        $body = $this->getJsonBody();

        if (empty($body['refresh_token'])) {
            throw new ValidationFailedException(['refresh_token' => 'رمز التحديث مطلوب']);
        }

        $userAgent = $this->request->getUserAgent()->getAgentString();
        $ip        = $this->request->getIPAddress();

        $data = $this->authService->refreshToken(
            (string) $body['refresh_token'],
            $userAgent,
            $ip
        );

        return $this->respondSuccess($data, 'تم تحديث جلسة الدخول بنجاح');
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(): ResponseInterface
    {
        $body = $this->getJsonBody();

        if (!empty($body['refresh_token'])) {
            $this->authService->logout((string) $body['refresh_token']);
        }

        return $this->respondSuccess(null, lang('Api.logged_out'));
    }

    /**
     * POST /api/v1/auth/logout-all
     */
    public function logoutAll(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $this->authService->logoutAll((int) $user->id);

        return $this->respondSuccess(null, lang('Api.logged_out_all'));
    }
}
