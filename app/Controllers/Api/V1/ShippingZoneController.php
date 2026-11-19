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
            'id'            => (int) $z->id,
            'state_name_ar' => $z->state_name_ar,
            'fee_sdg'       => (int) $z->fee_sdg,
        ], $zones);

        return $this->respondSuccess($data, 'مناطق التوصيل ورسوم الشحن');
    }
}
