<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\ApiException;
use App\Exceptions\UnauthorizedException;
use App\Exceptions\ValidationFailedException;
use App\Models\UserModel;
use Config\Wex;

/**
 * AuthService
 *
 * Coordinates Google Sign-In, Email OTP authentication, token rotation,
 * user account provisioning, and session termination.
 */
class AuthService
{
    protected UserModel $userModel;
    protected TokenService $tokenService;
    protected OtpService $otpService;
    protected GoogleAuthService $googleAuthService;
    protected MailService $mailService;

    public function __construct(
        ?UserModel $userModel = null,
        ?TokenService $tokenService = null,
        ?OtpService $otpService = null,
        ?GoogleAuthService $googleAuthService = null,
        ?MailService $mailService = null
    ) {
        $this->userModel         = $userModel ?? new UserModel();
        $this->tokenService      = $tokenService ?? new TokenService();
        $this->otpService        = $otpService ?? new OtpService();
        $this->googleAuthService = $googleAuthService ?? new GoogleAuthService();
        $this->mailService       = $mailService ?? new MailService();
    }

    /**
     * Authenticates user via Google OAuth ID token.
     */
    public function signInWithGoogle(string $idToken, ?string $userAgent = null, ?string $ip = null): array
    {
        $profile = $this->googleAuthService->verifyIdToken($idToken);
        $now     = gmdate('Y-m-d H:i:s');

        // 1. Find user by google_id
        $user = $this->userModel->where('google_id', $profile['google_id'])->first();

        // 2. If not found by google_id, check by email to link account
        if (!$user) {
            $user = $this->userModel->where('email', $profile['email'])->first();
            if ($user) {
                // Link Google ID to existing account
                $this->userModel->update($user->id, [
                    'google_id'         => $profile['google_id'],
                    'email_verified_at' => $user->email_verified_at ?? $now,
                    'avatar_url'        => $profile['avatar_url'] ?? $user->avatar_url,
                    'updated_at'        => $now,
                ]);
                $user = $this->userModel->find($user->id);
            }
        }

        // 3. If still not found, provision a new customer
        if (!$user) {
            $name = !empty($profile['name']) ? $profile['name'] : 'عميل ويكس';

            $userId = $this->userModel->insert([
                'name'                => $name,
                'email'               => $profile['email'],
                'phone'               => null,
                'google_id'           => $profile['google_id'],
                'avatar_url'          => $profile['avatar_url'],
                'role'                => Wex::ROLE_CUSTOMER,
                'email_verified_at'   => $now,
                'email_notifications' => 1,
                'is_active'           => 1,
                'last_login_at'       => $now,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            $user = $this->userModel->find($userId);
        } else {
            // Reject inactive accounts
            if (!$user->is_active) {
                throw new UnauthorizedException(lang('Api.account_inactive'));
            }

            // Update login time and profile if available
            $this->userModel->update($user->id, [
                'last_login_at' => $now,
                'avatar_url'    => $profile['avatar_url'] ?? $user->avatar_url,
                'updated_at'    => $now,
            ]);
            $user = $this->userModel->find($user->id);
        }

        return $this->buildAuthResponse($user, $userAgent, $ip);
    }

    /**
     * Dispatches an email OTP code for passwordless sign-in.
     */
    public function requestEmailOtp(string $email, ?string $ip = null): void
    {
        $email = strtolower(trim($email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationFailedException(['email' => 'البريد الإلكتروني المدخل غير صالح']);
        }

        $code = $this->otpService->generate($email, $ip);
        $this->mailService->sendOtp($email, $code);
    }

    /**
     * Verifies the email OTP code and issues tokens.
     */
    public function verifyEmailOtp(
        string $email,
        string $code,
        ?string $name = null,
        ?string $userAgent = null,
        ?string $ip = null
    ): array {
        $email = strtolower(trim($email));
        $now   = gmdate('Y-m-d H:i:s');

        // Check if new user and require name before consuming OTP
        $user = $this->userModel->where('email', $email)->first();
        if (!$user) {
            $name = trim((string) $name);
            if ($name === '') {
                throw new ValidationFailedException([
                    'name' => lang('Api.name_required_new_user'),
                ]);
            }
        }

        // Verify OTP (throws ValidationFailedException on failure)
        $this->otpService->verify($email, $code);

        if (!$user) {
            // New user requires a name
            $name = trim((string) $name);
            if ($name === '') {
                throw new ValidationFailedException([
                    'name' => lang('Api.name_required_new_user'),
                ]);
            }

            $userId = $this->userModel->insert([
                'name'                => $name,
                'email'               => $email,
                'phone'               => null,
                'google_id'           => null,
                'avatar_url'          => null,
                'role'                => Wex::ROLE_CUSTOMER,
                'email_verified_at'   => $now,
                'email_notifications' => 1,
                'is_active'           => 1,
                'last_login_at'       => $now,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            $user = $this->userModel->find($userId);
        } else {
            if (!$user->is_active) {
                throw new UnauthorizedException(lang('Api.account_inactive'));
            }

            $this->userModel->update($user->id, [
                'email_verified_at' => $user->email_verified_at ?? $now,
                'last_login_at'     => $now,
                'updated_at'        => $now,
            ]);
            $user = $this->userModel->find($user->id);
        }

        return $this->buildAuthResponse($user, $userAgent, $ip);
    }

    /**
     * Rotates refresh token and issues a fresh access token.
     */
    public function refreshToken(string $rawRefreshToken, ?string $userAgent = null, ?string $ip = null): array
    {
        $rotation = $this->tokenService->rotateRefreshToken($rawRefreshToken, $userAgent, $ip);
        $user = $this->userModel->find($rotation['user_id']);

        if (!$user || !$user->is_active) {
            throw new UnauthorizedException(lang('Api.account_inactive'));
        }

        $access = $this->tokenService->generateAccessToken($user);

        return [
            'access_token'       => $access['access_token'],
            'refresh_token'      => $rotation['refresh_token'],
            'token_type'         => 'Bearer',
            'expires_in'         => $access['expires_in'],
            'refresh_expires_in' => $rotation['refresh_expires_in'] ?? (30 * 86400),
            'user'               => $this->formatUserData($user),
        ];
    }

    /**
     * Revokes a single refresh token session.
     */
    public function logout(string $rawRefreshToken): void
    {
        $this->tokenService->revokeRefreshToken($rawRefreshToken);
    }

    /**
     * Revokes all active refresh token sessions for the authenticated user.
     */
    public function logoutAll(int $userId): void
    {
        $this->tokenService->revokeAllUserTokens($userId);
    }

    protected function buildAuthResponse(User $user, ?string $userAgent = null, ?string $ip = null): array
    {
        $access       = $this->tokenService->generateAccessToken($user);
        $refreshToken = $this->tokenService->generateRefreshToken((int) $user->id, null, $userAgent, $ip);

        return [
            'access_token'       => $access['access_token'],
            'refresh_token'      => $refreshToken,
            'token_type'         => 'Bearer',
            'expires_in'         => $access['expires_in'],
            'refresh_expires_in' => 30 * 86400,
            'user'               => $this->formatUserData($user),
        ];
    }

    public function formatUserData(User $user): array
    {
        return [
            'id'                  => (int) $user->id,
            'name'                => $user->name,
            'email'               => $user->email,
            'phone'               => $user->phone,
            'role'                => $user->role,
            'avatar_url'          => $user->avatar_url,
            'google_linked'       => !empty($user->google_id),
            'email_verified'      => !empty($user->email_verified_at),
            'email_notifications' => (bool) $user->email_notifications,
            'is_active'           => (bool) $user->is_active,
            'created_at'          => $user->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($user->created_at)) : null,
        ];
    }
}
