<?php

namespace Tests\Unit;

use App\Libraries\PushEndpointPolicy;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * API-PUSH-01: client-supplied Web Push endpoints must not become an SSRF primitive.
 *
 * @internal
 */
final class PushEndpointPolicyTest extends CIUnitTestCase
{
    public static function rejected(): array
    {
        return [
            'metadata'      => ['http://169.254.169.254/latest/meta-data/'],
            'https internal'=> ['https://10.0.0.5/push'],
            'localhost'     => ['https://localhost/admin'],
            'plain http fcm'=> ['http://fcm.googleapis.com/fcm/send/x'],
            'lookalike'     => ['https://fcm.googleapis.com.evil.example/x'],
            'suffix trick'  => ['https://evilfcm.googleapis.com.attacker.io/x'],
            'userinfo'      => ['https://fcm.googleapis.com@evil.example/x'],
            'odd port'      => ['https://fcm.googleapis.com:8443/fcm/send/x'],
            'garbage'       => ['not-a-url'],
        ];
    }

    /**
     * @dataProvider rejected
     */
    public function testRejectsNonPushServiceEndpoints(string $url): void
    {
        $this->assertFalse(PushEndpointPolicy::isAllowed($url), $url);
    }

    public function testAcceptsBrowserPushServices(): void
    {
        foreach ([
            'https://fcm.googleapis.com/fcm/send/abc',
            'https://updates.push.services.mozilla.com/wpush/v2/abc',
            'https://web.push.apple.com/QWERTY',
            'https://db5p.notify.windows.com/w/?token=abc',
        ] as $url) {
            $this->assertTrue(PushEndpointPolicy::isAllowed($url), $url);
        }
    }
}
