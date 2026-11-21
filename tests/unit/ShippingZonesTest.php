<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;
use App\Database\Seeds\ShippingZonesSeeder;

/**
 * @internal
 */
final class ShippingZonesTest extends CIUnitTestCase
{
    public function testShippingZonesSpelling(): void
    {
        $seederFile = APPPATH . 'Database/Seeds/ShippingZonesSeeder.php';
        $content = file_get_contents($seederFile);

        // Must not contain misspelled 'آم درمان'
        $this->assertStringNotContainsString('آم درمان', $content, "Misspelled 'آم درمان' must not exist in seeder.");

        // Must contain correctly spelled Khartoum localities
        $this->assertStringContainsString('أم درمان', $content);
        $this->assertStringContainsString('الخرطوم', $content);
        $this->assertStringContainsString('بحري', $content);

        // Required states
        $expectedZones = [
            'أم درمان', 'الخرطوم', 'بحري', 'الجزيرة', 'البحر الأحمر', 'كسلا',
            'القضارف', 'سنار', 'النيل الأبيض', 'النيل الأزرق', 'نهر النيل',
            'الشمالية', 'شمال كردفان', 'جنوب كردفان', 'غرب كردفان',
            'شمال دارفور', 'جنوب دارفور', 'غرب دارفور', 'وسط دارفور', 'شرق دارفور'
        ];

        foreach ($expectedZones as $zone) {
            $this->assertStringContainsString($zone, $content, "Zone '{$zone}' must be correctly spelled in seeder.");
        }
    }
}
