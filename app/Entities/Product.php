<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;
use Config\Wex;

class Product extends Entity
{
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    protected $casts = [
        'id'             => 'integer',
        'category_id'    => 'integer',
        'brand_id'       => '?integer',
        'price_aed'      => 'integer',
        'stock_quantity' => 'integer',
        'is_active'      => 'boolean',
        'is_featured'    => 'boolean',
    ];

    public function getSpecs(): array
    {
        if (empty($this->attributes['specs'])) {
            return [];
        }
        if (is_array($this->attributes['specs'])) {
            return $this->attributes['specs'];
        }
        $decoded = json_decode($this->attributes['specs'], true);
        return is_array($decoded) ? $decoded : [];
    }

    /** Structured specs (schema keys => values); [] for legacy rows. */
    public function getSpecData(): array
    {
        $raw = $this->attributes['spec_data'] ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    public function setSpecs(mixed $specs): self
    {
        $this->attributes['specs'] = is_array($specs)
            ? json_encode($specs, JSON_UNESCAPED_UNICODE)
            : $specs;

        return $this;
    }

    /**
     * Stock status helper (does not expose exact stock count in public responses)
     */
    public function getStockStatus(int $lowStockThreshold = 2): string
    {
        $qty = (int) ($this->attributes['stock_quantity'] ?? 0);
        if ($qty <= 0) {
            return Wex::STOCK_STATUS_OUT_OF_STOCK;
        }
        if ($qty <= $lowStockThreshold) {
            return Wex::STOCK_STATUS_LOW_STOCK;
        }
        return Wex::STOCK_STATUS_IN_STOCK;
    }
}
