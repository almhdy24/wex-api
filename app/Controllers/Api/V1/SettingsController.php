<?php

namespace App\Controllers\Api\V1;

use App\Models\SettingModel;
use App\Services\PricingService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SettingsController
 *
 * Public shop profile and store operational details.
 * Exchange rates and bank details are omitted for customers.
 */
class SettingsController extends BaseApiController
{
    protected SettingModel $settingModel;
    protected PricingService $pricingService;

    public function __construct(
        ?SettingModel $settingModel = null,
        ?PricingService $pricingService = null
    ) {
        $this->settingModel   = $settingModel ?? new SettingModel();
        $this->pricingService = $pricingService ?? new PricingService();
    }

    /**
     * GET /api/v1/shop
     *
     * Returns public shop metadata, price lock duration, app discount rate,
     * and prices_updated_at. Exchange rate and bank details are strictly omitted.
     */
    public function shop(): ResponseInterface
    {
        $allSettings = $this->settingModel->findAll();
        $map = [];
        foreach ($allSettings as $s) {
            $map[$s->key] = $s->value;
        }

        $pricesUpdatedAt = $this->pricingService->getPricesUpdatedAt();

        $data = [
            'name'                 => $map['shop_name'] ?? 'Wex Technology (ويكس تكنولوجي)',
            'address'              => $map['shop_address'] ?? 'مول الواحة، الشهداء، أم درمان',
            'phone'                => $map['shop_phone'] ?? '+249 9XX XXX XXX',
            'whatsapp'             => $map['shop_whatsapp'] ?? '249912345678',
            'working_hours'        => $map['working_hours'] ?? 'من 10 صباحاً إلى 10 مساءً، عدا الجمعة',
            'lock_hours'           => isset($map['lock_hours']) ? (int) $map['lock_hours'] : 4,
            'app_discount_percent' => isset($map['app_discount_percent']) ? (int) $map['app_discount_percent'] : 3,
            'prices_updated_at'    => $pricesUpdatedAt,
        ];

        return $this->respondSuccess($data, 'معلومات المتجر');
    }
}
