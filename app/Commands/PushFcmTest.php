<?php

namespace App\Commands;

use App\Entities\PushSubscription;
use App\Services\Push\FcmProvider;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class PushFcmTest extends BaseCommand
{
    protected $group       = 'Push';
    protected $name        = 'push:fcm-test';
    protected $description = 'Sends a test push notification via FCM HTTP v1 to the given device token.';
    protected $usage       = 'push:fcm-test <token>';

    public function run(array $params)
    {
        $token = trim((string) ($params[0] ?? ''));
        if ($token === '') {
            CLI::error('Usage: php spark push:fcm-test <fcm_token>');
            return EXIT_ERROR;
        }

        $provider = new FcmProvider();
        if (!$provider->isEnabled()) {
            CLI::error('FCM_ENABLED is false. Set FCM_ENABLED=true and FCM_SERVICE_ACCOUNT_PATH in .env first.');
            return EXIT_ERROR;
        }

        $sub = new PushSubscription([
            'platform'          => 'android',
            'provider'          => 'fcm',
            'endpoint_or_token' => $token,
        ]);

        $result = $provider->send($sub, [
            'title' => 'إشعار تجريبي من ويكس',
            'body'  => 'تم اختبار توصيل إشعارات FCM بنجاح.',
            'tag'   => 'wex-fcm-test',
            'data'  => [
                'type' => 'test',
                'url'  => '/notifications',
            ],
        ]);

        if ($result->success) {
            CLI::write('FCM test notification sent successfully (HTTP ' . $result->statusCode . ').', 'green');
            return EXIT_SUCCESS;
        }

        CLI::error('FCM send failed: ' . ($result->error ?? 'unknown error') . ($result->expired ? ' [EXPIRED]' : ''));
        return EXIT_ERROR;
    }
}
