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
    protected int $reuseGraceSeconds;
    protected int $maxFamilyLifetimeDays;
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

        $graceSeconds = (int) env('REFRESH_REUSE_GRACE_SECONDS', 30);
        $this->reuseGraceSeconds = max(0, $graceSeconds);

        $maxDays = (int) env('JWT_REFRESH_MAX_LIFETIME_DAYS', 180);
        $this->maxFamilyLifetimeDays = max(1, $maxDays);
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
     * Sliding expiry: valid for refreshTtlSeconds (default 30 days) from NOW,
     * capped by the family's absolute lifetime limit (default 180 days).
     */
    public function generateRefreshToken(
        int $userId,
        ?string $familyId = null,
        ?string $userAgent = null,
        ?string $ip = null,
        ?string $familyCreatedAt = null
    ): string {
        $rawBytes = random_bytes(64);
        $rawToken = rtrim(strtr(base64_encode($rawBytes), '+/', '-_'), '=');
        $hash     = hash('sha256', $rawToken);

        $now = gmdate('Y-m-d H:i:s');
        if ($familyId === null || $familyId === '') {
            $familyId = bin2hex(random_bytes(16));
        }

        $familyCreated = ($familyCreatedAt !== null && $familyCreatedAt !== '')
            ? $familyCreatedAt
            : $now;

        // Sliding expiry: 30 days from now, but capped by the 180-day absolute family lifetime
        $familyCreatedTime = strtotime($familyCreated);
        $maxFamilyExpiresAt = $familyCreatedTime + ($this->maxFamilyLifetimeDays * 86400);
        $slidingExpiresAt = time() + $this->refreshTtlSeconds;

        $expiresAtTimestamp = min($slidingExpiresAt, $maxFamilyExpiresAt);
        $expiresAt = gmdate('Y-m-d H:i:s', $expiresAtTimestamp);

        $this->tokenModel->insert([
            'user_id'           => $userId,
            'family_id'         => $familyId,
            'token_hash'        => $hash,
            'user_agent'        => substr((string) $userAgent, 0, 255),
            'ip'                => substr((string) $ip, 0, 45),
            'expires_at'        => $expiresAt,
            'revoked_at'        => null,
            'replaced_by_id'    => null,
            'revoke_reason'     => null,
            'created_at'        => $now,
            'family_created_at' => $familyCreated,
        ]);

        return $rawToken;
    }

    /**
     * Rotates a refresh token.
     * - Grace window: if a token was already revoked due to rotation within REFRESH_REUSE_GRACE_SECONDS,
     *   reissues a fresh pair in the same family without revoking the family.
     * - Theft detection: if a token is reused outside the grace window or was revoked for other reasons,
     *   revokes all active tokens in the family with reason 'reuse_detected'.
     * - Sliding expiry: shifts the 30-day window forward from NOW on every refresh, capped at 180 days.
     *
     * @return array{user_id: int, refresh_token: string, refresh_expires_in: int}
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
        $nowTime = time();

        $familyCreatedAt = $record->family_created_at ?? $record->created_at;
        $familyCreatedTime = strtotime($familyCreatedAt);
        $maxFamilyExpiresAt = $familyCreatedTime + ($this->maxFamilyLifetimeDays * 86400);

        // Check absolute cap first
        if ($nowTime >= $maxFamilyExpiresAt) {
            $this->tokenModel
                ->where('family_id', $record->family_id)
                ->where('revoked_at IS NULL')
                ->set([
                    'revoked_at'    => $now,
                    'revoke_reason' => 'expired_cap',
                ])
                ->update();

            throw new UnauthorizedException(lang('Api.refresh_token_invalid'));
        }

        // If the token is already revoked
        if ($record->isRevoked()) {
            $revokedTimestamp = strtotime($record->revoked_at);
            $elapsedSinceRevoke = $nowTime - $revokedTimestamp;

            // GRACE WINDOW: If revoked due to rotation AND within grace window, allow reissue in same family
            if ($record->revoke_reason === 'rotated' && $elapsedSinceRevoke <= $this->reuseGraceSeconds) {
                // Issue a fresh replacement in the same family, keeping family alive
                $newRawToken = $this->generateRefreshToken(
                    (int) $record->user_id,
                    $record->family_id,
                    $userAgent,
                    $ip,
                    $familyCreatedAt
                );

                $newHash = hash('sha256', $newRawToken);
                $newRecord = $this->tokenModel->where('token_hash', $newHash)->first();

                $remainingSeconds = max(0, strtotime($newRecord->expires_at) - $nowTime);

                return [
                    'user_id'            => (int) $record->user_id,
                    'refresh_token'      => $newRawToken,
                    'refresh_expires_in' => $remainingSeconds,
                ];
            }

            // Outside grace window OR revoked for other reasons -> Theft detected
            $this->tokenModel
                ->where('family_id', $record->family_id)
                ->where('revoked_at IS NULL')
                ->set([
                    'revoked_at'    => $now,
                    'revoke_reason' => 'reuse_detected',
                ])
                ->update();

            throw new UnauthorizedException(lang('Api.refresh_token_invalid'));
        }

        // Check if expired
        if ($record->isExpired()) {
            throw new UnauthorizedException(lang('Api.refresh_token_invalid'));
        }

        // Generate new token in the same family with sliding expiry
        $newRawToken = $this->generateRefreshToken(
            (int) $record->user_id,
            $record->family_id,
            $userAgent,
            $ip,
            $familyCreatedAt
        );

        $newHash = hash('sha256', $newRawToken);
        $newRecord = $this->tokenModel->where('token_hash', $newHash)->first();

        // Mark previous token as revoked and replaced with reason 'rotated'
        $this->tokenModel->update($record->id, [
            'revoked_at'     => $now,
            'replaced_by_id' => $newRecord ? $newRecord->id : null,
            'revoke_reason'  => 'rotated',
        ]);

        $remainingSeconds = max(0, strtotime($newRecord->expires_at) - $nowTime);

        return [
            'user_id'            => (int) $record->user_id,
            'refresh_token'      => $newRawToken,
            'refresh_expires_in' => $remainingSeconds,
        ];
    }

    /**
     * Revokes a single refresh token (used on logout).
     */
    public function revokeRefreshToken(string $rawToken, string $reason = 'logout'): bool
    {
        $hash = hash('sha256', $rawToken);
        $record = $this->tokenModel->where('token_hash', $hash)->first();

        if (!$record) {
            return false;
        }

        return (bool) $this->tokenModel->update($record->id, [
            'revoked_at'    => gmdate('Y-m-d H:i:s'),
            'revoke_reason' => $reason,
        ]);
    }

    /**
     * Revokes all active refresh tokens for a user (used on logout-all / delete account).
     */
    public function revokeAllUserTokens(int $userId, string $reason = 'logout_all'): bool
    {
        return (bool) $this->tokenModel
            ->where('user_id', $userId)
            ->where('revoked_at IS NULL')
            ->set([
                'revoked_at'    => gmdate('Y-m-d H:i:s'),
                'revoke_reason' => $reason,
            ])
            ->update();
    }
}
