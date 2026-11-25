<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\PushSubscription;
use App\Models\PushOutboxModel;
use App\Models\UserModel;
use App\Services\Push\PushProviderInterface;
use App\Services\Push\PushResult;
use App\Services\PushService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * API-PUSH-02: a pending outbox row picked up by two deliverers (inline + push:work,
 * or two overlapping cron runs) must be sent exactly once.
 *
 * @internal
 */
final class PushOutboxClaimTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    public function testSameRowDeliveredByTwoWorkersIsSentOnce(): void
    {
        $user = (new UserModel())->where('email', 'admin@wextechnology.com')->first();
        $db   = \Config\Database::connect();
        $now  = gmdate('Y-m-d H:i:s');

        $db->table('push_subscriptions')->insert([
            'user_id' => $user->id, 'platform' => 'web', 'provider' => 'webpush',
            'endpoint_or_token' => 'https://fcm.googleapis.com/fcm/send/claim-' . bin2hex(random_bytes(4)),
            'endpoint_hash' => bin2hex(random_bytes(16)), 'keys_json' => '{"p256dh":"x","auth":"y"}',
            'is_active' => 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $subId = (int) $db->insertID();

        $outbox = new PushOutboxModel();
        $rowId  = (int) $outbox->insert([
            'subscription_id' => $subId, 'status' => 'pending', 'attempts' => 0,
            'payload_json' => json_encode(['title' => 't', 'body' => 'b', 'data' => []]), 'created_at' => $now,
        ]);

        $counter = new class () implements PushProviderInterface {
            public int $sends = 0;

            public function send(PushSubscription $subscription, array $payload): PushResult
            {
                $this->sends++;

                return PushResult::ok();
            }
        };

        $service = new class (null, null, null, null, null, null, null, $counter) extends PushService {
            public function deliver(array $rows): array
            {
                return $this->deliverRows($rows);
            }
        };

        // Both workers fetched the row while it was still pending.
        $staleRows = $outbox->whereIn('id', [$rowId])->findAll();
        $service->deliver($staleRows);
        $service->deliver($staleRows);

        $this->assertSame(1, $counter->sends);
        $this->assertSame('sent', $outbox->find($rowId)['status']);
    }
}
