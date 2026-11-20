<?php

namespace App\Presenters;

use App\Entities\Product;
use App\Services\PricingService;

/**
 * AdminProductPresenter
 *
 * Formats products for admin responses, including AED prices, conversion rates,
 * raw stock quantities, and active statuses.
 */
class AdminProductPresenter
{
    /**
     * Formats a single product for admin responses.
     */
    public static function present(array|Product $data, ?int $rateSdg = null): array
    {
        $arr = is_array($data) ? $data : $data->toArray();
        $pricing = new PricingService();
        $rate = $rateSdg ?? $pricing->getCurrentRateSdg();

        $priceAed = (int) ($arr['price_aed'] ?? 0);
        $priceSdg = isset($arr['price_sdg'])
            ? (int) $arr['price_sdg']
            : $pricing->convertAedToSdg($priceAed, $rate);

        return [
            'id'                => (int) ($arr['id'] ?? 0),
            'type'              => (string) ($arr['type'] ?? ''),
            'category_id'       => isset($arr['category_id']) ? (int) $arr['category_id'] : null,
            'brand_id'          => isset($arr['brand_id']) ? (int) $arr['brand_id'] : null,
            'name'              => (string) ($arr['name'] ?? ''),
            'slug'              => (string) ($arr['slug'] ?? ''),
            'short_description' => $arr['short_description'] ?? null,
            'description'       => $arr['description'] ?? null,
            'condition'         => (string) ($arr['condition'] ?? ''),
            'price_aed'         => $priceAed,
            'price_sdg'         => $priceSdg,
            'rate_sdg'          => $rate,
            'specs'             => is_string($arr['specs'] ?? null)
                ? json_decode($arr['specs'], true)
                : ($arr['specs'] ?? []),
            'warranty_text'     => $arr['warranty_text'] ?? null,
            'stock_quantity'    => (int) ($arr['stock_quantity'] ?? 0),
            'is_active'         => (bool) ($arr['is_active'] ?? false),
            'is_featured'       => (bool) ($arr['is_featured'] ?? false),
            'created_at'        => isset($arr['created_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime($arr['created_at'])) : null,
            'updated_at'        => isset($arr['updated_at']) ? gmdate('Y-m-d\TH:i:s\Z', strtotime($arr['updated_at'])) : null,
        ];
    }

    /**
     * Formats a collection of products for admin list view.
     */
    public static function presentCollection(array $items, ?int $rateSdg = null): array
    {
        $pricing = new PricingService();
        $rate = $rateSdg ?? $pricing->getCurrentRateSdg();

        return array_map(static fn($item) => self::present($item, $rate), $items);
    }
}
