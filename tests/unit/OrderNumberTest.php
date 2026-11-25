<?php

namespace Tests\Unit;

use App\Services\OrderService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * API-ORD-08: order numbers are shown to customers and must never contain "AED"
 * (hex suffixes could, e.g. WEX-261125-AED90).
 *
 * @internal
 */
final class OrderNumberTest extends CIUnitTestCase
{
    public function testAlphabetCannotSpellForbiddenCurrencyOrWords(): void
    {
        $alphabet = OrderService::ORDER_SUFFIX_ALPHABET;
        foreach (['A', 'E', 'D', 'I', 'O', 'U', '0', '1'] as $ch) {
            $this->assertStringNotContainsString($ch, $alphabet);
        }
    }

    public function testGeneratedSuffixesUseOnlyTheSafeAlphabet(): void
    {
        for ($i = 0; $i < 2000; $i++) {
            $suffix = OrderService::randomSuffix(5);
            $this->assertMatchesRegularExpression('/^[' . OrderService::ORDER_SUFFIX_ALPHABET . ']{5}$/', $suffix);
            $this->assertStringNotContainsStringIgnoringCase('aed', $suffix);
        }
    }
}
