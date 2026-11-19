<?php

namespace App\Services;

use App\Models\ExchangeRateModel;
use App\Models\SettingModel;
use Config\Wex;

/**
 * PricingService
 *
 * The single source of truth for all pricing, currency conversion,
 * discount, and rounding calculations across the platform.
 * All monetary calculations use strict integer arithmetic.
 */
class PricingService
{
    protected SettingModel $settingModel;
    protected ExchangeRateModel $rateModel;

    protected ?int $cachedRate = null;
    protected ?string $cachedRateUpdatedAt = null;
    protected ?int $cachedRoundingStep = null;
    protected ?int $cachedAppDiscountPercent = null;

    public function __construct(
        ?SettingModel $settingModel = null,
        ?ExchangeRateModel $rateModel = null
    ) {
        $this->settingModel = $settingModel ?? new SettingModel();
        $this->rateModel    = $rateModel ?? new ExchangeRateModel();
    }

    /**
     * Retrieves current exchange rate in whole SDG per 1 AED.
     */
    public function getCurrentRateSdg(): int
    {
        if ($this->cachedRate !== null) {
            return $this->cachedRate;
        }

        $row = $this->settingModel->where('key', 'exchange_rate_sdg')->first();
        $this->cachedRate = $row ? (int) $row->value : (new Wex())->defaultExchangeRateSdg;

        return $this->cachedRate;
    }

    /**
     * Retrieves the UTC timestamp when the current exchange rate was last updated.
     */
    public function getRateUpdatedAt(): string
    {
        if ($this->cachedRateUpdatedAt !== null) {
            return $this->cachedRateUpdatedAt;
        }

        $row = $this->settingModel->where('key', 'exchange_rate_sdg')->first();
        if ($row && !empty($row->updated_at)) {
            $this->cachedRateUpdatedAt = gmdate('Y-m-d\TH:i:s\Z', strtotime($row->updated_at));
        } else {
            $this->cachedRateUpdatedAt = gmdate('Y-m-d\TH:i:s\Z');
        }

        return $this->cachedRateUpdatedAt;
    }

    /**
     * Retrieves the rounding step in whole SDG (default 100 SDG).
     */
    public function getRoundingStepSdg(): int
    {
        if ($this->cachedRoundingStep !== null) {
            return $this->cachedRoundingStep;
        }

        $row = $this->settingModel->where('key', 'rounding_step_sdg')->first();
        $this->cachedRoundingStep = $row ? max(1, (int) $row->value) : (new Wex())->defaultRoundingStepSdg;

        return $this->cachedRoundingStep;
    }

    /**
     * Retrieves the platform app discount percentage (default 3%).
     */
    public function getAppDiscountPercent(): int
    {
        if ($this->cachedAppDiscountPercent !== null) {
            return $this->cachedAppDiscountPercent;
        }

        $row = $this->settingModel->where('key', 'app_discount_percent')->first();
        $this->cachedAppDiscountPercent = $row ? max(0, min(100, (int) $row->value)) : (new Wex())->defaultAppDiscountPercent;

        return $this->cachedAppDiscountPercent;
    }

    /**
     * Converts whole AED dirhams to whole SDG at a specific rate with rounding applied.
     */
    public function convertAedToSdg(int $priceAed, ?int $rateSdg = null, ?int $roundingStep = null): int
    {
        $rate     = $rateSdg ?? $this->getCurrentRateSdg();
        $step     = $roundingStep ?? $this->getRoundingStepSdg();
        $rawSdg   = $priceAed * $rate;

        return $this->roundSdg($rawSdg, $step);
    }

    /**
     * Rounds an SDG amount up to the nearest configured step (e.g. 100 SDG).
     */
    public function roundSdg(int $amountSdg, ?int $step = null): int
    {
        $roundingStep = $step ?? $this->getRoundingStepSdg();
        if ($roundingStep <= 1) {
            return $amountSdg;
        }

        return (int) (ceil($amountSdg / $roundingStep) * $roundingStep);
    }

    /**
     * Computes the order price breakdown:
     * - subtotal_aed
     * - subtotal_sdg (converted at snapshot rate)
     * - discount_sdg (computed percentage from subtotal_sdg, rounded)
     * - delivery_fee_sdg (added as-is)
     * - total_sdg (rounded final total)
     *
     * @param int $subtotalAed Sum of item line totals in AED
     * @param int $rateSdg Snapshot exchange rate
     * @param int $discountPercent Snapshot discount percentage
     * @param int $deliveryFeeSdg Delivery fee in whole SDG
     * @param int|null $roundingStep Rounding step in whole SDG
     * @return array{subtotal_aed: int, subtotal_sdg: int, discount_sdg: int, delivery_fee_sdg: int, total_sdg: int}
     */
    public function calculateOrderTotals(
        int $subtotalAed,
        int $rateSdg,
        int $discountPercent,
        int $deliveryFeeSdg,
        ?int $roundingStep = null
    ): array {
        $step = $roundingStep ?? $this->getRoundingStepSdg();

        // 1. Raw Subtotal in SDG
        $rawSubtotalSdg = $subtotalAed * $rateSdg;
        $subtotalSdg    = $this->roundSdg($rawSubtotalSdg, $step);

        // 2. Discount in SDG
        $discountSdg = 0;
        if ($discountPercent > 0) {
            $rawDiscount = ($subtotalSdg * $discountPercent) / 100;
            $discountSdg = (int) floor($rawDiscount);
        }

        // 3. Subtotal after discount
        $afterDiscount = max(0, $subtotalSdg - $discountSdg);

        // 4. Grand Total with delivery fee, rounded to nearest step
        $rawTotal = $afterDiscount + max(0, $deliveryFeeSdg);
        $totalSdg = $this->roundSdg($rawTotal, $step);

        return [
            'subtotal_aed'     => $subtotalAed,
            'subtotal_sdg'     => $subtotalSdg,
            'discount_sdg'     => $discountSdg,
            'delivery_fee_sdg' => max(0, $deliveryFeeSdg),
            'total_sdg'        => $totalSdg,
        ];
    }
}
