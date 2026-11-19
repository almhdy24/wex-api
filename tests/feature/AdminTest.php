<?php

namespace Tests\Feature;

use App\Database\Seeds\AdminSeeder;
use App\Database\Seeds\DatabaseSeeder;
use App\Entities\User;
use App\Models\AuditLogModel;
use App\Models\UserModel;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * @internal
 */
final class AdminTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected User $adminUser;
    protected User $staffUser;
    protected User $customerUser;
    protected string $adminToken;
    protected string $staffToken;
    protected string $customerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $tokenService = new TokenService();
        $userModel    = new UserModel();

        // 1. Admin
        $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        if (!$admin) {
            (new AdminSeeder())->run();
            $admin = $userModel->where('email', 'admin@wextechnology.com')->first();
        }
        $this->adminUser  = $admin;
        $this->adminToken = $tokenService->generateAccessToken($admin)['access_token'];

        // 2. Staff
        $staff = $userModel->where('email', 'staff_test@wextechnology.com')->first();
        if (!$staff) {
            $userModel->insert(new User([
                'name'      => 'موظف المتجر',
                'email'     => 'staff_test@wextechnology.com',
                'role'      => Wex::ROLE_STAFF,
                'is_active' => true,
            ]));
            $staff = $userModel->where('email', 'staff_test@wextechnology.com')->first();
        }
        $this->staffUser  = $staff;
        $this->staffToken = $tokenService->generateAccessToken($staff)['access_token'];

        // 3. Customer
        $customer = $userModel->where('email', 'cust_admin_test@example.com')->first();
        if (!$customer) {
            $userModel->insert(new User([
                'name'      => 'عميل تجريبي',
                'email'     => 'cust_admin_test@example.com',
                'role'      => Wex::ROLE_CUSTOMER,
                'is_active' => true,
            ]));
            $customer = $userModel->where('email', 'cust_admin_test@example.com')->first();
        } else {
            $userModel->update($customer->id, ['role' => Wex::ROLE_CUSTOMER, 'is_active' => 1]);
            $customer->role = Wex::ROLE_CUSTOMER;
            $customer->is_active = 1;
        }
        $this->customerUser  = $customer;
        $this->customerToken = $tokenService->generateAccessToken($customer)['access_token'];
    }

    public function testCustomerCannotAccessDashboard(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->get('/api/v1/admin/dashboard');

        $result->assertStatus(403);
    }

    public function testAdminAndStaffCanAccessDashboard(): void
    {
        // Admin
        $adminRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('/api/v1/admin/dashboard');

        $adminRes->assertStatus(200);
        $data = json_decode($adminRes->getJSON(), true)['data'];

        $this->assertArrayHasKey('orders_by_status', $data);
        $this->assertArrayHasKey('pending_reviews_count', $data);
        $this->assertArrayHasKey('today_orders', $data);
        $this->assertArrayHasKey('low_stock_products', $data);
        $this->assertArrayHasKey('exchange_rate', $data);
        $this->assertArrayHasKey('price_lock_hours', $data);

        // Staff
        $staffRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->staffToken,
        ])->get('/api/v1/admin/dashboard');

        $staffRes->assertStatus(200);
    }

    public function testAdminCanListUsersWithFilters(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('/api/v1/admin/users?role=customer&page=1&per_page=10');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertTrue($body['success']);
        $this->assertNotEmpty($body['data']);
        $this->assertSame('customer', $body['data'][0]['role']);
        $this->assertNotNull($body['meta']);
    }

    public function testStaffCannotListUsers(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->staffToken,
        ])->get('/api/v1/admin/users');

        $result->assertStatus(403);
    }

    public function testAdminCanViewUserDetails(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get("/api/v1/admin/users/{$this->customerUser->id}");

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertTrue($body['success']);
        $this->assertSame($this->customerUser->email, $body['data']['email']);
        $this->assertArrayHasKey('orders_count', $body['data']);
        $this->assertArrayHasKey('total_spent_sdg', $body['data']);
    }

    public function testAdminCanUpdateUserRoleAndLogsAudit(): void
    {
        // 1. Promote customer to staff
        $payload = ['role' => Wex::ROLE_STAFF];
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->patch("/api/v1/admin/users/{$this->customerUser->id}/role");

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertSame(Wex::ROLE_STAFF, $body['data']['role']);

        // Check DB
        $userModel = new UserModel();
        $updated = $userModel->find($this->customerUser->id);
        $this->assertSame(Wex::ROLE_STAFF, $updated->role);

        // Check audit log
        $auditModel = new AuditLogModel();
        $log = $auditModel
            ->where('action', 'change_user_role')
            ->where('entity_id', $this->customerUser->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertSame((int) $this->adminUser->id, (int) $log->user_id);
    }

    public function testAdminCannotChangeOwnRole(): void
    {
        $payload = ['role' => Wex::ROLE_CUSTOMER];
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->patch("/api/v1/admin/users/{$this->adminUser->id}/role");

        $result->assertStatus(403);
    }

    public function testAdminCanToggleUserStatus(): void
    {
        // Deactivate customer
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['is_active' => false]))->patch("/api/v1/admin/users/{$this->customerUser->id}/status");

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);
        $this->assertFalse($body['data']['is_active']);

        // Check DB
        $userModel = new UserModel();
        $updated = $userModel->find($this->customerUser->id);
        $this->assertFalse((bool) $updated->is_active);

        // Re-activate customer
        $reactivateRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode(['is_active' => true]))->patch("/api/v1/admin/users/{$this->customerUser->id}/status");

        $reactivateRes->assertStatus(200);
        $this->assertTrue(json_decode($reactivateRes->getJSON(), true)['data']['is_active']);
    }

    public function testAdminCanViewAuditLogs(): void
    {
        $result = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->adminToken,
        ])->get('/api/v1/admin/audit-logs?page=1&per_page=10');

        $result->assertStatus(200);
        $body = json_decode($result->getJSON(), true);

        $this->assertTrue($body['success']);
        $this->assertNotEmpty($body['data']);
        $this->assertArrayHasKey('action', $body['data'][0]);
        $this->assertArrayHasKey('entity', $body['data'][0]);
        $this->assertNotNull($body['meta']);
    }
}
