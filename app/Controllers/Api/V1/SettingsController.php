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
            'lock_hours'              => isset($map['lock_hours']) ? (int) $map['lock_hours'] : 4,
            'app_discount_percent'    => isset($map['app_discount_percent']) ? (int) $map['app_discount_percent'] : 3,
            'prices_updated_at'       => $pricesUpdatedAt,
            'delivery_notes'          => $map['delivery_notes'] ?? 'التوصيل متاح داخل ولاية الخرطوم والولايات الآمنة مع إمكانية الفحص والمعاينة عند الاستلام.',
            'policy_inspection_title' => $map['policy_inspection_title'] ?? 'المعاينة وفحص الأجهزة',
            'policy_inspection'       => $map['policy_inspection'] ?? 'أجهزة مستوردة؛ يفحص العميل الجهاز ويعاينه بنفسه بدقة عند الاستلام في المتجر للتأكد من الحالة وسلامة كافة المكونات قبل إتمام الشراء.',
            'policy_pickup'           => $map['policy_pickup'] ?? 'يمكنك تشغيل الجهاز وفحصه بنفسك بمقرنا في مول الواحة، الشهداء، أم درمان قبل سداد أي مبلغ.',
            'policy_return'           => $map['policy_return'] ?? 'نظراً لطبيعة الأجهزة المستوردة وعدم وجود وكيل محلي، لا يتوفر ضمان مصنعي أو استبدال بعد الاستلام والفحص المباشر.',
        ];

        return $this->respondSuccess($data, 'معلومات المتجر');
    }
}
