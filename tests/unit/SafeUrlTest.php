<?php

namespace Tests\Unit;

use App\Libraries\SafeUrl;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * API-XSS-01: admin/staff link fields (announcement link_url, shop_map_link) are
 * rendered as href on the storefront and must not carry script URLs.
 *
 * @internal
 */
final class SafeUrlTest extends CIUnitTestCase
{
    public function testRejectsScriptAndProtocolRelativeUrls(): void
    {
        foreach ([
            'javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', "java\tscript:alert(1)",
            'data:text/html,<script>alert(1)</script>', 'vbscript:msgbox(1)',
            '//evil.example/x', '/\\evil.example', 'https://user@evil.example/', 'ftp://x.example/',
        ] as $url) {
            $this->assertFalse(SafeUrl::isSafeLink($url), $url);
        }
    }

    public function testAcceptsPathsAndWebUrls(): void
    {
        foreach ([null, '', '/products/iphone-15', '/faq#returns', 'https://maps.google.com/?q=Omdurman', 'http://example.sd/x'] as $url) {
            $this->assertTrue(SafeUrl::isSafeLink($url), (string) $url);
        }
    }
}
