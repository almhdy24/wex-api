<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Entities\User;
use App\Models\UserModel;
use App\Services\OtpService;
use App\Services\TokenService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Wex;

/**
 * API-USR-01: deleting an account must free its email for re-registration,
 * strip PII and stop push delivery.
 *
 * @internal
 */
final class AccountDeletionTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    public function testDeletedAccountEmailCanSignUpAgainAndPiiIsRemoved(): void
    {
        $email = 'deleteme_' . bin2hex(random_bytes(3)) . '@wex.sd';
        $users = new UserModel();
        $id    = $users->insert(new User([
            'name' => 'سيُحذف', 'email' => $email, 'phone' => '0912345678',
            'google_id' => 'g-' . bin2hex(random_bytes(4)), 'role' => Wex::ROLE_CUSTOMER, 'is_active' => true,
        ]));
        $user  = $users->find($id);
        $token = (new TokenService())->generateAccessToken($user)['access_token'];

        $db = \Config\Database::connect();
        $db->table('push_subscriptions')->insert([
            'user_id' => $id, 'platform' => 'web', 'provider' => 'webpush',
            'endpoint_or_token' => 'https://push.example/' . $id, 'endpoint_hash' => hash('sha256', 'e' . $id),
            'is_active' => 1, 'created_at' => gmdate('Y-m-d H:i:s'), 'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->withHeaders(['Authorization' => 'Bearer ' . $token])->delete('api/v1/me')->assertStatus(200);

        $deleted = $users->withDeleted()->find($id);
        $this->assertNotSame($email, $deleted->email);
        $this->assertNull($deleted->phone);
        $this->assertNull($deleted->google_id);
        $this->assertSame(0, (int) $db->table('push_subscriptions')->where('user_id', $id)->where('is_active', 1)->countAllResults());

        // Re-register with the same email via OTP.
        $code = (new OtpService())->generate($email);
        $res  = $this->withBody(json_encode(['email' => $email, 'code' => $code, 'name' => 'عاد من جديد']))
            ->post('api/v1/auth/email/verify');
        $res->assertStatus(200);
        $this->assertNotSame((int) $id, (int) json_decode($res->getJSON(), true)['data']['user']['id']);
    }
}
