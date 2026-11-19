<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Exceptions\ValidationFailedException;
use App\Services\SettingService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RateAdminController
 *
 * Admin management for exchange rates (AED -> SDG) and audit history.
 */
class RateAdminController extends BaseApiController
{
    protected SettingService $settingService;

    public function __construct(?SettingService $settingService = null)
    {
        $this->settingService = $settingService ?? new SettingService();
    }

    /**
     * GET /api/v1/admin/rates
     *
     * List historical exchange rates with pagination.
     */
    public function index(): ResponseInterface
    {
        $page    = (int) ($this->request->getGet('page') ?? 1);
        $perPage = (int) ($this->request->getGet('per_page') ?? 20);

        $result = $this->settingService->getRateHistory($page, $perPage);

        return $this->respondSuccess(
            $result['items'],
            'سجل أسعار الصرف',
            ResponseInterface::HTTP_OK,
            $result['meta']
        );
    }

    /**
     * POST /api/v1/admin/rates
     *
     * Update current exchange rate.
     */
    public function update(): ResponseInterface
    {
        $raw = $this->request->getJSON(true) ?? $this->request->getPost();

        if (!isset($raw['rate_sdg']) || !is_numeric($raw['rate_sdg'])) {
            throw new ValidationFailedException('سعر الصرف مطلوب ويجب أن يكون رقماً صحيحاً.', [
                'rate_sdg' => 'حقل سعر الصرف مطلوب.',
            ]);
        }

        $rateSdg = (int) $raw['rate_sdg'];
        $user    = $this->currentUser();
        $ip      = $this->request->getIPAddress();

        $updated = $this->settingService->updateExchangeRate($rateSdg, $user?->id, $ip);

        return $this->respondSuccess($updated, 'تم تحديث سعر الصرف بنجاح');
    }
}
