<?php

namespace App\Commands;

use App\Services\CronRunner;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * One bounded pass of all periodic jobs (same runner as /api/v1/internal/cron).
 * Plesk Scheduled Task (HelioHost), every 5 minutes:
 *   /opt/plesk/php/8.3/bin/php -f httpdocs/_wex_core/spark cron:run
 */
class CronRun extends BaseCommand
{
    protected $group       = 'Wex';
    protected $name        = 'cron:run';
    protected $description = 'Runs orders:expire, push:work, push:reminders, mail:send and the daily integrity check.';

    public function run(array $params)
    {
        $result = (new CronRunner())->run();
        CLI::write(json_encode($result, JSON_UNESCAPED_UNICODE));

        return $result['status'] === 'busy' ? 75 : EXIT_SUCCESS;
    }
}
