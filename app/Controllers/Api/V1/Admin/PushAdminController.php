<?php

namespace App\Controllers\Api\V1\Admin;

use App\Controllers\Api\V1\BaseApiController;
use App\Exceptions\ValidationFailedException;
use App\Services\PushService;
use App\Services\SettingService;
use CodeIgniter\HTTP\ResponseInterface;

class PushAdminController extends BaseApiController
{
    protected PushService $pushService;
    protected SettingService $settingService;

    public function __construct(?PushService $pushService = null, ?SettingService $settingService = null)
    {
        $this->pushService    = $pushService ?? new PushService();
        $this->settingService = $settingService ?? new SettingService();
    }

    /**
     * POST /api/v1/admin/push/broadcast
     */
    public function broadcast(): ResponseInterface
    {
        $body = $this->getJsonBody();
        $title = trim((string) ($body['title'] ?? ''));
        $msg   = trim((string) ($body['body'] ?? ''));
        $link  = trim((string) ($body['link'] ?? '/notifications'));
        $segment = trim((string) ($body['segment'] ?? 'customers'));
        $scheduledAt = !empty($body['scheduled_at']) ? (string) $body['scheduled_at'] : null;

        $errors = [];
        if ($title === '' || mb_strlen($title) > 120) {
            $errors['title'] = 'عنوان الإشعار مطلوب (بحد أقصى 120 حرفاً).';
        }
        if ($msg === '' || mb_strlen($msg) > 500) {
            $errors['body'] = 'نص الإشعار مطلوب (بحد أقصى 500 حرف).';
        }
        if (!in_array($segment, ['all', 'customers', 'with-open-orders'], true)) {
            $errors['segment'] = 'الفئة المستهدفة غير صالحة.';
        }
        if ($link !== '' && (!str_starts_with($link, '/') || str_starts_with($link, '//'))) {
            $errors['link'] = 'الرابط يجب أن يكون مساراً نسبياً داخلياً يبدأ بـ /.';
        }
        if ($scheduledAt !== null && strtotime($scheduledAt) === false) {
            $errors['scheduled_at'] = 'تاريخ الجدولة غير صالح.';
        }

        if (!empty($errors)) {
            throw new ValidationFailedException($errors);
        }

        $result = $this->pushService->broadcast($segment, [
            'type'         => 'announcement',
            'title'        => $title,
            'body'         => $msg,
            'url'          => $link !== '' ? $link : '/notifications',
            'scheduled_at' => $scheduledAt,
        ]);

        return $this->respondSuccess($result, 'تم إرسال الإشعار الجماعي بنجاح');
    }

    /**
     * GET /api/v1/admin/push/stats
     */
    public function stats(): ResponseInterface
    {
        return $this->respondSuccess($this->pushService->getStats(), 'إحصائيات الإشعارات');
    }

    /**
     * GET /api/v1/admin/push/log
     */
    public function log(): ResponseInterface
    {
        [$page, $perPage] = $this->getPaginationParams();
        $result = $this->pushService->getLogs($page, $perPage);
        return $this->respondSuccess($result['items'], 'سجل الإشعارات', ResponseInterface::HTTP_OK, $result['meta']);
    }

    /**
     * PUT /api/v1/admin/push/settings
     */
    public function updateSettings(): ResponseInterface
    {
        $body = $this->getJsonBody();
        if (array_key_exists('push_enabled', $body)) {
            $val = filter_var($body['push_enabled'], FILTER_VALIDATE_BOOLEAN) ? '1' : '0';
            $this->settingService->set('push_enabled', $val);
        }

        return $this->respondSuccess([
            'push_enabled' => $this->pushService->isGlobalEnabled(),
        ], 'تم تحديث إعدادات الإشعارات');
    }

    /**
     * GET /api/v1/admin/push/fake-log (non-production testing helper)
     */
    public function fakeLog(): ResponseInterface
    {
        $env = strtolower((string) (getenv('APP_ENV') ?: (defined('ENVIRONMENT') ? ENVIRONMENT : 'development')));
        if ($env === 'production') {
            return $this->respondError('غير متاح', 404);
        }

        $path = WRITEPATH . 'logs/fake_push.jsonl';
        if ($this->request->getMethod() === 'DELETE') {
            @unlink($path);
            return $this->respondSuccess(['cleared' => true], 'تم مسح السجل التجريبي');
        }

        $items = [];
        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
            foreach ($lines as $line) {
                $decoded = json_decode($line, true);
                if (is_array($decoded)) {
                    $items[] = $decoded;
                }
            }
        }

        return $this->respondSuccess($items, 'سجل الإشعارات الوهمية');
    }
}
