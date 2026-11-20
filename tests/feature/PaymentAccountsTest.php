<?php

namespace Tests\Feature;

use App\Database\Seeds\AdminSeeder;
use App\Database\Seeds\DatabaseSeeder;
use App\Entities\User;
use App\Models\AuditLogModel;
use App\Models\PaymentAccountModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Database;
use Config\Wex;

/**
 * @internal
 */
final class PaymentAccountsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected User $adminUser;
    protected User $staffUser;
    protected string $adminToken;
    protected string $staffToken;

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

        $staff = $userModel->where('email', 'staff_pa_test@wextechnology.com')->first();
        if (!$staff) {
            $userModel->insert(new User([
                'name'      => 'موظف تجريبي',
                'email'     => 'staff_pa_test@wextechnology.com',
                'role'      => Wex::ROLE_STAFF,
                'is_active' => true,
            ]));
            $staff = $userModel->where('email', 'staff_pa_test@wextechnology.com')->first();
        }
        $this->staffUser  = $staff;
        $this->staffToken = $tokenService->generateAccessToken($staff)['access_token'];
    }

    public function testStaffIsForbiddenFromPaymentAccounts(): void
    {
        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->staffToken,
        ])->get('/api/v1/admin/payment-accounts');

        $res->assertStatus(403);
    }

    public function testAdminCanListPaymentAccounts(): void
    {
        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('/api/v1/admin/payment-accounts');

        $res->assertStatus(200);
        $body = json_decode($res->getJSON(), true);
        $this->assertTrue($body['success']);
        $this->assertIsArray($body['data']);
    }

    public function testCreateRequiresConfirmation(): void
    {
        $payload = [
            'bank_name'      => 'بنك أم درمان الوطني',
            'account_name'   => 'ويكس تكنولوجي',
            'account_number' => '123456789',
        ];

        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->post('/api/v1/admin/payment-accounts');

        $res->assertStatus(422);
        $body = json_decode($res->getJSON(), true);
        $this->assertFalse($body['success']);
        $this->assertArrayHasKey('confirm', $body['errors'] ?? []);
    }

    public function testAdminCanCreateAccountWithConfirmAndAuditsAndEmailsOutbox(): void
    {
        $db = Database::connect();
        $db->table('email_outbox')->emptyTable();

        $payload = [
            'bank_name'      => 'بنك فيصل الإسلامي',
            'account_name'   => 'ويكس تكنولوجي - فيصل',
            'account_number' => '987654321',
            'branch'         => 'فرع أم درمان',
            'confirm'        => true,
        ];

        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->post('/api/v1/admin/payment-accounts');

        $res->assertStatus(201);
        $body = json_decode($res->getJSON(), true);
        $this->assertTrue($body['success']);
        $newId = (int) $body['data']['id'];

        // Verify database
        $model = new PaymentAccountModel();
        $created = $model->find($newId);
        $this->assertNotNull($created);
        $this->assertSame('بنك فيصل الإسلامي', $created->bank_name);

        // Verify audit log
        $auditModel = new AuditLogModel();
        $log = $auditModel->where('action', 'payment_account_created')->where('entity_id', $newId)->first();
        $this->assertNotNull($log);

        // Verify email outbox entry
        $email = $db->table('email_outbox')->where('template', 'payment_accounts_changed')->get()->getFirstRow();
        $this->assertNotNull($email);
        $this->assertSame('pending', $email->status);
    }

    public function testAdminCanUpdateAccountWithConfirm(): void
    {
        $model = new PaymentAccountModel();
        $account = $model->first();
        $this->assertNotNull($account);

        // Without confirm fails
        $failRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['bank_name' => 'بنك جديد']))->patch("/api/v1/admin/payment-accounts/{$account->id}");
        $failRes->assertStatus(422);

        // With confirm succeeds
        $successRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'bank_name' => 'بنك الخرطوم المحدث',
            'confirm'   => true,
        ]))->patch("/api/v1/admin/payment-accounts/{$account->id}");

        $successRes->assertStatus(200);
        $updated = $model->find($account->id);
        $this->assertSame('بنك الخرطوم المحدث', $updated->bank_name);
    }

    public function testAdminCanToggleAccountWithConfirm(): void
    {
        $model = new PaymentAccountModel();
        $account = $model->first();
        $this->assertNotNull($account);

        $initialActive = (int) $account->is_active;

        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['confirm' => true]))->patch("/api/v1/admin/payment-accounts/{$account->id}/toggle");

        $res->assertStatus(200);
        $refreshed = $model->find($account->id);
        $this->assertSame($initialActive ? 0 : 1, (int) $refreshed->is_active);
    }

    public function testAdminCanReorderAccountsWithConfirm(): void
    {
        $model = new PaymentAccountModel();
        $all = $model->findAll();
        $ids = array_map(static fn($a) => (int) $a->id, $all);

        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode([
            'ids'     => array_reverse($ids),
            'confirm' => true,
        ]))->post('/api/v1/admin/payment-accounts/reorder');

        $res->assertStatus(200);
    }

    public function testAdminCanDeleteAccountWithConfirm(): void
    {
        $model = new PaymentAccountModel();
        $acc = new \App\Entities\PaymentAccount([
            'bank_name'      => 'بنk للحذف',
            'account_name'   => 'تجريبي',
            'account_number' => '000000',
            'is_active'      => 0,
        ]);
        $id = (int) $model->insert($acc);

        $res = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['confirm' => true]))->delete("/api/v1/admin/payment-accounts/{$id}");

        $res->assertStatus(200);
        $this->assertNull($model->find($id));
    }
}
