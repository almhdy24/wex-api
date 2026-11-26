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
            'name'                    => $map['shop_name'] ?? 'Wex Technology (ويكس تكنولوجي)',
            'address'                 => $map['shop_address'] ?? 'مول الواحة، الشهداء، أم درمان',
            'phone'                   => $map['shop_phone'] ?? '',
            'whatsapp'                => $map['shop_whatsapp'] ?? '',
            'working_hours'           => $map['working_hours'] ?? 'من 10 صباحاً إلى 10 مساءً، عدا الجمعة',
            // Admin-editable but never sent: the storefront fell back to hard-coded values.
            'shop_map_link'           => $map['shop_map_link'] ?? '',
            'footer_text'             => $map['footer_text'] ?? '',
            'social_facebook'         => $map['social_facebook'] ?? '',
            'social_instagram'        => $map['social_instagram'] ?? '',
            'lock_hours'              => isset($map['lock_hours']) ? (int) $map['lock_hours'] : 4,
            'app_discount_percent'    => isset($map['app_discount_percent']) ? (int) $map['app_discount_percent'] : 3,
            'prices_updated_at'       => $pricesUpdatedAt,
            'orders_enabled'          => \App\Services\SettingService::isOn($map['orders_enabled'] ?? null, true),
            'orders_disabled_message' => $map['orders_disabled_message'] ?? 'استقبال الطلبات متوقف مؤقتاً في الوقت الحالي، نعتذر عن الإزعاج.',
            'ticker_enabled'          => \App\Services\SettingService::isOn($map['ticker_enabled'] ?? null, false),
            'delivery_notes'          => $map['delivery_notes'] ?? ($map['extra_delivery_note'] ?? 'التوصيل متاح داخل ولاية الخرطوم والولايات المحددة.'),
            'extra_delivery_note'     => $map['extra_delivery_note'] ?? ($map['delivery_notes'] ?? null),
            'policy_inspection_title' => $map['policy_inspection_title'] ?? 'استلام الأجهزة',
            'policy_inspection'       => $map['policy_inspection'] ?? ($map['inspection_note'] ?? 'أجهزة مستوردة؛ يتأكد المشتري من مطابقة الجهاز ومواصفاته الفنية عند الاستلام.'),
            'inspection_note'         => $map['inspection_note'] ?? ($map['policy_inspection'] ?? null),
            'policy_pickup'           => $map['policy_pickup'] ?? 'يمكنك استلام الجهاز من مقرنا في مول الواحة، الشهداء، أم درمان.',
            'policy_return'           => $map['policy_return'] ?? ($map['returns_note'] ?? 'نظراً لطبيعة الأجهزة المستوردة، يتم الاستلام والتأكد من الجهاز عند الاستلام.'),
            'returns_note'            => $map['returns_note'] ?? ($map['policy_return'] ?? null),
        ];

        return $this->respondSuccess($data, 'معلومات المتجر');
    }
}
