<?php

namespace Tests\Unit;

use App\Services\PricingService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class PricingServiceTest extends CIUnitTestCase
{
    protected PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new PricingService();
    }

    public function testRoundingStepDefault(): void
    {
        // 500 with step 100 stays 500
        $this->assertSame(500, $this->pricing->roundSdg(500, 100));

        // 501 with step 100 rounds up to 600
        $this->assertSame(600, $this->pricing->roundSdg(501, 100));

        // 599 with step 100 rounds up to 600
        $this->assertSame(600, $this->pricing->roundSdg(599, 100));

        // 0 stays 0
        $this->assertSame(0, $this->pricing->roundSdg(0, 100));
    }

    public function testConvertAedToSdg(): void
    {
        // 1000 AED at 650 SDG/AED = 650,000 SDG
        $converted = $this->pricing->convertAedToSdg(1000, 650, 100);
        $this->assertSame(650000, $converted);

        // 1 AED at 650 SDG/AED = 650 SDG, rounded up with step 100 = 700 SDG
        $this->assertSame(700, $this->pricing->convertAedToSdg(1, 650, 100));
    }

    public function testCalculateOrderTotals(): void
    {
        // 1400 AED subtotal at 650 rate = 910,000 SDG
        // 3% discount = 27,300 SDG (rounded to 27,300 with step 100)
        // delivery fee = 5,000 SDG
        // total_sdg = 910,000 - 27,300 + 5,000 = 887,700 SDG
        $totals = $this->pricing->calculateOrderTotals(
            subtotalAed: 1400,
            rateSdg: 650,
            discountPercent: 3,
            deliveryFeeSdg: 5000,
            roundingStep: 100
        );

        $this->assertSame(1400, $totals['subtotal_aed']);
        $this->assertSame(650000 + 260000, $totals['subtotal_sdg']); // 910,000
        $this->assertSame(27300, $totals['discount_sdg']);
        $this->assertSame(5000, $totals['delivery_fee_sdg']);
        $this->assertSame(887700, $totals['total_sdg']);
    }

    public function testZeroDiscountCalculation(): void
    {
        $totals = $this->pricing->calculateOrderTotals(
            subtotalAed: 1000,
            rateSdg: 700,
            discountPercent: 0,
            deliveryFeeSdg: 0,
            roundingStep: 100
        );

        $this->assertSame(1000, $totals['subtotal_aed']);
        $this->assertSame(700000, $totals['subtotal_sdg']);
        $this->assertSame(0, $totals['discount_sdg']);
        $this->assertSame(0, $totals['delivery_fee_sdg']);
        $this->assertSame(700000, $totals['total_sdg']);
    }
}
