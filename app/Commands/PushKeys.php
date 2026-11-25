<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Minishlink\WebPush\VAPID;

class PushKeys extends BaseCommand
{
    protected $group       = 'Push';
    protected $name        = 'push:keys';
    protected $description = 'Generates VAPID public/private keys for Web Push and prints .env lines.';
    protected $usage       = 'push:keys';

    public function run(array $params)
    {
        $keys = VAPID::createVapidKeys();

        CLI::write('Add the following lines to your wex-api .env file:', 'green');
        CLI::newLine();
        CLI::write('VAPID_PUBLIC_KEY="' . $keys['publicKey'] . '"', 'yellow');
        CLI::write('VAPID_PRIVATE_KEY="' . $keys['privateKey'] . '"', 'yellow');
        CLI::write('VAPID_SUBJECT="mailto:support@wex.sd"', 'yellow');
        CLI::newLine();
        CLI::write('Warning: Rotating VAPID keys invalidates all existing Web Push subscriptions.', 'red');

        return EXIT_SUCCESS;
    }
}
