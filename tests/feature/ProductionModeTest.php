<?php

namespace Tests\Feature;

use App\Models\UserModel;
use App\Services\PushService;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * Test-only features must be unreachable in production, whatever .env says
 * (TESTING_LOGIN_ENABLED, PUSH_PROVIDER=fake).
 *
 * @internal
 */
final class ProductionModeTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = \App\Database\Seeds\DatabaseSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        putenv('CI_ENVIRONMENT=production');
        putenv('TESTING_LOGIN_ENABLED=true');
        putenv('PUSH_PROVIDER=fake');
    }

    protected function tearDown(): void
    {
        putenv('CI_ENVIRONMENT');
        putenv('TESTING_LOGIN_ENABLED');
        putenv('PUSH_PROVIDER');
        parent::tearDown();
    }

    public function testTestingLoginIs404InProduction(): void
    {
        $admin = (new UserModel())->where('role', Wex::ROLE_ADMIN)->first();
        $res = $this->withBodyFormat('json')->post('/api/v1/auth/testing-login', ['email' => $admin->email]);
        $res->assertStatus(404);
        $this->assertStringNotContainsString('access_token', $res->getJSON());
    }

    public function testFakePushLogIs404InProduction(): void
    {
        $admin = (new UserModel())->where('role', Wex::ROLE_ADMIN)->first();
        $token = (new TokenService())->generateAccessToken($admin)['access_token'];
        $this->withHeaders(['Authorization' => 'Bearer ' . $token])->get('/api/v1/admin/push/fake-log')->assertStatus(404);
    }

    public function testFakePushProviderIgnoredInProduction(): void
    {
        $svc = new PushService();
        $prop = new \ReflectionProperty($svc, 'overrideProvider');
        $prop->setAccessible(true);
        $this->assertNull($prop->getValue($svc), 'PUSH_PROVIDER=fake must not take effect in production');
    }
}
