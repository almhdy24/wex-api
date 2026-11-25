<?php

namespace App\Commands;

use App\Services\PushService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * PushWork
 *
 * Cron worker:
 *   * * * * * cd /path/to/wex-api && php spark push:work >> /dev/null 2>&1
 */
class PushWork extends BaseCommand
{
    protected $group       = 'Push';
    protected $name        = 'push:work';
    protected $description = 'Processes pending and retryable rows in push_outbox.';
    protected $usage       = 'push:work [limit]';

    public function run(array $params)
    {
        $limit = isset($params[0]) ? max(1, (int) $params[0]) : 100;
        $service = new PushService();
        $stats = $service->processOutbox($limit);

        CLI::write(
            sprintf(
                '[%s UTC] push:work processed: sent=%d, failed=%d, expired=%d',
                gmdate('Y-m-d H:i:s'),
                $stats['sent'],
                $stats['failed'],
                $stats['expired']
            ),
            'green'
        );

        return EXIT_SUCCESS;
    }
}
