<?php

namespace Tests\Feature;

use App\Database\Seeds\AdminSeeder;
use App\Database\Seeds\DatabaseSeeder;
use App\Entities\Announcement;
use App\Entities\User;
use App\Models\AnnouncementModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * @internal
 */
final class AnnouncementsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected User $adminUser;
    protected string $adminToken;

    protected function setUp(): void
    {
        parent::setUp();

        $tokenService = new TokenService();
        $userModel    = new UserModel();

        $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        if (!$admin) {
            (new AdminSeeder())->run();
            $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        }
        $this->adminUser  = $admin;
        $this->adminToken = $tokenService->generateAccessToken($admin)['access_token'];
    }

    public function testPublicActiveWindowAnnouncements(): void
    {
        $model = new AnnouncementModel();
        $model->emptyTable();

        $now = time();
        // 1. Active announcement (current window)
        $model->insert(new Announcement([
            'kind'           => 'info',
            'title'          => 'إعلان سارٍ',
            'body'           => 'محتوى الإعلان الساري',
            'starts_at'      => gmdate('Y-m-d H:i:s', $now - 3600),
            'ends_at'        => gmdate('Y-m-d H:i:s', $now + 3600),
            'is_active'      => 1,
            'is_dismissible' => 1,
        ]));

        // 2. Future announcement (should not appear)
        $model->insert(new Announcement([
            'kind'           => 'promo',
            'title'          => 'إعلان مستقبلي',
            'body'           => 'محتوى مستقبلي',
            'starts_at'      => gmdate('Y-m-d H:i:s', $now + 7200),
            'ends_at'        => gmdate('Y-m-d H:i:s', $now + 14400),
            'is_active'      => 1,
            'is_dismissible' => 1,
        ]));

        // 3. Expired announcement (should not appear)
        $model->insert(new Announcement([
            'kind'           => 'warning',
            'title'          => 'إعلان منتهي',
            'body'           => 'محتوى منتهي',
            'starts_at'      => gmdate('Y-m-d H:i:s', $now - 14400),
            'ends_at'        => gmdate('Y-m-d H:i:s', $now - 7200),
            'is_active'      => 1,
            'is_dismissible' => 1,
        ]));

        // 4. Inactive announcement (should not appear)
        $model->insert(new Announcement([
            'kind'           => 'info',
            'title'          => 'إعلان معطل',
            'body'           => 'محتوى معطل',
            'is_active'      => 0,
            'is_dismissible' => 1,
        ]));

        $res = $this->get('/api/v1/announcements');
        $res->assertStatus(200);
        $body = json_decode($res->getJSON(), true);

        $this->assertTrue($body['success']);
        $this->assertCount(1, $body['data']);
        $this->assertSame('إعلان سارٍ', $body['data'][0]['title']);
    }

    public function testAdminAnnouncementCrudAndToggle(): void
    {
        // 1. Create
        $createPayload = [
            'kind'           => 'promo',
            'title'          => 'تخفيضات نهاية الأسبوع',
            'body'           => 'خصم 5% على كافة أجهزة ThinkPad',
            'placement'      => 'banner',
            'is_dismissible' => true,
        ];

        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($createPayload))->post('/api/v1/admin/announcements');

        $res->assertStatus(201);
        $created = json_decode($res->getJSON(), true)['data'];
        $id = (int) $created['id'];
        $this->assertSame('تخفيضات نهاية الأسبوع', $created['title']);

        // 2. Read (list & show)
        $listRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('/api/v1/admin/announcements');
        $listRes->assertStatus(200);

        $showRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get("/api/v1/admin/announcements/{$id}");
        $showRes->assertStatus(200);

        // 3. Update
        $updateRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['title' => 'تخفيضات محدثة']))->patch("/api/v1/admin/announcements/{$id}");
        $updateRes->assertStatus(200);
        $this->assertSame('تخفيضات محدثة', json_decode($updateRes->getJSON(), true)['data']['title']);

        // 4. Toggle
        $toggleRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->patch("/api/v1/admin/announcements/{$id}/toggle");
        $toggleRes->assertStatus(200);
        $this->assertSame(0, (int) json_decode($toggleRes->getJSON(), true)['data']['is_active']);

        // 5. Delete
        $delRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->delete("/api/v1/admin/announcements/{$id}");
        $delRes->assertStatus(200);

        $model = new AnnouncementModel();
        $this->assertNull($model->find($id));
    }

    // API-XSS-01: script URLs must be rejected for storefront links.
    public function testAnnouncementRejectsJavascriptLink(): void
    {
        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'title' => 'عرض', 'body' => 'نص', 'link_url' => 'javascript:alert(document.cookie)',
        ]))->post('/api/v1/admin/announcements');

        $res->assertStatus(422);
        $this->assertArrayHasKey('link_url', json_decode($res->getJSON(), true)['errors']);
    }
}
