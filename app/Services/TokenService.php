<?php

namespace App\Services;

use App\Entities\RefreshToken;
use App\Entities\User;
use App\Exceptions\UnauthorizedException;
use App\Models\RefreshTokenModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use RuntimeException;

/**
 * TokenService
 *
 * Manages JWT HS256 access tokens and rotating refresh tokens with theft detection.
 */
class TokenService
{
    protected string $jwtSecret;
    protected int $accessTtlSeconds;
    protected int $refreshTtlSeconds;
    protected RefreshTokenModel $tokenModel;

    public function __construct(?RefreshTokenModel $tokenModel = null)
    {
        $this->tokenModel = $tokenModel ?? new RefreshTokenModel();

        $secret = env('JWT_SECRET', '');
        if (ENVIRONMENT === 'production' && strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET must be at least 32 characters in production.');
        }

        // Fallback secret for local dev if unset
        $this->jwtSecret = $secret !== '' ? $secret : 'wex-dev-secret-key-must-be-at-least-32-chars-long!';

        $accessMinutes = (int) env('JWT_ACCESS_TTL_MINUTES', 15);
        $this->accessTtlSeconds = max(1, $accessMinutes) * 60;

        $refreshDays = (int) env('JWT_REFRESH_TTL_DAYS', 30);
        $this->refreshTtlSeconds = max(1, $refreshDays) * 86400;
    }

    /**
     * Generates a new HS256 JWT access token for a user.
     */
    public function generateAccessToken(User $user): array
    {
        $now = time();
        $expiresAt = $now + $this->accessTtlSeconds;

        $payload = [
            'iss'  => env('APP_URL', 'http://localhost:3000'),
            'sub'  => (int) $user->id,
            'role' => $user->role,
            'iat'  => $now,
            'exp'  => $expiresAt,
            'jti'  => bin2hex(random_bytes(16)),
        ];

        $token = JWT::encode($payload, $this->jwtSecret, 'HS256');

        return [
            'access_token' => $token,
            'token_type'   => 'Bearer',
            'expires_in'   => $this->accessTtlSeconds,
        ];
    }

    /**
     * Decodes and validates a JWT access token.
     *
     * @throws UnauthorizedException
     */
    public function decodeAccessToken(string $token): object
    {
        try {
            return JWT::decode($token, new Key($this->jwtSecret, 'HS256'));
        } catch (\Throwable $e) {
            throw new UnauthorizedException(lang('Api.unauthorized'), $e);
        }
    }

    /**
     * Generates and stores a new refresh token.
     */
    public function generateRefreshToken(
        int $userId,
        ?string $familyId = null,
        ?string $userAgent = null,
        ?string $ip = null
    ): string {
        $rawBytes = random_bytes(64);
        $rawToken = rtrim(strtr(base64_encode($rawBytes), '+/', '-_'), '=');
        $hash     = hash('sha256', $rawToken);

        if ($familyId === null || $familyId === '') {
            $familyId = bin2hex(random_bytes(16));
        }

        $now       = gmdate('Y-m-d H:i:s');
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $this->refreshTtlSeconds);

        $this->tokenModel->insert([
            'user_id'        => $userId,
            'family_id'      => $familyId,
            'token_hash'     => $hash,
            'user_agent'     => substr((string) $userAgent, 0, 255),
            'ip'             => substr((string) $ip, 0, 45),
            'expires_at'     => $expiresAt,
            'revoked_at'     => null,
            'replaced_by_id' => null,
            'created_at'     => $now,
        ]);

        return $rawToken;
    }

    /**
     * Rotates a refresh token.
     * Implements family-based theft detection: if an already-revoked token is presented,
     * the entire family of refresh tokens is immediately revoked.
     *
     * @return array{user_id: int, refresh_token: string}
     * @throws UnauthorizedException
     */
    public function rotateRefreshToken(
        string $rawToken,
        ?string $userAgent = null,
        ?string $ip = null
    ): array {
        $hash = hash('sha256', $rawToken);
        $record = $this->tokenModel->where('token_hash', $hash)->first();

        if (!$record) {
            throw new UnauthorizedException(lang('Api.refresh_token_invalid'));
        }

        $now = gmdate('Y-m-d H:i:s');

        // THEFT DETECTION: If a revoked token is reused, compromise is suspected.
        if ($record->isRevoked()) {
            $this->tokenModel
                ->where('family_id', $record->family_id)
                ->where('revoked_at IS NULL')
                ->set(['revoked_at' => $now])
                ->update();

            throw new UnauthorizedException(lang('Api.refresh_token_invalid'));
        }

        // Check if expired
        if ($record->isExpired()) {
            throw new UnauthorizedException(lang('Api.refresh_token_invalid'));
        }

        // Generate new token in the same family
        $newRawToken = $this->generateRefreshToken(
            (int) $record->user_id,
            $record->family_id,
            $userAgent,
            $ip
        );

        $newHash = hash('sha256', $newRawToken);
        $newRecord = $this->tokenModel->where('token_hash', $newHash)->first();

        // Mark previous token as revoked and replaced
        $this->tokenModel->update($record->id, [
            'revoked_at'     => $now,
            'replaced_by_id' => $newRecord ? $newRecord->id : null,
        ]);

        return [
            'user_id'       => (int) $record->user_id,
            'refresh_token' => $newRawToken,
        ];
    }

    /**
     * Revokes a single refresh token (used on logout).
     */
    public function revokeRefreshToken(string $rawToken): bool
    {
        $hash = hash('sha256', $rawToken);
        $record = $this->tokenModel->where('token_hash', $hash)->first();

        if (!$record) {
            return false;
        }

        return (bool) $this->tokenModel->update($record->id, [
            'revoked_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Revokes all active refresh tokens for a user (used on logout-all / delete account).
     */
    public function revokeAllUserTokens(int $userId): bool
    {
        return (bool) $this->tokenModel
            ->where('user_id', $userId)
            ->where('revoked_at IS NULL')
            ->set(['revoked_at' => gmdate('Y-m-d H:i:s')])
            ->update();
    }
}
