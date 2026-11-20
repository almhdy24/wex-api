<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Verifies that no invented business claims (warranty, trial periods,
 * authorized dealership, artificial inspection guarantees) exist in views, seeders, or controllers.
 *
 * @internal
 */
final class ClaimsScannerTest extends CIUnitTestCase
{
    protected array $forbiddenPatterns = [
        '/ضمان\s+(معتمد|شامل|سنتين|الوكيل|المحل|استبدال)/u',
        '/فحص\s+(شامل|مخبري|واختبار)/u',
        '/فترة\s+تجربة/u',
        '/قبل\s+سداد\s+أي\s+مبلغ/u',
        '/استرجاع\s+خلال/u',
        '/استبدال\s+مجاني/u',
        '/وكيل\s+(معتمد|رسمي|تجاري)/u',
        '/موزع\s+رسمي/u',
        '/التوصيل\s+الآمن/u',
    ];

    public function testViewsAndSeedersContainZeroInventedClaims(): void
    {
        $scanPaths = [
            APPPATH . 'Views',
            APPPATH . 'Database/Seeds',
            APPPATH . 'Controllers',
        ];

        $violations = [];

        foreach ($scanPaths as $dir) {
            if (!is_dir($dir)) continue;

            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') continue;

                $content = file_get_contents($file->getPathname());
                foreach ($this->forbiddenPatterns as $pattern) {
                    if (preg_match($pattern, $content, $matches)) {
                        $violations[] = basename($file->getPathname()) . " matched '{$matches[0]}'";
                    }
                }
            }
        }

        $this->assertEmpty($violations, 'Found invented claims violations: ' . implode(', ', $violations));
    }
}
