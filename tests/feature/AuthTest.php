<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\OtpCodeModel;
use App\Models\UserModel;
use App\Services\GoogleAuthService;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;
use Firebase\JWT\JWT;

/**
 * @internal
 */
final class AuthTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrate     = true;
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    public function testRequestOtpReturnsGenericSuccess(): void
    {
        $email = 'req_' . uniqid() . '@wex.sd';
        $result = $this->withBody(json_encode(['email' => $email]))
            ->post('api/v1/auth/email/request');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('تم إرسال رمز التحقق إلى بريدك الإلكتروني', $body['message']);
    }

    public function testVerifyOtpWrongCodeIncrementsAttempts(): void
    {
        $email = 'wrongcode@wex.sd';
        $this->withBody(json_encode(['email' => $email]))
            ->post('api/v1/auth/email/request');

        $result = $this->withBody(json_encode([
            'email' => $email,
            'code'  => '999999',
            'name'  => 'مستخدم تجريبي',
        ]))->post('api/v1/auth/email/verify');

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('code', $body['errors']);
    }

    public function testVerifyOtpRequiresNameForNewUser(): void
    {
        $email = 'noname@wex.sd';
        $this->withBody(json_encode(['email' => $email]))
            ->post('api/v1/auth/email/request');

        $result = $this->withBody(json_encode([
            'email' => $email,
            'code'  => '123456',
        ]))->post('api/v1/auth/email/verify');

        $result->assertStatus(422);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('name', $body['errors']);
    }

    public function testMeEndpointRequiresBearerToken(): void
    {
        $result = $this->get('api/v1/me');
        $result->assertStatus(401);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
    }

    public function testMeEndpointUpdatesProfileWithSudanPhone(): void
    {
        $userModel = new UserModel();
        $user = $userModel->where('role', Wex::ROLE_ADMIN)->first();

        $tokenService = new TokenService();
        $access = $tokenService->generateAccessToken($user);

        // 1. Valid Sudanese phone normalization
        $result = $this->withHeaders(['Authorization' => 'Bearer ' . $access['access_token']])
            ->withBody(json_encode([
                'name'  => 'مدير محدث',
                'phone' => '0912345678',
            ]))
            ->patch('api/v1/me');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
        $this->assertSame('+249912345678', $body['data']['phone']);
        $this->assertSame('مدير محدث', $body['data']['name']);

        // 2. Invalid phone format rejected
        $badResult = $this->withHeaders(['Authorization' => 'Bearer ' . $access['access_token']])
            ->withBody(json_encode([
                'phone' => '12345',
            ]))
            ->patch('api/v1/me');

        $badResult->assertStatus(422);
        $badBody = json_decode($badResult->getJSON(), true);
        $this->assertFalse($badBody['success']);
        $this->assertArrayHasKey('phone', $badBody['errors']);
    }

    public function testRefreshTokenRotationAndTheftDetection(): void
    {
        $userModel = new UserModel();
        $user = $userModel->where('role', Wex::ROLE_ADMIN)->first();

        $tokenService = new TokenService();
        $initialRefreshToken = $tokenService->generateRefreshToken((int) $user->id);

        // 1. First refresh -> succeeds
        $result = $this->withBody(json_encode(['refresh_token' => $initialRefreshToken]))
            ->post('api/v1/auth/refresh');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
        $newRefreshToken = $body['data']['refresh_token'];
        $this->assertNotEmpty($newRefreshToken);
        $this->assertNotSame($initialRefreshToken, $newRefreshToken);

        // 2. Replay initial refresh token (THEFT DETECTION) -> fails with 401
        $theftResult = $this->withBody(json_encode(['refresh_token' => $initialRefreshToken]))
            ->post('api/v1/auth/refresh');

        $theftResult->assertStatus(401);

        // 3. New token also invalidated due to family revocation -> fails with 401
        $revokedResult = $this->withBody(json_encode(['refresh_token' => $newRefreshToken]))
            ->post('api/v1/auth/refresh');

        $revokedResult->assertStatus(401);
    }

    public function testGoogleSignInVerifiesValidToken(): void
    {
        // 1. Generate an RSA keypair for testing
        $res = openssl_pkey_new([
            'digest_alg'       => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        openssl_pkey_export($res, $privateKey);
        $details = openssl_pkey_get_details($res);

        // Convert raw n and e to base64url for JWK
        $n = rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '=');
        $e = rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '=');

        $kid = 'test-key-id-1';
        $mockJwks = [
            'keys' => [
                [
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => $kid,
                    'n'   => $n,
                    'e'   => $e,
                ],
            ],
        ];

        // 2. Build and sign a fake Google ID token
        $now = time();
        $payload = [
            'iss'            => 'https://accounts.google.com',
            'aud'            => 'sample-web-client-id.apps.googleusercontent.com',
            'sub'            => 'google_uid_987654321',
            'email'          => 'google_tester@wex.sd',
            'email_verified' => true,
            'name'           => 'مستخدم تجريبي من جوجل',
            'picture'        => 'https://example.com/avatar.jpg',
            'iat'            => $now,
            'exp'            => $now + 3600,
        ];

        $idToken = JWT::encode($payload, $privateKey, 'RS256', $kid);

        // 3. Inject mock keys into GoogleAuthService via instance in AuthService
        $googleAuth = new GoogleAuthService($mockJwks);
        $authService = new \App\Services\AuthService(
            null,
            null,
            null,
            $googleAuth
        );

        $authData = $authService->signInWithGoogle($idToken);
        $this->assertNotEmpty($authData['access_token']);
        $this->assertSame('google_tester@wex.sd', $authData['user']['email']);
        $this->assertSame('customer', $authData['user']['role']);
    }

    public function testRefreshTokenViaCookiePreservesAbsoluteExpiration(): void
    {
        $userModel = new UserModel();
        $user = $userModel->where('role', Wex::ROLE_ADMIN)->first();

        $tokenService = new TokenService();
        $initialRefreshToken = $tokenService->generateRefreshToken((int) $user->id);

        $initialHash = hash('sha256', $initialRefreshToken);
        $tokenModel = new \App\Models\RefreshTokenModel();
        $initialRecord = $tokenModel->where('token_hash', $initialHash)->first();
        $this->assertNotNull($initialRecord);
        $originalExpiresAt = $initialRecord->expires_at;

        // Call refresh using Cookie header and no JSON body
        $result = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $initialRefreshToken])
            ->post('api/v1/auth/refresh');

        $result->assertStatus(200);
        $result->assertCookie('wex_refresh_token');

        $body = json_decode($result->getJSON(), true);
        $this->assertTrue($body['success']);
        $this->assertNotEmpty($body['data']['access_token']);
        $this->assertNotEmpty($body['data']['refresh_token']);
        $this->assertArrayHasKey('refresh_expires_in', $body['data']);

        // Check new token in DB
        $newHash = hash('sha256', $body['data']['refresh_token']);
        $newRecord = $tokenModel->where('token_hash', $newHash)->first();
        $this->assertNotNull($newRecord);

        // Crucial requirement: rotated token MUST inherit original expires_at (no extension)
        $this->assertSame((string) $originalExpiresAt, (string) $newRecord->expires_at);
        $this->assertSame((int) $initialRecord->family_id, (int) $newRecord->family_id);
    }

    public function testLogoutViaCookieClearsCookieAndRevokesSession(): void
    {
        $userModel = new UserModel();
        $user = $userModel->where('role', Wex::ROLE_ADMIN)->first();

        $tokenService = new TokenService();
        $refreshToken = $tokenService->generateRefreshToken((int) $user->id);

        $result = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $refreshToken])
            ->post('api/v1/auth/logout');

        $result->assertStatus(200);

        // Verify cookie is cleared/expired
        $cookie = $result->response()->getCookie('wex_refresh_token');
        $this->assertNotNull($cookie);
        $this->assertSame('', $cookie->getValue());
        $this->assertLessThanOrEqual(time(), $cookie->getExpiresTimestamp());

        // Trying to refresh now fails
        $refreshResult = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $refreshToken])
            ->post('api/v1/auth/refresh');
        $refreshResult->assertStatus(401);
    }

    public function testLogoutAllClearsCookie(): void
    {
        $userModel = new UserModel();
        $user = $userModel->where('role', Wex::ROLE_ADMIN)->first();

        $tokenService = new TokenService();
        $access = $tokenService->generateAccessToken($user);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $access['access_token'],
            'Cookie'        => 'wex_refresh_token=some_token',
        ])->post('api/v1/auth/logout-all');

        $result->assertStatus(200);

        // Verify cookie is cleared/expired
        $cookie = $result->response()->getCookie('wex_refresh_token');
        $this->assertNotNull($cookie);
        $this->assertSame('', $cookie->getValue());
        $this->assertLessThanOrEqual(time(), $cookie->getExpiresTimestamp());
    }
}
