<?php

namespace App\Controllers\Api\V1;

use App\Services\CronRunner;
use App\Services\Installer;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Internal endpoints for shared hosting without SSH/crontab. Both are 404 unless their
 * secret is configured, so they are invisible on installs that do not use them.
 */
class InternalController extends BaseApiController
{
    /**
     * POST /api/v1/internal/cron   header: X-Cron-Token: <CRON_TOKEN>
     * GET  /api/v1/internal/cron?token=...   only when CRON_ALLOW_QUERY_TOKEN=true
     *      (Plesk "Fetch a URL" cannot send headers; the token then appears in access logs).
     */
    public function cron(): ResponseInterface
    {
        if (strlen((string) env('CRON_TOKEN', '')) < 32) {
            return $this->respondError(lang('Api.route_not_found'), 404);
        }

        $presented = $this->request->getHeaderLine('X-Cron-Token');
        if ($presented === '' && strtoupper($this->request->getMethod()) === 'GET'
            && filter_var(env('CRON_ALLOW_QUERY_TOKEN', false), FILTER_VALIDATE_BOOLEAN)) {
            $presented = (string) $this->request->getGet('token');
        }

        if (!CronRunner::tokenIsValid($presented)) {
            return $this->respondError('غير مصرّح بتنفيذ المهام المجدولة.', 403);
        }

        $result = (new CronRunner())->run();

        return $this->respondSuccess($result, $result['status'] === 'busy' ? 'تنفيذ سابق ما زال قيد التشغيل.' : 'تم تنفيذ المهام المجدولة.', $result['status'] === 'busy' ? 409 : 200);
    }

    /**
     * POST /api/v1/internal/install   header: X-Install-Token: <INSTALL_TOKEN>
     * body (first run only): {"admin_email": "...", "admin_name": "..."}
     */
    public function install(): ResponseInterface
    {
        $installer = new Installer();
        if (!$installer->isEnabled()) {
            return $this->respondError(lang('Api.route_not_found'), 404);
        }
        // Token first: callers without the secret learn nothing (not even whether it is installed).
        if (!$installer->tokenIsValid($this->request->getHeaderLine('X-Install-Token'))) {
            return $this->respondError('رمز التثبيت غير صحيح.', 403);
        }
        if ($installer->isLocked()) {
            return $this->respondError('تم التثبيت مسبقاً. احذف INSTALL_TOKEN من ملف .env.', 410);
        }

        $body = $this->getJsonBody();
        $result = $installer->install(
            (string) ($body['admin_email'] ?? ''),
            (string) ($body['admin_name'] ?? 'مدير المتجر'),
            \App\Libraries\Input::bool($body['sample_content'] ?? false)
        );

        return $this->respondSuccess($result, 'تم التثبيت بنجاح. احذف INSTALL_TOKEN من ملف .env الآن.');
    }

    /**
     * POST /api/v1/internal/upgrade   header: X-Cron-Token: <CRON_TOKEN>
     * After uploading an upgrade zip: runs pending migrations (idempotent: nothing to do
     * returns the same success). Header only; never accepts the token in the query string.
     */
    public function upgrade(): ResponseInterface
    {
        if (strlen((string) env('CRON_TOKEN', '')) < 32) {
            return $this->respondError(lang('Api.route_not_found'), 404);
        }
        if (!CronRunner::tokenIsValid($this->request->getHeaderLine('X-Cron-Token'))) {
            return $this->respondError('غير مصرّح.', 403);
        }

        $runner = service('migrations')->setNamespace(null);
        $before = count($runner->findMigrations());
        $runner->latest();
        log_message('notice', 'Upgrade: migrations brought to latest.');

        return $this->respondSuccess(['migrated' => true, 'known_migrations' => $before], 'تم تحديث قاعدة البيانات.');
    }
}
