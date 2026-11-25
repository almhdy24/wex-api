<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\User;
use App\Models\NotificationModel;
use App\Models\OrderModel;
use App\Models\PushOutboxModel;
use App\Models\PushSubscriptionModel;
use App\Models\UserModel;
use App\Services\Push\FakeProvider;
use App\Services\PushService;
use App\Services\SettingService;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * @internal
 */
final class PushNotificationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    private User $customer;
    private User $customer2;
    private User $admin;
    private string $customerToken;
    private string $customer2Token;
    private string $adminToken;
    private FakeProvider $fakeProvider;
    private PushService $pushService;

    protected function setUp(): void
    {
        parent::setUp();

        $userModel    = new UserModel();
        $tokenService = new TokenService();

        $this->customer = $this->ensureUser($userModel, 'push-cust1@wex.sd', 'عميل دفع 1', Wex::ROLE_CUSTOMER);
        $this->customer2 = $this->ensureUser($userModel, 'push-cust2@wex.sd', 'عميل دفع 2', Wex::ROLE_CUSTOMER);
        $this->admin = $this->ensureUser($userModel, 'admin@wextechnology.com', 'مدير النظام', Wex::ROLE_ADMIN);

        $this->customerToken  = $tokenService->generateAccessToken($this->customer)['access_token'];
        $this->customer2Token = $tokenService->generateAccessToken($this->customer2)['access_token'];
        $this->adminToken     = $tokenService->generateAccessToken($this->admin)['access_token'];

        (new SettingService())->set('push_enabled', '1');
        $this->db->table('push_outbox')->where('id >', 0)->delete();
        $this->db->table('push_subscriptions')->where('id >', 0)->delete();
        $this->db->table('notification_preferences')->where('id >', 0)->delete();

        $this->fakeProvider = new FakeProvider('');
        $this->pushService  = new PushService(overrideProvider: $this->fakeProvider);
    }

    private function ensureUser(UserModel $model, string $email, string $name, string $role): User
    {
        $u = $model->where('email', $email)->first();
        if ($u) {
            return $u;
        }
        $id = $model->insert(new User([
            'name'      => $name,
            'email'     => $email,
            'role'      => $role,
            'is_active' => 1,
        ]));
        return $model->find($id);
    }

    public function testSubscriptionRequiresAuthAndValidatesPayload(): void
    {
        $res = $this->post('/api/v1/push/subscriptions', []);
        $res->assertStatus(401);

        $bad = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->withBodyFormat('json')->post('/api/v1/push/subscriptions', [
            'platform'     => 'web',
            'provider'     => 'webpush',
            'subscription' => ['endpoint' => 'not-a-url', 'keys' => []],
        ]);
        $bad->assertStatus(422);
    }

    public function testUserRebindingOnSameEndpointAndUnsubscribe(): void
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/rebind-' . uniqid();
        $payload = [
            'platform'     => 'web',
            'provider'     => 'webpush',
            'subscription' => [
                'endpoint' => $endpoint,
                'keys'     => ['p256dh' => 'BTestKey123', 'auth' => 'Auth123'],
            ],
            'device_label' => 'Chrome Test',
        ];

        $r1 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customerToken,
        ])->withBodyFormat('json')->post('/api/v1/push/subscriptions', $payload);
        $r1->assertStatus(201);

        $r2 = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customer2Token,
        ])->withBodyFormat('json')->post('/api/v1/push/subscriptions', $payload);
        $r2->assertStatus(201);

        $subModel = new PushSubscriptionModel();
        $sub = $subModel->where('endpoint_hash', PushSubscriptionModel::hashEndpoint($endpoint))->first();
        $this->assertNotNull($sub);
        $this->assertSame((int) $this->customer2->id, (int) $sub->user_id);

        $del = $this->withHeaders([
            'Authorization' => 'Bearer ' . $this->customer2Token,
        ])->withBodyFormat('json')->delete('/api/v1/push/subscriptions', ['endpoint_or_token' => $endpoint]);
        $del->assertStatus(200);

        $this->assertNull($subModel->where('endpoint_hash', PushSubscriptionModel::hashEndpoint($endpoint))->first());
    }

    public function testPreferenceFilteringKillSwitchActorExclusionAndTag(): void
    {
        $this->pushService->registerSubscription((int) $this->customer->id, [
            'platform'          => 'web',
            'provider'          => 'webpush',
            'endpoint_or_token' => 'https://push.example/sub-' . uniqid(),
            'keys'              => ['p256dh' => 'k1', 'auth' => 'a1'],
        ]);

        // 1. Actor exclusion
        $actorRes = $this->pushService->sendToUser((int) $this->customer->id, [
            'type'          => 'order_created',
            'title'         => 'طلب جديد',
            'body'          => 'نص',
            'actor_user_id' => (int) $this->customer->id,
        ]);
        $this->assertNull($actorRes);
        $this->assertCount(0, $this->fakeProvider->sent);

        // 2. Kill switch OFF -> in-app created, 0 push sends
        (new SettingService())->set('push_enabled', '0');
        $notifId = $this->pushService->sendToUser((int) $this->customer->id, [
            'type'         => 'order_created',
            'title'        => 'تم استلام طلبك',
            'body'         => 'تم إنشاء طلبك رقم WEX-TEST-1',
            'order_number' => 'WEX-TEST-1',
        ]);
        $this->assertNotNull($notifId);
        $this->assertCount(0, $this->fakeProvider->sent);

        // 3. Kill switch ON, preference OFF -> 0 push sends
        (new SettingService())->set('push_enabled', '1');
        $this->pushService->updatePreferences((int) $this->customer->id, ['order_updates' => false]);
        $this->pushService->sendToUser((int) $this->customer->id, [
            'type'         => 'order_confirmed',
            'title'        => 'تأكيد',
            'body'         => 'مؤكد',
            'order_number' => 'WEX-TEST-1',
        ]);
        $this->assertCount(0, $this->fakeProvider->sent);

        // 4. Preference ON -> 1 push send with order tag
        $this->pushService->updatePreferences((int) $this->customer->id, ['order_updates' => true]);
        $this->pushService->sendToUser((int) $this->customer->id, [
            'type'         => 'order_confirmed',
            'title'        => 'تأكيد',
            'body'         => 'طلبك رقم WEX-TEST-1 مؤكد',
            'order_number' => 'WEX-TEST-1',
        ]);
        $this->assertCount(1, $this->fakeProvider->sent);
        $this->assertSame('order-WEX-TEST-1', $this->fakeProvider->sent[0]['payload']['tag']);
    }

    public function testRetryBackoffAndExpiryDeactivation(): void
    {
        $sub = $this->pushService->registerSubscription((int) $this->customer->id, [
            'platform'          => 'android',
            'provider'          => 'fcm',
            'endpoint_or_token' => 'fcm-token-' . uniqid(),
        ]);

        // Force failure -> should schedule retry with attempts=1
        $this->fakeProvider->setForceFailure('Temporary network error');
        $this->pushService->sendToUser((int) $this->customer->id, [
            'type'  => 'payment_approved',
            'title' => 'تم تأكيد الدفع',
            'body'  => 'تم تأكيد الدفع',
        ]);

        $outboxModel = new PushOutboxModel();
        $row = $outboxModel->where('subscription_id', $sub->id)->first();
        $this->assertSame('pending', $row['status']);
        $this->assertSame(1, (int) $row['attempts']);
        $this->assertNotNull($row['next_attempt_at']);

        // Simulate 4 more retries -> status becomes 'failed'
        for ($i = 0; $i < 4; $i++) {
            $outboxModel->update($row['id'], ['next_attempt_at' => gmdate('Y-m-d H:i:s', time() - 10)]);
            $this->pushService->processOutbox(10);
        }
        $rowAfter = $outboxModel->find($row['id']);
        $this->assertSame('failed', $rowAfter['status']);
        $this->assertSame(5, (int) $rowAfter['attempts']);

        // Expiry -> subscription marked is_active = 0
        $this->fakeProvider->setForceFailure(null);
        $this->fakeProvider->setShouldExpire(true);
        $this->pushService->sendToUser((int) $this->customer->id, [
            'type'  => 'payment_approved',
            'title' => 'تم تأكيد الدفع',
            'body'  => 'تم تأكيد الدفع',
        ]);

        $subAfter = (new PushSubscriptionModel())->find($sub->id);
        $this->assertFalse($subAfter->is_active);
    }

    public function testStaleEventSkippingAndBroadcastSegments(): void
    {
        $this->pushService->registerSubscription((int) $this->customer->id, [
            'platform'          => 'web',
            'provider'          => 'webpush',
            'endpoint_or_token' => 'https://push.example/seg1-' . uniqid(),
            'keys'              => ['p256dh' => 'k1', 'auth' => 'a1'],
        ]);
        $this->pushService->registerSubscription((int) $this->customer2->id, [
            'platform'          => 'web',
            'provider'          => 'webpush',
            'endpoint_or_token' => 'https://push.example/seg2-' . uniqid(),
            'keys'              => ['p256dh' => 'k2', 'auth' => 'a2'],
        ]);

        // Create an open order for customer 1 only
        $orderModel = new OrderModel();
        $orderId = $orderModel->insert([
            'order_number'     => 'WEX-OPEN-' . uniqid(),
            'user_id'          => $this->customer->id,
            'status'           => Wex::ORDER_STATUS_CONFIRMED,
            'delivery_type'    => Wex::DELIVERY_TYPE_PICKUP,
            'delivery_phone'   => '+249900000000',
            'subtotal_aed'      => 100,
            'subtotal_sdg'      => 65000,
            'discount_sdg'      => 0,
            'discount_percent'  => 0,
            'delivery_fee_sdg' => 0,
            'total_sdg'         => 65000,
            'rate_sdg_snapshot' => 650,
            'exchange_rate_sdg' => 650,
            'lock_expires_at'  => gmdate('Y-m-d H:i:s', time() + 3600),
            'created_at'       => gmdate('Y-m-d H:i:s'),
            'updated_at'       => gmdate('Y-m-d H:i:s'),
        ]);

        // Stale event: order is 'confirmed', expected 'pending_payment' -> skipped
        $stale = $this->pushService->sendToUser((int) $this->customer->id, [
            'type'                  => 'price_lock_expiring',
            'title'                 => 'تذكير',
            'body'                  => 'تذكير',
            'order_id'              => (int) $orderId,
            'expected_order_status' => Wex::ORDER_STATUS_PENDING_PAYMENT,
        ]);
        $this->assertNull($stale);
        $this->assertCount(0, $this->fakeProvider->sent);

        // Broadcast to 'with-open-orders' -> only customer 1 receives it
        $bRes = $this->pushService->broadcast('with-open-orders', [
            'title' => 'تحديث',
            'body'  => 'رسالة للطلبات المفتوحة',
        ]);
        $this->assertGreaterThanOrEqual(1, $bRes['targeted_users']);
        $this->assertCount(1, $this->fakeProvider->sent);
        $this->assertSame((int) $this->customer->id, (int) $this->fakeProvider->sent[0]['user_id']);
    }
}
