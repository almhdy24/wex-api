<?php

namespace Tests\Feature;

use App\Database\Seeds\DatabaseSeeder;
use App\Services\MailService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * API-MAIL-01: an overlapping mail:send run (started while the first is still in the
 * SMTP call) must not send the same email again.
 *
 * @internal
 */
final class MailOutboxClaimTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace   = 'App';
    protected $migrateOnce = true;
    protected $refresh     = false;
    protected $seed        = DatabaseSeeder::class;

    public function testOverlappingWorkerDoesNotResendInFlightEmail(): void
    {
        $db = \Config\Database::connect();
        $db->table('email_outbox')->where('status', 'pending')->update(['status' => 'sent']);

        $mail = new class () extends MailService {
            public int $sends = 0;
            private bool $nested = false;

            public function send(string $to, string $subject, string $htmlBody, ?string $altText = null): bool
            {
                $this->sends++;
                if (!$this->nested) {
                    // A second cron run starts while this SMTP call is in flight.
                    $this->nested = true;
                    $this->processOutbox(20);
                }

                return true;
            }
        };

        $mail->enqueue(null, 'claim@wex.sd', 'اختبار', 'order_expired', ['order_number' => 'WEX-TEST-1']);
        $mail->processOutbox(20);

        $this->assertSame(1, $mail->sends);
    }
}
