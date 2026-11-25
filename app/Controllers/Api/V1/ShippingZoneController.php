<?php

namespace App\Controllers\Api\V1;

use App\Models\ShippingZoneModel;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ShippingZoneController
 *
 * Public endpoints for shipping zones and courier delivery fees.
 */
class ShippingZoneController extends BaseApiController
{
    protected ShippingZoneModel $zoneModel;

    public function __construct(?ShippingZoneModel $zoneModel = null)
    {
        $this->zoneModel = $zoneModel ?? new ShippingZoneModel();
    }

    /**
     * GET /api/v1/shipping-zones
     */
    public function index(): ResponseInterface
    {
        $zones = $this->zoneModel
            ->where('is_active', 1)
            ->orderBy('fee_sdg', 'ASC')
            ->findAll();

        $data = array_map(static fn($z) => [
            'id'               => (int) $z->id,
            'name'             => (string) $z->state_name_ar,
            'zone_name'        => (string) $z->state_name_ar,
            'state_name_ar'    => (string) $z->state_name_ar,
            'fee_sdg'          => (int) $z->fee_sdg,
            'delivery_fee_sdg' => (int) $z->fee_sdg,
            'is_active'        => (bool) ($z->is_active ?? 1),
        ], $zones);

        return $this->respondSuccess($data, 'مناطق التوصيل ورسومها');
    }
}
