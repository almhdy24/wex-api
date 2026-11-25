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

        return $this->respondSuccess($this->filterAuthData($data), 'تم تسجيل الدخول بنجاح');
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

        return $this->respondSuccess($this->filterAuthData($data), 'تم تأكيد الرمز وتسجيل الدخول بنجاح');
    }

    /**
     * POST /api/v1/auth/testing-login
     * Strictly guarded by testing environment. Never executes in production.
     */
    public function testingLogin(): ResponseInterface
    {
        $env = getenv('CI_ENVIRONMENT') ?: (defined('ENVIRONMENT') ? ENVIRONMENT : 'development');
        if (strtolower($env) === 'production') {
            return $this->respondError('غير متاح في هذه البيئة', 404);
        }

        $body = $this->getJsonBody();
        $email = !empty($body['email']) ? (string) $body['email'] : 'customer@wex.sd';
        $user = (new \App\Models\UserModel())->where('email', $email)->first();
        if (!$user) {
            return $this->respondError('المستخدم غير موجود', 404);
        }

        $userAgent = $this->request->getUserAgent()->getAgentString();
        $ip = $this->request->getIPAddress();
        $tokenService = new \App\Services\TokenService();
        $access       = $tokenService->generateAccessToken($user);
        $refreshToken = $tokenService->generateRefreshToken((int) $user->id, null, $userAgent, $ip);

        $this->setRefreshTokenCookie($refreshToken, 30 * 86400);

        $data = [
            'user'         => [
                'id'       => (int) $user->id,
                'name'     => $user->name,
                'email'    => $user->email,
                'phone'    => $user->phone,
                'role'     => $user->role,
            ],
            'access_token' => $access['access_token'],
            'refresh_token'=> $refreshToken,
            'token_type'   => 'Bearer',
            'expires_in'   => $access['expires_in'] ?? 900,
        ];

        return $this->respondSuccess($data, 'تم تسجيل الدخول بنجاح لبيئة الاختبار');
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
            throw new \App\Exceptions\UnauthorizedException(lang('Api.refresh_token_invalid'));
        }

        $userAgent = $this->request->getUserAgent()->getAgentString();
        $ip        = $this->request->getIPAddress();

        $data = $this->authService->refreshToken(
            $refreshToken,
            $userAgent,
            $ip
        );

        $this->setRefreshTokenCookie($data['refresh_token'], (int) ($data['refresh_expires_in'] ?? 30 * 86400));

        return $this->respondSuccess($this->filterAuthData($data), 'تم تحديث جلسة الدخول بنجاح');
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
     * Set HttpOnly SameSite refresh token cookie for web clients.
     * Host-only cookie (empty domain) ensures support for localhost and LAN IPs on Android.
     */
    protected function setRefreshTokenCookie(string $token, int $ttl): void
    {
        $isHttps = $this->request->isSecure()
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '');

        $cookieSecure = env('COOKIE_SECURE');
        $secure = $cookieSecure !== null
            ? filter_var($cookieSecure, FILTER_VALIDATE_BOOLEAN)
            : $isHttps;

        $samesite = (string) env('COOKIE_SAMESITE', 'Lax');

        if (strcasecmp($samesite, 'None') === 0 && !$secure) {
            log_message('warning', 'SameSite=None cookies require the Secure attribute; set COOKIE_SECURE=true or serve over HTTPS.');
        }

        $this->response->setCookie(
            'wex_refresh_token',
            $token,
            $ttl,
            '',
            '/',
            '',
            $secure,
            true,
            $samesite
        );
    }

    /**
     * Clear the refresh token cookie.
     */
    protected function clearRefreshTokenCookie(): void
    {
        $isHttps = $this->request->isSecure()
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '');

        $cookieSecure = env('COOKIE_SECURE');
        $secure = $cookieSecure !== null
            ? filter_var($cookieSecure, FILTER_VALIDATE_BOOLEAN)
            : $isHttps;

        $samesite = (string) env('COOKIE_SAMESITE', 'Lax');

        $this->response->setCookie(
            'wex_refresh_token',
            '',
            0,
            '',
            '/',
            '',
            $secure,
            true,
            $samesite
        );
    }

    /**
     * Determines whether refresh_token should be returned in JSON body.
     * Web clients rely on HttpOnly cookie by default. Native/mobile clients
     * may explicitly request the refresh_token via header or body flag.
     */
    protected function shouldReturnRefreshToken(): bool
    {
        $clientHeader = $this->request->getHeaderLine('X-Client');
        if (strcasecmp($clientHeader, 'native') === 0) {
            return true;
        }

        $body = $this->getJsonBody();
        if (!empty($body['return_refresh_token'])) {
            return true;
        }

        // Keep compatibility for non-cookie API clients passing refresh_token in request body
        if (!empty($body['refresh_token'])) {
            return true;
        }

        return false;
    }

    /**
     * Filters auth payload data before responding.
     */
    protected function filterAuthData(array $data): array
    {
        if (!$this->shouldReturnRefreshToken()) {
            unset($data['refresh_token']);
        }
        return $data;
    }
}
