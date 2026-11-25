<?php

namespace App\Controllers\Api\V1;

use App\Exceptions\NotFoundException;
use App\Exceptions\ValidationFailedException;
use App\Services\PushService;
use CodeIgniter\HTTP\ResponseInterface;
use Config\PushTemplates;

class PushController extends BaseApiController
{
    protected PushService $pushService;

    public function __construct(?PushService $pushService = null)
    {
        $this->pushService = $pushService ?? new PushService();
    }

    /**
     * GET /api/v1/push/config
     */
    public function config(): ResponseInterface
    {
        return $this->respondSuccess($this->pushService->getPublicConfig(), 'إعدادات الإشعارات');
    }

    /**
     * GET /api/v1/push/subscriptions
     */
    public function listSubscriptions(): ResponseInterface
    {
        $user = $this->currentUser();
        $items = $this->pushService->listUserSubscriptions((int) $user->id);
        return $this->respondSuccess($items, 'الأجهزة المسجلة');
    }

    /**
     * POST /api/v1/push/subscriptions
     */
    public function subscribe(): ResponseInterface
    {
        $user = $this->currentUser();
        $body = $this->getJsonBody();

        $platform = strtolower(trim((string) ($body['platform'] ?? 'web')));
        $provider = strtolower(trim((string) ($body['provider'] ?? ($platform === 'web' ? 'webpush' : 'fcm'))));

        $errors = [];
        if (!in_array($platform, ['web', 'android', 'ios'], true)) {
            $errors['platform'] = 'المنصة غير صالحة.';
        }
        if (!in_array($provider, ['webpush', 'fcm'], true)) {
            $errors['provider'] = 'مزود الإشعارات غير صالح.';
        }

        $endpointOrToken = '';
        $keys = [];

        if ($provider === 'webpush') {
            $sub = $body['subscription'] ?? $body;
            $endpointOrToken = trim((string) ($sub['endpoint'] ?? ($body['endpoint_or_token'] ?? '')));
            $keys = is_array($sub['keys'] ?? null) ? $sub['keys'] : [];
            if ($endpointOrToken === '' || !filter_var($endpointOrToken, FILTER_VALIDATE_URL)
                || !\App\Libraries\PushEndpointPolicy::isAllowed($endpointOrToken)) {
                $errors['subscription'] = 'بيانات اشتراك المتصفح غير صالحة.';
            }
            if (empty($keys['p256dh']) || empty($keys['auth'])) {
                $errors['keys'] = 'مفاتيح التشفير مطلوبة لاشتراك المتصفح.';
            }
        } else {
            $endpointOrToken = trim((string) ($body['token'] ?? ($body['endpoint_or_token'] ?? '')));
            if ($endpointOrToken === '' || strlen($endpointOrToken) < 10) {
                $errors['token'] = 'رمز الجهاز (FCM token) غير صالح.';
            }
        }

        if (!empty($errors)) {
            throw new ValidationFailedException($errors);
        }

        $ua = $this->request->getUserAgent()->getAgentString();

        $saved = $this->pushService->registerSubscription((int) $user->id, [
            'platform'          => $platform,
            'provider'          => $provider,
            'endpoint_or_token' => $endpointOrToken,
            'keys'              => $keys,
            'device_label'      => $body['device_label'] ?? null,
            'user_agent'        => $ua,
            'locale'            => $body['locale'] ?? 'ar',
            'app_version'       => $body['app_version'] ?? null,
            'topics'            => is_array($body['topics'] ?? null) ? $body['topics'] : null,
        ]);

        return $this->respondSuccess([
            'id'           => $saved->id,
            'platform'     => $saved->platform,
            'provider'     => $saved->provider,
            'device_label' => $saved->device_label,
            'is_active'    => $saved->is_active,
        ], 'تم تفعيل الإشعارات لهذا الجهاز', ResponseInterface::HTTP_CREATED);
    }

    /**
     * DELETE /api/v1/push/subscriptions
     */
    public function unsubscribe(): ResponseInterface
    {
        $user = $this->currentUser();
        $body = $this->getJsonBody();
        $endpointOrToken = trim((string) (
            $body['endpoint_or_token']
            ?? $body['endpoint']
            ?? $body['token']
            ?? ($body['subscription']['endpoint'] ?? '')
        ));

        if ($endpointOrToken === '') {
            throw new ValidationFailedException(['endpoint_or_token' => 'معرف الاشتراك مطلوب.']);
        }

        $deleted = $this->pushService->unregisterSubscription($endpointOrToken, (int) $user->id);

        return $this->respondSuccess(['deleted' => $deleted], 'تم إلغاء الاشتراك من هذا الجهاز');
    }

    /**
     * DELETE /api/v1/push/subscriptions/{id}
     */
    public function deleteSubscription(int $id): ResponseInterface
    {
        $user = $this->currentUser();
        $deleted = $this->pushService->deleteUserSubscriptionById((int) $user->id, $id);
        if (!$deleted) {
            throw new NotFoundException('الجهاز غير موجود.');
        }

        return $this->respondSuccess(['deleted' => true], 'تم إزالة الجهاز');
    }

    /**
     * GET /api/v1/push/preferences
     */
    public function getPreferences(): ResponseInterface
    {
        $user = $this->currentUser();
        return $this->respondSuccess($this->pushService->getPreferences((int) $user->id), 'تفضيلات الإشعارات');
    }

    /**
     * PUT /api/v1/push/preferences
     */
    public function updatePreferences(): ResponseInterface
    {
        $user = $this->currentUser();
        $body = $this->getJsonBody();
        $updated = $this->pushService->updatePreferences((int) $user->id, $body);
        return $this->respondSuccess($updated, 'تم حفظ تفضيلات الإشعارات');
    }

    /**
     * POST /api/v1/push/test
     */
    public function sendTest(): ResponseInterface
    {
        $user = $this->currentUser();
        $tpl = PushTemplates::render('test');

        $notifId = $this->pushService->sendToUser((int) $user->id, [
            'type'  => 'test',
            'title' => $tpl['title'],
            'body'  => $tpl['body'],
            'url'   => '/notifications',
            'tag'   => 'wex-test-' . time(),
        ]);

        return $this->respondSuccess(['notification_id' => $notifId], 'تم إرسال الإشعار التجريبي');
    }
}
