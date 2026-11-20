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

        $this->setRefreshTokenCookie($data['refresh_token'], (int) ($data['refresh_expires_in'] ?? 30 * 86400));

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

        $this->setRefreshTokenCookie($data['refresh_token'], (int) ($data['refresh_expires_in'] ?? 30 * 86400));

        return $this->respondSuccess($data, 'تم تأكيد الرمز وتسجيل الدخول بنجاح');
    }

    /**
     * POST /api/v1/auth/refresh
     */
    public function refresh(): ResponseInterface
    {
        $body = $this->getJsonBody();
        $refreshToken = !empty($body['refresh_token']) ? (string) $body['refresh_token'] : null;

        if ($refreshToken === null) {
            $cookieToken = $this->request->getCookie('wex_refresh_token');
            if (!empty($cookieToken)) {
                $refreshToken = (string) $cookieToken;
            } elseif ($cookieHeader = $this->request->getHeaderLine('Cookie')) {
                if (preg_match('/(?:^|;\s*)wex_refresh_token=([^;]+)/', $cookieHeader, $matches)) {
                    $refreshToken = urldecode($matches[1]);
                }
            }
        }

        if (empty($refreshToken)) {
            throw new ValidationFailedException(['refresh_token' => 'رمز التحديث مطلوب']);
        }

        $userAgent = $this->request->getUserAgent()->getAgentString();
        $ip        = $this->request->getIPAddress();

        $data = $this->authService->refreshToken(
            $refreshToken,
            $userAgent,
            $ip
        );

        $this->setRefreshTokenCookie($data['refresh_token'], (int) ($data['refresh_expires_in'] ?? 30 * 86400));

        return $this->respondSuccess($data, 'تم تحديث جلسة الدخول بنجاح');
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout(): ResponseInterface
    {
        $body = $this->getJsonBody();
        $refreshToken = !empty($body['refresh_token']) ? (string) $body['refresh_token'] : null;

        if ($refreshToken === null) {
            $cookieToken = $this->request->getCookie('wex_refresh_token');
            if (!empty($cookieToken)) {
                $refreshToken = (string) $cookieToken;
            } elseif ($cookieHeader = $this->request->getHeaderLine('Cookie')) {
                if (preg_match('/(?:^|;\s*)wex_refresh_token=([^;]+)/', $cookieHeader, $matches)) {
                    $refreshToken = urldecode($matches[1]);
                }
            }
        }

        if (!empty($refreshToken)) {
            $this->authService->logout($refreshToken);
        }

        $this->clearRefreshTokenCookie();

        return $this->respondSuccess(null, lang('Api.logged_out'));
    }

    /**
     * POST /api/v1/auth/logout-all
     */
    public function logoutAll(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $this->authService->logoutAll((int) $user->id);

        $this->clearRefreshTokenCookie();

        return $this->respondSuccess(null, lang('Api.logged_out_all'));
    }

    /**
     * Set HttpOnly Secure SameSite refresh token cookie for web clients.
     */
    protected function setRefreshTokenCookie(string $token, int $ttl): void
    {
        $secure = (bool) (env('COOKIE_SECURE', false) || $this->request->isSecure() || ENVIRONMENT === 'production');
        $this->response->setCookie(
            'wex_refresh_token',
            $token,
            $ttl,
            '',
            '/',
            '',
            $secure,
            true,
            'Lax'
        );
    }

    /**
     * Clear the refresh token cookie.
     */
    protected function clearRefreshTokenCookie(): void
    {
        $secure = (bool) (env('COOKIE_SECURE', false) || $this->request->isSecure() || ENVIRONMENT === 'production');
        $this->response->setCookie(
            'wex_refresh_token',
            '',
            0,
            '',
            '/',
            '',
            $secure,
            true,
            'Lax'
        );
    }
}
