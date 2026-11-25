<?php

namespace App\Services;

use App\Entities\OtpCode;
use App\Exceptions\ApiException;
use App\Exceptions\TooManyRequestsException;
use App\Exceptions\ValidationFailedException;
use App\Models\OtpCodeModel;

/**
 * OtpService
 *
 * Secure 6-digit one-time password service with HMAC hashing, attempt limits,
 * resend cooldowns, and single-use enforcement.
 */
class OtpService
{
    protected OtpCodeModel $otpModel;
    protected string $secretKey;
    protected int $ttlSeconds = 600; // 10 minutes
    protected int $cooldownSeconds = 60; // 60 seconds
    protected int $maxAttempts = 5;
    protected int $maxCodesPerHour = 5;

    public function __construct(?OtpCodeModel $otpModel = null)
    {
        $this->otpModel  = $otpModel ?? new OtpCodeModel();
        $this->secretKey = env('encryption.key', env('JWT_SECRET', 'wex-default-otp-secret-key'));
    }

    /**
     * Generates a new 6-digit OTP code for the given email.
     * Enforces 60-second cooldown and burns previous active codes.
     *
     * @return string The raw 6-digit code (to be sent via email, never stored in plaintext)
     * @throws TooManyRequestsException
     */
    public function generate(string $email, ?string $ip = null): string
    {
        $email = strtolower(trim($email));
        $now   = time();

        // 1. Check resend cooldown (60 seconds)
        $latest = $this->otpModel
            ->where('email', $email)
            ->orderBy('id', 'DESC')
            ->first();

        $isLocalDev = $this->isRateLimitExempt($ip);
        if (!$isLocalDev && $latest && (strtotime($latest->created_at) + $this->cooldownSeconds) > $now) {
            $waitSeconds = ($this->cooldownSeconds - ($now - strtotime($latest->created_at)));
            throw new TooManyRequestsException($waitSeconds, lang('Api.otp_cooldown'));
        }

        // 1b. Per-email hourly cap (independent of client IP) to bound brute-force via code rotation
        if (!$isLocalDev) {
            $issuedLastHour = $this->otpModel
                ->where('email', $email)
                ->where('created_at >=', gmdate('Y-m-d H:i:s', $now - 3600))
                ->countAllResults();
            if ($issuedLastHour >= $this->maxCodesPerHour) {
                throw new TooManyRequestsException(3600, 'تجاوزت الحد المسموح من طلبات رمز التحقق، يرجى المحاولة لاحقاً.');
            }
        }

        // 2. Invalidate any existing active codes for this email (only the latest is valid)
        $this->otpModel
            ->where('email', $email)
            ->where('consumed_at IS NULL')
            ->set(['consumed_at' => gmdate('Y-m-d H:i:s')])
            ->update();

        // 3. Generate 6-digit cryptographically secure code
        $rawCode  = (string) random_int(100000, 999999);
        $codeHash = hash_hmac('sha256', $rawCode, $this->secretKey);

        $this->otpModel->insert([
            'email'       => $email,
            'code_hash'   => $codeHash,
            'attempts'    => 0,
            'expires_at'  => gmdate('Y-m-d H:i:s', $now + $this->ttlSeconds),
            'consumed_at' => null,
            'ip'          => substr((string) $ip, 0, 45),
            'created_at'  => gmdate('Y-m-d H:i:s', $now),
        ]);

        return $rawCode;
    }

    /**
     * Cooldown / hourly caps are skipped only for automated tests and loopback development.
     */
    protected function isRateLimitExempt(?string $ip): bool
    {
        return ENVIRONMENT === 'testing'
            || (ENVIRONMENT === 'development' && in_array($ip, ['127.0.0.1', '::1', 'localhost', null], true));
    }

    /**
     * Verifies the provided 6-digit code against the stored hash.
     *
     * @throws ValidationFailedException
     */
    public function verify(string $email, string $code): bool
    {
        $email = strtolower(trim($email));
        $code  = trim($code);

        $record = $this->otpModel
            ->where('email', $email)
            ->where('consumed_at IS NULL')
            ->orderBy('id', 'DESC')
            ->first();

        if (!$record) {
            throw new ValidationFailedException(['code' => lang('Api.otp_invalid')]);
        }

        // Check if expired
        if ($record->isExpired()) {
            throw new ValidationFailedException(['code' => lang('Api.otp_invalid')]);
        }

        // Check if burned due to max attempts
        if ($record->attempts >= $this->maxAttempts) {
            throw new ValidationFailedException(['code' => lang('Api.otp_max_attempts')]);
        }

        $db = $this->otpModel->db;
        $table = $this->otpModel->getTable();

        // Reserve one attempt atomically BEFORE comparing, so concurrent guesses can never
        // exceed maxAttempts (check-then-increment would let N parallel requests all pass).
        $db->query(
            "UPDATE {$table} SET attempts = attempts + 1 WHERE id = ? AND attempts < ? AND consumed_at IS NULL",
            [$record->id, $this->maxAttempts]
        );
        if ($db->affectedRows() !== 1) {
            throw new ValidationFailedException(['code' => lang('Api.otp_max_attempts')]);
        }

        $expectedHash = hash_hmac('sha256', $code, $this->secretKey);

        if (!hash_equals($record->code_hash, $expectedHash)) {
            if ((int) $record->attempts + 1 >= $this->maxAttempts) {
                throw new ValidationFailedException(['code' => lang('Api.otp_max_attempts')]);
            }

            throw new ValidationFailedException(['code' => lang('Api.otp_invalid')]);
        }

        // Mark code as consumed (single-use) atomically: a concurrent request with the same
        // valid code must not be able to log in a second time.
        $db->query(
            "UPDATE {$table} SET consumed_at = ? WHERE id = ? AND consumed_at IS NULL",
            [gmdate('Y-m-d H:i:s'), $record->id]
        );
        if ($db->affectedRows() !== 1) {
            throw new ValidationFailedException(['code' => lang('Api.otp_invalid')]);
        }

        return true;
    }
}
