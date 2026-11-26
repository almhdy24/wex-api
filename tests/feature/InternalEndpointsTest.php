<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Models\UserModel;
use App\Services\CronRunner;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * HOST-01/02: cron runner and self-locking installer for shared hosting.
 *
 * @internal
 */
final class InternalEndpointsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    private const CRON = 'cron-token-0123456789abcdef0123456789abcdef';
    private const INST = 'install-token-0123456789abcdef0123456789ab';

    private function setEnv(string $k, ?string $v): void
    {
        if ($v === null) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        } else {
            putenv("{$k}={$v}");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
    }

    protected function tearDown(): void
    {
        foreach (['CRON_TOKEN', 'CRON_ALLOW_QUERY_TOKEN', 'INSTALL_TOKEN'] as $k) {
            $this->setEnv($k, null);
        }
        @unlink(WRITEPATH . 'install.lock');
        @unlink(WRITEPATH . 'cron_state.json');
        parent::tearDown();
    }

    public function testCronIsInvisibleWithoutConfiguredToken(): void
    {
        $this->setEnv('CRON_TOKEN', null);
        $this->withHeaders(['X-Cron-Token' => self::CRON])->post('api/v1/internal/cron')->assertStatus(404);

        $this->setEnv('CRON_TOKEN', 'too-short');
        $this->withHeaders(['X-Cron-Token' => 'too-short'])->post('api/v1/internal/cron')->assertStatus(404);
    }

    public function testCronRejectsMissingOrWrongToken(): void
    {
        $this->setEnv('CRON_TOKEN', self::CRON);
        $this->post('api/v1/internal/cron')->assertStatus(403);
        $this->withHeaders(['X-Cron-Token' => self::CRON . 'x'])->post('api/v1/internal/cron')->assertStatus(403);
        // query token is refused unless explicitly allowed
        $this->get('api/v1/internal/cron?token=' . self::CRON)->assertStatus(403);
    }

    public function testCronRunsAllTasksWithValidToken(): void
    {
        $this->setEnv('CRON_TOKEN', self::CRON);
        $res = $this->withHeaders(['X-Cron-Token' => self::CRON])->post('api/v1/internal/cron');
        $res->assertStatus(200);
        $data = json_decode($res->getJSON(), true)['data'];
        $this->assertSame('ok', $data['status']);
        foreach (['orders:expire', 'push:work', 'push:reminders', 'mail:send', 'integrity:check'] as $t) {
            $this->assertArrayHasKey($t, $data['ran']);
            $this->assertNotSame('error', $data['ran'][$t], "{$t} errored");
        }

        // integrity:check is daily: a second run today skips it
        $again = json_decode($this->withHeaders(['X-Cron-Token' => self::CRON])->post('api/v1/internal/cron')->getJSON(), true)['data'];
        $this->assertArrayNotHasKey('integrity:check', $again['ran']);
    }

    public function testQueryTokenOnlyWhenAllowed(): void
    {
        $this->setEnv('CRON_TOKEN', self::CRON);
        $this->setEnv('CRON_ALLOW_QUERY_TOKEN', 'true');
        $this->get('api/v1/internal/cron?token=' . self::CRON)->assertStatus(200);
    }

    public function testOverlappingRunReturnsBusy(): void
    {
        $this->setEnv('CRON_TOKEN', self::CRON);
        $fh = fopen(WRITEPATH . 'cron.lock', 'c');
        $this->assertTrue(flock($fh, LOCK_EX | LOCK_NB));
        try {
            $this->withHeaders(['X-Cron-Token' => self::CRON])->post('api/v1/internal/cron')->assertStatus(409);
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    public function testTimeBudgetStopsStartingNewTasks(): void
    {
        $calls = [];
        $runner = new CronRunner([
            'a' => static function () use (&$calls) { $calls[] = 'a'; usleep(300000); return 1; },
            'b' => static function () use (&$calls) { $calls[] = 'b'; return 1; },
        ]);
        $result = $runner->run(0.1);
        $this->assertSame(['a'], $calls);
        $this->assertSame(['b'], $result['skipped']);
    }

    public function testInstallerIsGatedAndSelfLocking(): void
    {
        $email = 'owner_' . bin2hex(random_bytes(3)) . '@wex.sd';

        $this->setEnv('INSTALL_TOKEN', null);
        $this->withBodyFormat('json')->post('api/v1/internal/install', ['admin_email' => $email])->assertStatus(404);

        $this->setEnv('INSTALL_TOKEN', self::INST);
        $this->withHeaders(['X-Install-Token' => 'wrong'])->withBodyFormat('json')
            ->post('api/v1/internal/install', ['admin_email' => $email])->assertStatus(403);

        $ok = $this->withHeaders(['X-Install-Token' => self::INST])->withBodyFormat('json')
            ->post('api/v1/internal/install', ['admin_email' => $email, 'admin_name' => 'صاحب المتجر']);
        $ok->assertStatus(200);
        $admin = (new UserModel())->where('email', $email)->first();
        $this->assertSame('admin', $admin->role);
        $this->assertFileExists(WRITEPATH . 'install.lock');

        $this->withHeaders(['X-Install-Token' => self::INST])->withBodyFormat('json')
            ->post('api/v1/internal/install', ['admin_email' => 'second@wex.sd'])->assertStatus(410);
        // without the token the install state is not revealed
        $this->withHeaders([])->withBodyFormat('json')->post('api/v1/internal/install', ['admin_email' => 'second@wex.sd'])->assertStatus(403);
        $this->assertNull((new UserModel())->where('email', 'second@wex.sd')->first());
    }

    /** Fresh install: ordering closed, no catalogue added unless sample content is asked for. */
    public function testInstallStartsClosedWithoutSampleContent(): void
    {
        $db = \Config\Database::connect();
        $products = $db->table('products')->countAllResults();
        $db->table('settings')->where('key', 'orders_enabled')->update(['value' => '1']);

        $this->setEnv('INSTALL_TOKEN', self::INST);
        $this->withHeaders(['X-Install-Token' => self::INST])->withBodyFormat('json')
            ->post('api/v1/internal/install', ['admin_email' => 'fresh_' . bin2hex(random_bytes(3)) . '@shop.test'])
            ->assertStatus(200);

        $this->assertSame('0', $db->table('settings')->where('key', 'orders_enabled')->get()->getRow()->value);
        $this->assertSame($products, $db->table('products')->countAllResults(), 'installer must not add products by default');
        $this->assertSame(1, $db->table('categories')->where('slug', 'laptops')->countAllResults());
    }

    /** Orders cannot be opened while required setup items are missing; the checklist says what. */
    public function testOrdersStayClosedUntilSetupComplete(): void
    {
        $db = \Config\Database::connect();
        $admin = (new UserModel())->where('role', 'admin')->first();
        $token = (new \App\Services\TokenService())->generateAccessToken($admin)['access_token'];
        $auth = ['Authorization' => 'Bearer ' . $token];

        $db->table('settings')->where('key', 'shop_phone')->update(['value' => '']);
        $db->table('settings')->where('key', 'shop_whatsapp')->update(['value' => '']);
        $db->table('payment_accounts')->update(['is_active' => 0]);

        $status = json_decode($this->withHeaders($auth)->get('api/v1/admin/setup-status')->getJSON(), true)['data'];
        $this->assertFalse($status['complete']);
        $keys = array_column($status['missing'], 'key');
        $this->assertContains('contact', $keys);
        $this->assertContains('payment_account', $keys);

        $res = $this->withHeaders($auth)->withBodyFormat('json')->patch('api/v1/admin/settings', ['orders_enabled' => true]);
        $res->assertStatus(422);
        $this->assertStringContainsString('أكمل إعداد المتجر', $res->getJSON());

        $db->table('settings')->where('key', 'shop_whatsapp')->update(['value' => '0911000000']);
        $db->table('payment_accounts')->update(['is_active' => 1]);
        $this->assertTrue((new \App\Services\SetupStatusService())->status()['complete']);
        $this->withHeaders($auth)->withBodyFormat('json')->patch('api/v1/admin/settings', ['orders_enabled' => true])->assertStatus(200);
    }

    /** Browser installer: gated, reveals nothing before the token, never echoes secrets, self-locks. */
    public function testInstallerPageFlow(): void
    {
        $this->setEnv('INSTALL_TOKEN', null);
        $this->get('api/install')->assertStatus(404);

        $this->setEnv('INSTALL_TOKEN', self::INST);
        $page = $this->get('api/install');
        $page->assertStatus(200);
        $html = (string) $page->getBody();
        $this->assertStringContainsString('install_token', $html);
        $this->assertStringNotContainsString(PHP_VERSION, $html, 'no environment report before the token');

        $this->post('api/install', ['install_token' => 'wrong-token-wrong-token-wrong-token'])->assertStatus(403);

        $check = (string) $this->post('api/install', ['install_token' => self::INST])->getBody();
        $this->assertStringContainsString('فحص الاستضافة', $check);
        $jwt = (string) env('JWT_SECRET', '');
        if ($jwt !== '') {
            $this->assertStringNotContainsString($jwt, $check, 'existing secrets are never displayed');
        }

        $done = $this->post('api/install', ['install_token' => self::INST, 'action' => 'install', 'admin_email' => 'page_' . bin2hex(random_bytes(3)) . '@shop.test', 'admin_name' => 'مدير']);
        $done->assertStatus(200);
        $this->assertStringContainsString('تم التثبيت بنجاح', (string) $done->getBody());

        $this->post('api/install', ['install_token' => self::INST])->assertStatus(410);
    }
}
