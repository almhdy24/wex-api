<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * ClaimsScanTest
 *
 * Verifies that API seeders, migrations with seed data, language files,
 * and email templates contain zero forbidden marketing claims or placeholder data.
 */
class ClaimsScanTest extends CIUnitTestCase
{
    protected array $forbiddenWords = [
        'ضمان',
        'فحص',
        'معاينة',
        'اختبار',
        'تجربة',
        'مختارة بعناية',
        'استبدال',
        'استرجاع',
        'معتمد',
        'وكيل',
        'رسمي',
        'مضمون',
        '2849102',
        'WEX-DBG',
        'فاتورة',
    ];

    public function testSeedsAndTemplatesContainZeroForbiddenClaims(): void
    {
        $scanDirs = [
            APPPATH . 'Database/Seeds',
            APPPATH . 'Database/Migrations',
            APPPATH . 'Language',
            APPPATH . 'Views/emails',
        ];

        $violations = [];

        foreach ($scanDirs as $dir) {
            if (!is_dir($dir)) {
                continue;
            }

            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($it as $file) {
                if ($file->isDir() || $file->getExtension() !== 'php') {
                    continue;
                }

                // Skip DevSeeder if created
                if ($file->getFilename() === 'DevSeeder.php') {
                    continue;
                }

                $content = file_get_contents($file->getPathname());
                // Remove PHP comments
                $clean = preg_replace('!/\*.*?\*/!s', '', $content);
                $clean = preg_replace('!//.*?$!m', '', $clean);

                foreach ($this->forbiddenWords as $word) {
                    if (str_contains($clean, $word)) {
                        $violations[] = basename($dir) . '/' . $file->getFilename() . ' contains forbidden: "' . $word . '"';
                    }
                }
            }
        }

        $this->assertEmpty($violations, "Found forbidden claims/placeholders in API files:\n" . implode("\n", $violations));
    }
}
