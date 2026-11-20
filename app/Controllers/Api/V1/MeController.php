<?php

namespace App\Controllers\Api\V1;

use App\Exceptions\ValidationFailedException;
use App\Libraries\PhoneValidator;
use App\Models\UserModel;
use App\Services\AuthService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * MeController
 *
 * Current authenticated user profile, preferences, and account management.
 */
class MeController extends BaseApiController
{
    protected UserModel $userModel;
    protected AuthService $authService;

    public function __construct(?UserModel $userModel = null, ?AuthService $authService = null)
    {
        $this->userModel   = $userModel ?? new UserModel();
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * GET /api/v1/me
     */
    public function show(): ResponseInterface
    {
        $user = $this->getCurrentUser();

        return $this->respondSuccess(
            $this->authService->formatUserData($user),
            'بيانات المستخدم'
        );
    }

    /**
     * PATCH /api/v1/me
     */
    public function update(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $body = $this->getJsonBody();

        $updateData = [];

        if (isset($body['name'])) {
            $name = trim((string) $body['name']);
            if (mb_strlen($name) < 2) {
                throw new ValidationFailedException(['name' => 'الاسم يجب أن يتكون من حرفين على الأقل']);
            }
            $updateData['name'] = $name;
        }

        if (array_key_exists('phone', $body)) {
            $phoneInput = $body['phone'] !== null ? trim((string) $body['phone']) : null;
            if ($phoneInput !== null && $phoneInput !== '') {
                $normalized = PhoneValidator::normalize($phoneInput);
                if ($normalized === null) {
                    throw new ValidationFailedException(['phone' => lang('Api.invalid_phone')]);
                }
                $updateData['phone'] = $normalized;
            } else {
                $updateData['phone'] = null;
            }
        }

        if (!empty($updateData)) {
            $updateData['updated_at'] = gmdate('Y-m-d H:i:s');
            $this->userModel->update($user->id, $updateData);
            $user = $this->userModel->find($user->id);
        }

        return $this->respondSuccess(
            $this->authService->formatUserData($user),
            lang('Api.profile_updated')
        );
    }

    /**
     * PATCH /api/v1/me/preferences
     */
    public function updatePreferences(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $body = $this->getJsonBody();

        if (!isset($body['email_notifications'])) {
            throw new ValidationFailedException([
                'email_notifications' => 'حقل email_notifications مطلوب (قيمة منطقية)',
            ]);
        }

        $val = filter_var($body['email_notifications'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;

        $this->userModel->update($user->id, [
            'email_notifications' => $val,
            'updated_at'          => gmdate('Y-m-d H:i:s'),
        ]);

        $user = $this->userModel->find($user->id);

        return $this->respondSuccess(
            $this->authService->formatUserData($user),
            lang('Api.preferences_updated')
        );
    }

    /**
     * POST /api/v1/me/link-google
     */
    public function linkGoogle(): ResponseInterface
    {
        $user = $this->getCurrentUser();
        $body = $this->getJsonBody();

        if (empty($body['id_token'])) {
            throw new ValidationFailedException(['id_token' => 'رمز Google ID مطلوب']);
        }

        $googleService = new \App\Services\GoogleAuthService();
        $profile = $googleService->verifyIdToken((string) $body['id_token']);

        // Prevent linking if another account already uses this google_id
        $existing = $this->userModel->where('google_id', $profile['google_id'])->first();
        if ($existing && (int) $existing->id !== (int) $user->id) {
            throw new ValidationFailedException([
                'google' => 'حساب Google هذا مرتبط بحساب عميل آخر بالفعل',
            ]);
        }

        $this->userModel->update($user->id, [
            'google_id'  => $profile['google_id'],
            'avatar_url' => $profile['avatar_url'] ?? $user->avatar_url,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $freshUser = $this->userModel->find($user->id);

        return $this->respondSuccess(
            $this->authService->formatUserData($freshUser),
            'تم ربط حساب Google بنجاح'
        );
    }

    /**
     * POST /api/v1/me/unlink-google
     */
    public function unlinkGoogle(): ResponseInterface
    {
        $user = $this->getCurrentUser();

        if (empty($user->email)) {
            throw new ValidationFailedException([
                'google' => 'لا يمكن إلغاء ربط Google لعدم وجود وسيلة دخول بديلة',
            ]);
        }

        $this->userModel->update($user->id, [
            'google_id'  => null,
            'updated_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $freshUser = $this->userModel->find($user->id);

        return $this->respondSuccess(
            $this->authService->formatUserData($freshUser),
            'تم إلغاء ربط حساب Google بنجاح'
        );
    }

    /**
     * DELETE /api/v1/me
     */
    public function delete(): ResponseInterface
    {
        $user = $this->getCurrentUser();

        // 1. Revoke all refresh tokens
        $this->authService->logoutAll((int) $user->id);

        // 2. Soft delete user record
        $this->userModel->delete($user->id);

        return $this->respondSuccess(null, lang('Api.account_deleted'));
    }
}
