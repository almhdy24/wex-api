<?php

namespace Tests\Feature;

use App\Controllers\Api\V1\AuthController;
use App\Database\Seeds\DatabaseSeeder;
use App\Exceptions\TooManyRequestsException;
use App\Exceptions\ValidationFailedException;
use App\Models\OtpCodeModel;
use App\Services\GoogleAuthService;
use App\Services\OtpService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Firebase\JWT\JWT;

/**
 * Regression tests for review findings API-AUTH-01..04.
 *
 * @internal
 */
final class AuthHardeningTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected function tearDown(): void
    {
        putenv('TESTING_LOGIN_ENABLED');
        unset($_ENV['TESTING_LOGIN_ENABLED'], $_SERVER['TESTING_LOGIN_ENABLED']);
        parent::tearDown();
    }

    // API-AUTH-01: testing-login must be opt-in, not "anything but production".
    public function testTestingLoginIsDisabledWithoutExplicitOptIn(): void
    {
        putenv('TESTING_LOGIN_ENABLED');
        unset($_ENV['TESTING_LOGIN_ENABLED'], $_SERVER['TESTING_LOGIN_ENABLED']);

        $this->assertFalse(AuthController::testingLoginEnabled());

        $result = $this->withBody(json_encode(['email' => 'admin@wextechnology.com']))
            ->post('api/v1/auth/testing-login');
        $result->assertStatus(404);
        $this->assertStringNotContainsString('access_token', $result->getJSON());
    }

    public function testTestingLoginWorksWhenOptedIn(): void
    {
        putenv('TESTING_LOGIN_ENABLED=true');
        $_ENV['TESTING_LOGIN_ENABLED'] = 'true';

        $this->assertTrue(AuthController::testingLoginEnabled());
    }

    // API-AUTH-02: Google sign-in must fail closed when no audiences are configured.
    public function testGoogleTokenRejectedWhenNoAudienceConfigured(): void
    {
        [$jwks, $token] = $this->makeGoogleToken('some-other-app.apps.googleusercontent.com');

        $saved = getenv('GOOGLE_CLIENT_IDS');
        putenv('GOOGLE_CLIENT_IDS=');
        $_ENV['GOOGLE_CLIENT_IDS'] = '';
        $_SERVER['GOOGLE_CLIENT_IDS'] = '';

        try {
            $service = new GoogleAuthService($jwks);
            $this->expectException(ValidationFailedException::class);
            $service->verifyIdToken($token);
        } finally {
            putenv('GOOGLE_CLIENT_IDS=' . ($saved ?: ''));
            $_ENV['GOOGLE_CLIENT_IDS'] = $saved ?: '';
            $_SERVER['GOOGLE_CLIENT_IDS'] = $saved ?: '';
        }
    }

    // API-AUTH-03: attempts limit must hold even when the record read is stale (concurrent guesses).
    public function testOtpVerifyRejectsWhenAttemptsExhaustedConcurrently(): void
    {
        $email = 'otp_race_' . bin2hex(random_bytes(3)) . '@wex.sd';
        $otp   = new OtpService();
        $code  = $otp->generate($email);

        $model = new OtpCodeModel();
        $stale = $model->where('email', $email)->where('consumed_at IS NULL')->orderBy('id', 'DESC')->first();

        // Other concurrent requests exhausted all attempts after our read.
        $model->update($stale->id, ['attempts' => 5]);

        $service = new OtpService($this->staleModel($stale));
        $this->expectException(ValidationFailedException::class);
        $service->verify($email, $code);
    }

    // API-AUTH-03: a valid code must be single-use even under concurrent verification.
    public function testOtpVerifyIsSingleUseUnderConcurrency(): void
    {
        $email = 'otp_once_' . bin2hex(random_bytes(3)) . '@wex.sd';
        $otp   = new OtpService();
        $code  = $otp->generate($email);

        $model = new OtpCodeModel();
        $stale = $model->where('email', $email)->where('consumed_at IS NULL')->orderBy('id', 'DESC')->first();

        // A parallel request already consumed the code after our read.
        $model->update($stale->id, ['consumed_at' => gmdate('Y-m-d H:i:s')]);

        $service = new OtpService($this->staleModel($stale));
        $this->expectException(ValidationFailedException::class);
        $service->verify($email, $code);
    }

    // API-AUTH-04: per-email hourly cap on OTP issuance.
    public function testOtpIssuanceCappedPerEmailPerHour(): void
    {
        $email   = 'otp_cap_' . bin2hex(random_bytes(3)) . '@wex.sd';
        $model   = new OtpCodeModel();
        $service = new class () extends OtpService {
            protected function isRateLimitExempt(?string $ip): bool
            {
                return false;
            }
        };

        // Seed 5 codes issued over the last hour, outside the 60s cooldown.
        for ($i = 0; $i < 5; $i++) {
            $model->insert([
                'email'      => $email,
                'code_hash'  => 'x',
                'attempts'   => 0,
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 600),
                'created_at' => gmdate('Y-m-d H:i:s', time() - 3000 + $i * 60),
            ]);
        }

        $this->expectException(TooManyRequestsException::class);
        $service->generate($email, '203.0.113.9');
    }

    private function staleModel(object $stale): OtpCodeModel
    {
        return new class ($stale) extends OtpCodeModel {
            public function __construct(private object $staleRecord)
            {
                parent::__construct();
            }

            public function first()
            {
                $this->builder()->resetQuery();

                return $this->staleRecord;
            }
        };
    }

    /**
     * @return array{0: array, 1: string}
     */
    private function makeGoogleToken(string $aud): array
    {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $privateKey);
        $d   = openssl_pkey_get_details($res);
        $b64 = static fn (string $v) => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $jwks = ['keys' => [[
            'kty' => 'RSA', 'alg' => 'RS256', 'use' => 'sig', 'kid' => 'k1',
            'n'   => $b64($d['rsa']['n']), 'e' => $b64($d['rsa']['e']),
        ]]];
        $now   = time();
        $token = JWT::encode([
            'iss' => 'https://accounts.google.com', 'aud' => $aud, 'sub' => 'g-1',
            'email' => 'victim@wex.sd', 'email_verified' => true, 'iat' => $now, 'exp' => $now + 600,
        ], $privateKey, 'RS256', 'k1');

        return [$jwks, $token];
    }
}
