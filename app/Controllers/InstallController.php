<?php

namespace App\Controllers;

use App\Services\Installer;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Browser installer for hosts without SSH: https://<domain>/api/install
 *
 * - 404 unless INSTALL_TOKEN (>= 32 chars) is set in .env.
 * - Nothing (not even the environment report) is shown before the token is entered.
 *   The token travels in the POST body; it is the form's secret, so a cross-site page
 *   cannot submit it (CSRF) and the route is rate-limited (brute force).
 * - After install.lock exists it only says so; remove INSTALL_TOKEN to make it 404.
 */
class InstallController extends Controller
{
    public function index(): ResponseInterface
    {
        $installer = new Installer();
        if (!$installer->isEnabled()) {
            return $this->response->setStatusCode(404)->setBody('');
        }

        $token = (string) ($this->request->getPost('install_token') ?? '');
        $action = (string) ($this->request->getPost('action') ?? '');
        $data = ['step' => 'token', 'error' => null, 'token' => ''];

        if ($this->request->getMethod() === 'POST' || strtoupper($this->request->getMethod()) === 'POST') {
            if (!$installer->tokenIsValid($token)) {
                $data['error'] = 'رمز التثبيت غير صحيح.';
                return $this->render($data, 403);
            }
            $data['token'] = $token;

            if ($installer->isLocked()) {
                $data['step'] = 'locked';
                return $this->render($data, 410);
            }

            $data['step'] = 'check';
            if ($action === 'secrets') {
                $data['secrets_result'] = $installer->generateMissingSecrets();
            } elseif ($action === 'install') {
                try {
                    $data['result'] = $installer->install(
                        (string) $this->request->getPost('admin_email'),
                        (string) $this->request->getPost('admin_name'),
                        $this->request->getPost('sample_content') === '1'
                    );
                    $data['step'] = 'done';
                } catch (\App\Exceptions\ValidationFailedException $e) {
                    $data['error'] = 'بريد المدير مطلوب ويجب أن يكون صحيحاً.';
                } catch (\Throwable $e) {
                    log_message('error', 'Installer failed: {msg}', ['msg' => $e->getMessage()]);
                    $data['error'] = 'تعذّر إكمال التثبيت. راجع سجل الأخطاء في writable/logs.';
                }
            }

            $data['requirements'] = $installer->requirements();
            $data['database'] = $installer->databaseCheck();
            $data['secrets'] = $installer->secretsStatus();
        }

        return $this->render($data, 200);
    }

    private function render(array $data, int $status): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->removeHeader('Cache-Control')->setHeader('Cache-Control', 'no-store')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow')
            ->setHeader('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'")
            ->setContentType('text/html', 'UTF-8')
            ->setBody(view('install/page', $data));
    }
}
