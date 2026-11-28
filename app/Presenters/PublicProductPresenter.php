<?php

namespace App\Presenters;

use App\Entities\Product;

/**
 * PublicProductPresenter
 *
 * Whitelists customer-facing and public product fields.
 * NEVER leaks price_aed, exchange rates, or reference currency terms.
 */
class PublicProductPresenter
{
    /**
     * Formats a product list item for public catalog response.
     */
    public static function presentListItem(array|Product $data): array
    {
        $arr = is_array($data) ? $data : $data->toArray();

        return [
            'id'                => (int) ($arr['id'] ?? 0),
            'slug'              => (string) ($arr['slug'] ?? ''),
            'type'              => (string) ($arr['type'] ?? ''),
            'name'              => (string) ($arr['name'] ?? ''),
            'short_description' => $arr['short_description'] ?? null,
            'condition'         => (string) ($arr['condition'] ?? ''),
            'price_sdg'         => (int) ($arr['price_sdg'] ?? 0),
            'stock_status'      => (string) ($arr['stock_status'] ?? 'in_stock'),
            'is_featured'       => (bool) ($arr['is_featured'] ?? false),
            'specs'             => is_string($arr['specs'] ?? null)
                ? (json_decode($arr['specs'], true) ?: [])
                : (is_array($arr['specs'] ?? null) ? $arr['specs'] : []),
            'primary_image'     => isset($arr['image']) && is_array($arr['image']) ? ($arr['image']['path'] ?? null) : null,
            'thumb_url'         => isset($arr['image']) && is_array($arr['image']) ? ($arr['image']['thumb_path'] ?? null) : null,
            'image'             => isset($arr['image']) && is_array($arr['image']) ? [
                'path'       => (string) ($arr['image']['path'] ?? ''),
                'thumb_path' => $arr['image']['thumb_path'] ?? null,
            ] : null,
        ];
    }

    /**
     * Formats a collection of product list items.
     */
    public static function presentCollection(array $items): array
    {
        return array_map(static fn($item) => self::presentListItem($item), $items);
    }

    /**
     * Formats a full product detail view for public responses.
     */
    public static function presentDetail(array|Product $data): array
    {
        $arr = is_array($data) ? $data : $data->toArray();

        return [
            'id'                => (int) ($arr['id'] ?? 0),
            'slug'              => (string) ($arr['slug'] ?? ''),
            'type'              => (string) ($arr['type'] ?? ''),
            'name'              => (string) ($arr['name'] ?? ''),
            'short_description' => $arr['short_description'] ?? null,
            'description'       => $arr['description'] ?? null,
            'condition'         => (string) ($arr['condition'] ?? ''),
            'price_sdg'         => (int) ($arr['price_sdg'] ?? 0),
            'stock_status'      => (string) ($arr['stock_status'] ?? 'in_stock'),
            'warranty_text'     => $arr['warranty_text'] ?? null,
            'specs'             => is_string($arr['specs'] ?? null)
                ? json_decode($arr['specs'], true)
                : ($arr['specs'] ?? []),
            'is_featured'       => (bool) ($arr['is_featured'] ?? false),
            'category'          => isset($arr['category']) && is_array($arr['category']) ? [
                'id'      => (int) $arr['category']['id'],
                'name_ar' => $arr['category']['name_ar'] ?? '',
                'name_en' => $arr['category']['name_en'] ?? '',
                'slug'    => $arr['category']['slug'] ?? '',
            ] : null,
            'brand'             => isset($arr['brand']) && is_array($arr['brand']) ? [
                'id'   => (int) $arr['brand']['id'],
                'name' => $arr['brand']['name'] ?? '',
                'slug' => $arr['brand']['slug'] ?? '',
            ] : null,
            'images'            => array_map(static function ($img) {
                return [
                    'id'         => (int) ($img['id'] ?? 0),
                    'path'       => (string) ($img['path'] ?? ''),
                    'thumb_path' => $img['thumb_path'] ?? null,
                    'is_primary' => (bool) ($img['is_primary'] ?? false),
                    'sort_order' => (int) ($img['sort_order'] ?? 0),
                ];
            }, $arr['images'] ?? []),
            'accessories'       => array_map(static function ($acc) {
                return [
                    'id'                => (int) ($acc['id'] ?? 0),
                    'slug'              => (string) ($acc['slug'] ?? ''),
                    'type'              => (string) ($acc['type'] ?? ''),
                    'name'              => (string) ($acc['name'] ?? ''),
                    'short_description' => $acc['short_description'] ?? null,
                    'condition'         => $acc['condition'] ?? null,
                    'price_sdg'         => (int) ($acc['price_sdg'] ?? 0),
                    'stock_status'      => (string) ($acc['stock_status'] ?? 'in_stock'),
                    'image'             => isset($acc['image']) && is_array($acc['image']) ? [
                        'path'       => (string) ($acc['image']['path'] ?? ''),
                        'thumb_path' => $acc['image']['thumb_path'] ?? null,
                    ] : null,
                ];
            }, $arr['accessories'] ?? []),
        ];
    }
}
