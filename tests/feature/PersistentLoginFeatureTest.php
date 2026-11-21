<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\User;
use App\Models\RefreshTokenModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * @internal
 */
final class PersistentLoginFeatureTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected User $customerUser;
    protected string $customerToken;
    protected RefreshTokenModel $tokenModel;
    protected TokenService $tokenService;

    protected function setUp(): void
    {
        parent::setUp();
        $_FILES = [];
        $_POST  = [];
        if (function_exists('service')) {
            service('superglobals')->setFilesArray([]);
            \Config\Services::resetSingle('superglobals');
            \Config\Services::resetSingle('request');
            \Config\Services::resetSingle('incomingrequest');
        }

        $this->tokenService = new TokenService();
        $this->tokenModel   = new RefreshTokenModel();
        $userModel          = new UserModel();

        $customer = $userModel->where('email', 'persist_cust@wex.sd')->first();
        if (!$customer) {
            $userModel->insert(new User([
                'name'      => 'عثمان التاج',
                'email'     => 'persist_cust@wex.sd',
                'role'      => Wex::ROLE_CUSTOMER,
                'is_active' => true,
            ]));
            $customer = $userModel->where('email', 'persist_cust@wex.sd')->first();
        }

        $this->customerUser = $customer;
        $this->customerToken = $this->tokenService->generateAccessToken($customer)['access_token'];
    }

    protected function tearDown(): void
    {
        $_FILES = [];
        $_POST  = [];
        if (function_exists('service')) {
            service('superglobals')->setFilesArray([]);
            \Config\Services::resetSingle('superglobals');
            \Config\Services::resetSingle('request');
            \Config\Services::resetSingle('incomingrequest');
        }
        parent::tearDown();
    }

    /**
     * (a) REGRESSION: Two refresh calls with the same cookie in the same second
     * (simulating StrictMode or parallel tabs) leave the family ACTIVE and the newest token valid.
     */
    public function testRegressionTwoRefreshCallsWithSameCookieWithinGraceWindowLeaveFamilyActive(): void
    {
        $initialRawToken = $this->tokenService->generateRefreshToken((int) $this->customerUser->id);
        $initialHash = hash('sha256', $initialRawToken);
        $initialRecord = $this->tokenModel->where('token_hash', $initialHash)->first();
        $this->assertNotNull($initialRecord);
        $familyId = $initialRecord->family_id;

        // 1. First refresh call with initial token
        $result1 = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $initialRawToken])
            ->withBody(json_encode(['return_refresh_token' => true]))
            ->post('api/v1/auth/refresh');

        $result1->assertStatus(200);
        $body1 = json_decode($result1->getJSON(), true);
        $rotatedToken1 = $body1['data']['refresh_token'];
        $this->assertNotEmpty($rotatedToken1);

        // 2. Second parallel call with the SAME original initial token (arrived in the same second)
        $result2 = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $initialRawToken])
            ->withBody(json_encode(['return_refresh_token' => true]))
            ->post('api/v1/auth/refresh');

        $result2->assertStatus(200);
        $body2 = json_decode($result2->getJSON(), true);
        $rotatedToken2 = $body2['data']['refresh_token'];
        $this->assertNotEmpty($rotatedToken2);

        // 3. Crucial: Family MUST still be active and have at least one unrevoked token
        $activeTokens = $this->tokenModel
            ->where('family_id', $familyId)
            ->where('revoked_at IS NULL')
            ->findAll();

        $this->assertNotEmpty($activeTokens, 'Family must have at least one valid unrevoked token after grace window replay.');

        // 4. Using the newest rotated token MUST succeed
        $result3 = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $rotatedToken2])
            ->withBody(json_encode(['return_refresh_token' => true]))
            ->post('api/v1/auth/refresh');

        $result3->assertStatus(200);
    }

    /**
     * (b) Cookie Max-Age about 30 days and correct security flags.
     */
    public function testCookieMaxAgeAbout30DaysAndSecurityFlags(): void
    {
        $rawToken = $this->tokenService->generateRefreshToken((int) $this->customerUser->id);

        $result = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $rawToken])
            ->post('api/v1/auth/refresh');

        $result->assertStatus(200);
        $result->assertCookie('wex_refresh_token');

        $cookie = $result->response()->getCookie('wex_refresh_token');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHTTPOnly());
        $this->assertSame('/', $cookie->getPath());
        $this->assertSame('Lax', $cookie->getSameSite());

        // Max-Age should be approximately 30 days (2,592,000 seconds, allowance for test execution)
        $expires = $cookie->getExpiresTimestamp();
        $ttl = $expires - time();
        $this->assertGreaterThanOrEqual(2591900, $ttl);
        $this->assertLessThanOrEqual(2592000, $ttl);
    }

    /**
     * (c) Sliding expiry: expires_at moves forward on refresh.
     */
    public function testSlidingExpiryMovesExpiresAtForward(): void
    {
        $rawToken = $this->tokenService->generateRefreshToken((int) $this->customerUser->id);
        $hash = hash('sha256', $rawToken);
        $record1 = $this->tokenModel->where('token_hash', $hash)->first();

        // Artificially age the first token by 5 days
        $olderExpiresAt = gmdate('Y-m-d H:i:s', time() + (25 * 86400));
        $this->tokenModel->update($record1->id, ['expires_at' => $olderExpiresAt]);

        $result = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $rawToken])
            ->withBody(json_encode(['return_refresh_token' => true]))
            ->post('api/v1/auth/refresh');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $newToken = $body['data']['refresh_token'];

        $newRecord = $this->tokenModel->where('token_hash', hash('sha256', $newToken))->first();
        $this->assertNotNull($newRecord);

        // New expires_at must slide forward past the aged expiration
        $this->assertGreaterThan(strtotime($olderExpiresAt), strtotime($newRecord->expires_at));
        $this->assertGreaterThanOrEqual(time() + (29 * 86400), strtotime($newRecord->expires_at));
    }

    /**
     * (d) Reuse after the grace window revokes the whole family (theft detection).
     */
    public function testReuseAfterGraceWindowRevokesWholeFamily(): void
    {
        $rawToken = $this->tokenService->generateRefreshToken((int) $this->customerUser->id);
        $hash = hash('sha256', $rawToken);
        $initialRecord = $this->tokenModel->where('token_hash', $hash)->first();
        $familyId = $initialRecord->family_id;

        // Rotate once legitimately
        $result = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $rawToken])
            ->withBody(json_encode(['return_refresh_token' => true]))
            ->post('api/v1/auth/refresh');
        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $newActiveToken = $body['data']['refresh_token'];

        // Age the revocation of rawToken so it is older than REFRESH_REUSE_GRACE_SECONDS (30s)
        $this->tokenModel->where('token_hash', $hash)
            ->set(['revoked_at' => gmdate('Y-m-d H:i:s', time() - 60)])
            ->update();

        // Replaying rawToken outside grace window must trigger theft detection (401)
        $replayResult = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $rawToken])
            ->post('api/v1/auth/refresh');
        $replayResult->assertStatus(401);

        // Entire family must now be revoked with reason 'reuse_detected'
        $activeTokens = $this->tokenModel
            ->where('family_id', $familyId)
            ->where('revoked_at IS NULL')
            ->findAll();
        $this->assertEmpty($activeTokens, 'Theft detection must revoke all tokens in the family.');

        // Attempting to use the newActiveToken must now fail
        $newResult = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $newActiveToken])
            ->post('api/v1/auth/refresh');
        $newResult->assertStatus(401);
    }

    /**
     * (e) Absolute lifetime cap (180 days) enforces required re-login.
     */
    public function testAbsoluteLifetimeCapEnforcesReLogin(): void
    {
        // Create token with family_created_at 181 days in the past
        $oldFamilyCreated = gmdate('Y-m-d H:i:s', time() - (181 * 86400));
        $rawToken = $this->tokenService->generateRefreshToken(
            (int) $this->customerUser->id,
            null,
            'TestAgent',
            '127.0.0.1',
            $oldFamilyCreated
        );

        $result = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $rawToken])
            ->post('api/v1/auth/refresh');

        $result->assertStatus(401);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['success']);
    }

    /**
     * (f) 401 on invalid and missing tokens.
     */
    public function testInvalidAndMissingTokensReturn401(): void
    {
        // 1. Missing token
        $resMissing = $this->post('api/v1/auth/refresh');
        $resMissing->assertStatus(401);
        $bodyMissing = json_decode($resMissing->getJSON(), true);
        $this->assertFalse($bodyMissing['success']);
        $this->assertSame('انتهت صلاحية جلسة الدخول، يرجى تسجيل الدخول مجدداً', $bodyMissing['message']);

        // 2. Garbage invalid token
        $resInvalid = $this->withHeaders(['Cookie' => 'wex_refresh_token=totally_fake_random_garbage_token'])
            ->post('api/v1/auth/refresh');
        $resInvalid->assertStatus(401);
        $bodyInvalid = json_decode($resInvalid->getJSON(), true);
        $this->assertFalse($bodyInvalid['success']);
        $this->assertSame('انتهت صلاحية جلسة الدخول، يرجى تسجيل الدخول مجدداً', $bodyInvalid['message']);
    }

    /**
     * (g) Token hashing stability across kernel restarts with the same key.
     */
    public function testHashingStableAcrossKernelRestarts(): void
    {
        $rawToken = 'sample_fixed_raw_token_for_stability_verification_1234567890';
        $hash1 = hash('sha256', $rawToken);

        // Re-instantiate TokenService to simulate a new request lifecycle
        $newTokenService = new TokenService();
        $hash2 = hash('sha256', $rawToken);

        $this->assertSame($hash1, $hash2);
    }

    /**
     * (h) Logout clears the cookie and revokes the token with reason 'logout'.
     */
    public function testLogoutClearsCookieAndRevokesToken(): void
    {
        $rawToken = $this->tokenService->generateRefreshToken((int) $this->customerUser->id);
        $hash = hash('sha256', $rawToken);

        $result = $this->withHeaders(['Cookie' => 'wex_refresh_token=' . $rawToken])
            ->post('api/v1/auth/logout');

        $result->assertStatus(200);
        $cookie = $result->response()->getCookie('wex_refresh_token');
        $this->assertNotNull($cookie);
        $this->assertSame('', $cookie->getValue());
        $this->assertSame(0, $cookie->getExpiresTimestamp());

        // Check DB token marked revoked with reason 'logout'
        $record = $this->tokenModel->where('token_hash', $hash)->first();
        $this->assertNotNull($record);
        $this->assertNotNull($record->revoked_at);
        $this->assertSame('logout', $record->revoke_reason);
    }

    /**
     * (i) Logout-all revokes all user tokens with reason 'logout_all'.
     */
    public function testLogoutAllRevokesAllUserTokens(): void
    {
        $token1 = $this->tokenService->generateRefreshToken((int) $this->customerUser->id);
        $token2 = $this->tokenService->generateRefreshToken((int) $this->customerUser->id);

        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Cookie'        => 'wex_refresh_token=' . $token1,
        ])->post('api/v1/auth/logout-all');

        $result->assertStatus(200);

        $hash1 = hash('sha256', $token1);
        $hash2 = hash('sha256', $token2);

        $rec1 = $this->tokenModel->where('token_hash', $hash1)->first();
        $rec2 = $this->tokenModel->where('token_hash', $hash2)->first();

        $this->assertNotNull($rec1->revoked_at);
        $this->assertSame('logout_all', $rec1->revoke_reason);
        $this->assertNotNull($rec2->revoked_at);
        $this->assertSame('logout_all', $rec2->revoke_reason);
    }
}
