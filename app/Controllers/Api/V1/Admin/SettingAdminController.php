<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Services\SettingService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * SettingAdminController
 *
 * Admin management for store settings. Accessible by admin only.
 */
class SettingAdminController extends BaseApiController
{
    protected SettingService $settingService;

    public function __construct(?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    /**
     * GET /api/v1/admin/settings
     */
    public function index(): ResponseInterface
    {
        $settings = $this->settingService->getAllSettings();
        return $this->respondSuccess($settings, 'إعدادات النظام');
    }

    /**
     * PATCH /api/v1/admin/settings
     */
    public function update(): ResponseInterface
    {
        $raw = $this->request->getJSON(true) ?? $this->request->getRawInput();
        if (!is_array($raw)) {
            $raw = [];
        }
        $user = $this->currentUser();
        $ip = $this->request->getIPAddress();

        $updated = $this->settingService->updateSettings($raw, $user?->id, $ip);

        return $this->respondSuccess($updated, 'تم تحديث الإعدادات بنجاح');
    }
}
