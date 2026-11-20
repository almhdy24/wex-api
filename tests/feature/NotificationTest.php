<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\User;
use App\Models\EmailOutboxModel;
use App\Models\NotificationModel;
use App\Models\ProductModel;
use App\Models\UserModel;
use App\Services\MailService;
use App\Services\NotificationService;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * @internal
 */
final class NotificationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    protected User $customerUser;
    protected string $customerToken;

    protected function setUp(): void
    {
        parent::setUp();

        $tokenService = new TokenService();
        $userModel    = new UserModel();

        $customer = $userModel->where('email', 'notif_cust@example.com')->first();
        if (!$customer) {
            $userModel->insert(new User([
                'name'                => 'ياسر محمد',
                'email'               => 'notif_cust@example.com',
                'role'                => Wex::ROLE_CUSTOMER,
                'email_notifications' => true,
                'is_active'           => true,
            ]));
            $customer = $userModel->where('email', 'notif_cust@example.com')->first();
        } else {
            $userModel->update($customer->id, ['email_notifications' => 1]);
            $customer->email_notifications = true;
        }
        $this->customerUser  = $customer;
        $this->customerToken = $tokenService->generateAccessToken($customer)['access_token'];
    }

    public function testNotificationsListingAndUnreadCount(): void
    {
        $notifService = new NotificationService();

        // 1. Create 2 test notifications
        $n1 = $notifService->notify(
            (int) $this->customerUser->id,
            'test_notif_1',
            'إشعار تجريبي أول',
            'محتوى الإشعار الأول'
        );

        $n2 = $notifService->notify(
            (int) $this->customerUser->id,
            'test_notif_2',
            'إشعار تجريبي ثانٍ',
            'محتوى الإشعار الثاني'
        );

        // 2. Check unread count
        $countRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->get('/api/v1/notifications/unread-count');

        $countRes->assertStatus(200);
        $countJson = json_decode($countRes->getJSON(), true);
        $this->assertTrue($countJson['success']);
        $this->assertGreaterThanOrEqual(2, $countJson['data']['unread_count']);

        // 3. List notifications
        $listRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->get('/api/v1/notifications?per_page=10');

        $listRes->assertStatus(200);
        $listJson = json_decode($listRes->getJSON(), true);
        $this->assertTrue($listJson['success']);
        $this->assertNotEmpty($listJson['data']);

        // 4. Mark single notification as read
        $markRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->patch("/api/v1/notifications/{$n1->id}/read");

        $markRes->assertStatus(200);
        $markJson = json_decode($markRes->getJSON(), true);
        $this->assertTrue($markJson['data']['is_read']);

        // 5. Mark all as read
        $allRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->post('/api/v1/notifications/mark-all-read');

        $allRes->assertStatus(200);

        // Check unread count becomes 0
        $zeroCountRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->get('/api/v1/notifications/unread-count');

        $zeroJson = json_decode($zeroCountRes->getJSON(), true);
        $this->assertSame(0, $zeroJson['data']['unread_count']);
    }

    public function testOrderCreationDispatchesNotificationAndEmailOutbox(): void
    {
        $productModel = new ProductModel();
        $productModel->update(1, ['stock_quantity' => 10]);

        $notifModel  = new NotificationModel();
        $outboxModel = new EmailOutboxModel();

        $maxNotifId  = (int) ($notifModel->selectMax('id')->first()?->id ?? 0);
        $maxOutboxId = (int) ($outboxModel->selectMax('id')->first()?->id ?? 0);

        $payload = [
            'order_type'    => 'pickup',
            'contact_phone' => '0912345678',
            'items'         => [['product_id' => 1, 'quantity' => 1]],
        ];

        $createRes = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
            'Content-Type'  => 'application/json',
        ])->withBody(json_encode($payload))->post('/api/v1/orders');

        $createRes->assertStatus(201);
        $order = json_decode($createRes->getJSON(), true)['data'];

        // Check notification recorded
        $notif = $notifModel
            ->where('id >', $maxNotifId)
            ->where('user_id', $this->customerUser->id)
            ->where('type', 'order_created')
            ->first();

        $this->assertNotNull($notif);
        $this->assertSame('تم استلام طلبك بنجاح', $notif->title);

        // Check email outbox queued
        $email = $outboxModel
            ->where('id >', $maxOutboxId)
            ->where('user_id', $this->customerUser->id)
            ->where('template', 'order_created')
            ->first();

        $this->assertNotNull($email);
        $this->assertSame('pending', $email->status);
        $this->assertSame($this->customerUser->email, $email->to_email);
    }

    public function testMailServiceProcessesOutboxAndLogsEmail(): void
    {
        $mailService = new MailService();

        // Enqueue email
        $mailService->enqueue(
            userId: (int) $this->customerUser->id,
            toEmail: 'test_dispatch@example.com',
            subject: 'اختبار إرسال البريد',
            template: 'order_created',
            payload: [
                'order_number'        => 'WEX-TEST-001',
                'delivery_type'       => 'pickup',
                'total_sdg'           => 120000,
                'lock_expires_at'     => '2026-11-19 22:00 UTC',
                'bank_account_name'   => 'Wex Technology',
                'bank_account_number' => '2849102',
            ]
        );

        // Process outbox (batch size large enough to handle prior test emails in shared test DB)
        $result = $mailService->processOutbox(100);
        $this->assertGreaterThanOrEqual(1, $result['sent']);

        // Check status updated in outbox
        $outboxModel = new EmailOutboxModel();
        $sentEmail = $outboxModel->where('to_email', 'test_dispatch@example.com')->orderBy('id', 'DESC')->first();
        $this->assertNotNull($sentEmail);
        $this->assertSame('sent', $sentEmail->status);
        $this->assertNotNull($sentEmail->sent_at);
    }

    public function testUserWithDisabledEmailNotificationsDoesNotEnqueueEmail(): void
    {
        $userModel = new UserModel();
        $userModel->update($this->customerUser->id, ['email_notifications' => 0]);

        $notifService = new NotificationService();
        $initialOutboxCount = (new EmailOutboxModel())->where('user_id', $this->customerUser->id)->countAllResults();

        $notifService->notify(
            userId: (int) $this->customerUser->id,
            type: 'test_no_email',
            title: 'إشعار بدون بريد',
            body: 'هذا الإشعار داخلي فقط لأن العميل عطل الإشعارات البريدية.',
            orderId: null,
            emailTemplate: 'order_created',
            emailPayload: []
        );

        $newOutboxCount = (new EmailOutboxModel())->where('user_id', $this->customerUser->id)->countAllResults();
        $this->assertSame($initialOutboxCount, $newOutboxCount);
    }
}
