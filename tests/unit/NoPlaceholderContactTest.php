<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * API-CONTACT-01: placeholder phone numbers ('+249 9XX XXX XXX', 249912345678) were
 * seeded, returned by GET /shop as fallbacks and printed in customer emails.
 *
 * @internal
 */
final class NoPlaceholderContactTest extends CIUnitTestCase
{
    public function testNoPlaceholderNumbersInAppCode(): void
    {
        $hits = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(APPPATH));
        foreach ($it as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            foreach (['9XX XXX', '249912345678', '249912300000'] as $needle) {
                if (str_contains($src, $needle)) {
                    $hits[] = $file->getPathname() . ' contains ' . $needle;
                }
            }
        }
        $this->assertSame([], $hits);
    }

    public function testPickupEmailOmitsPhoneRowWhenUnset(): void
    {
        $html = view('emails/ready_for_pickup', ['order_number' => 'WEX-1', 'shop_phone' => null]);
        $this->assertStringNotContainsString('هاتف المحل', $html);

        $html = view('emails/ready_for_pickup', ['order_number' => 'WEX-1', 'shop_phone' => '0911111111']);
        $this->assertStringContainsString('0911111111', $html);
    }
}
