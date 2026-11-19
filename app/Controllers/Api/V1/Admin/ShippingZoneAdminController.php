<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Entities\ShippingZone;
use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Models\ShippingZoneModel;
use App\Services\AuditLogService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * ShippingZoneAdminController
 *
 * Admin management for delivery zones (Sudan states) and delivery fees in SDG.
 */
class ShippingZoneAdminController extends BaseApiController
{
    protected ShippingZoneModel $shippingZones;
    protected AuditLogService $auditLogs;

    public function __construct(
        ?ShippingZoneModel $shippingZones = null,
        ?AuditLogService $auditLogs = null
    ) {
        $this->shippingZones = $shippingZones ?? new ShippingZoneModel();
        $this->auditLogs     = $auditLogs ?? new AuditLogService();
    }

    /**
     * GET /api/v1/admin/shipping-zones
     *
     * List all shipping zones with active/inactive status.
     */
    public function index(): ResponseInterface
    {
        $zones = $this->shippingZones
            ->orderBy('id', 'ASC')
            ->findAll();

        $data = array_map(function (ShippingZone $zone) {
            return [
                'id'            => $zone->id,
                'state_name_ar' => $zone->state_name_ar,
                'fee_sdg'       => (int) $zone->fee_sdg,
                'is_active'     => (bool) $zone->is_active,
                'created_at'    => $zone->created_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($zone->created_at)) : null,
                'updated_at'    => $zone->updated_at ? gmdate('Y-m-d\TH:i:s\Z', strtotime($zone->updated_at)) : null,
            ];
        }, $zones);

        return $this->respondSuccess($data, 'قائمة مناطق التوصيل');
    }

    /**
     * POST /api/v1/admin/shipping-zones
     *
     * Create a new shipping zone.
     */
    public function create(): ResponseInterface
    {
        $input = $this->request->getJSON(true) ?? $this->request->getPost();

        $rules = [
            'state_name_ar' => 'required|min_length[3]|max_length[100]|is_unique[shipping_zones.state_name_ar]',
            'fee_sdg'       => 'required|is_natural',
        ];

        if (!$this->validateData($input, $rules)) {
            throw new ValidationFailedException('بيانات منطقة التوصيل غير صالحة أو الولاية مسجلة مسبقاً.', $this->validator->getErrors());
        }

        $zone = new ShippingZone([
            'state_name_ar' => trim($input['state_name_ar']),
            'fee_sdg'       => (int) $input['fee_sdg'],
            'is_active'     => isset($input['is_active']) ? (bool) $input['is_active'] : true,
        ]);

        $id = $this->shippingZones->insert($zone);
        $saved = $this->shippingZones->find($id);

        $user = $this->currentUser();
        $this->auditLogs->log(
            $user?->id,
            'create_shipping_zone',
            'shipping_zones',
            $id,
            ['state_name_ar' => $saved->state_name_ar, 'fee_sdg' => $saved->fee_sdg],
            $this->request->getIPAddress()
        );

        return $this->respondSuccess([
            'id'            => $saved->id,
            'state_name_ar' => $saved->state_name_ar,
            'fee_sdg'       => (int) $saved->fee_sdg,
            'is_active'     => (bool) $saved->is_active,
        ], 'تمت إضافة منطقة التوصيل بنجاح', ResponseInterface::HTTP_CREATED);
    }

    /**
     * PATCH /api/v1/admin/shipping-zones/{id}
     *
     * Update an existing shipping zone.
     */
    public function update(int $id): ResponseInterface
    {
        $zone = $this->shippingZones->find($id);
        if (!$zone) {
            throw new NotFoundException('منطقة التوصيل غير موجودة.');
        }

        $input = $this->request->getJSON(true) ?? $this->request->getRawInput();

        $rules = [];
        if (isset($input['state_name_ar'])) {
            $rules['state_name_ar'] = 'min_length[3]|max_length[100]';
        }
        if (isset($input['fee_sdg'])) {
            $rules['fee_sdg'] = 'is_natural';
        }

        if (!empty($rules) && !$this->validateData($input, $rules)) {
            throw new ValidationFailedException('بيانات منطقة التوصيل غير صالحة.', $this->validator->getErrors());
        }

        if (isset($input['state_name_ar'])) {
            $zone->state_name_ar = trim($input['state_name_ar']);
        }
        if (isset($input['fee_sdg'])) {
            $zone->fee_sdg = (int) $input['fee_sdg'];
        }
        if (isset($input['is_active'])) {
            $zone->is_active = (bool) $input['is_active'];
        }

        $this->shippingZones->save($zone);
        $saved = $this->shippingZones->find($id);

        $user = $this->currentUser();
        $this->auditLogs->log(
            $user?->id,
            'update_shipping_zone',
            'shipping_zones',
            $id,
            ['state_name_ar' => $saved->state_name_ar, 'fee_sdg' => $saved->fee_sdg, 'is_active' => $saved->is_active],
            $this->request->getIPAddress()
        );

        return $this->respondSuccess([
            'id'            => $saved->id,
            'state_name_ar' => $saved->state_name_ar,
            'fee_sdg'       => (int) $saved->fee_sdg,
            'is_active'     => (bool) $saved->is_active,
        ], 'تم تحديث منطقة التوصيل بنجاح');
    }

    /**
     * DELETE /api/v1/admin/shipping-zones/{id}
     *
     * Delete a shipping zone.
     */
    public function delete(int $id): ResponseInterface
    {
        $zone = $this->shippingZones->find($id);
        if (!$zone) {
            throw new NotFoundException('منطقة التوصيل غير موجودة.');
        }

        $this->shippingZones->delete($id);

        $user = $this->currentUser();
        $this->auditLogs->log(
            $user?->id,
            'delete_shipping_zone',
            'shipping_zones',
            $id,
            ['state_name_ar' => $zone->state_name_ar],
            $this->request->getIPAddress()
        );

        return $this->respondSuccess(null, 'تم حذف منطقة التوصيل بنجاح');
    }
}
