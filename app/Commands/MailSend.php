<?php

namespace App\Commands;

use App\Services\MailService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * MailSend
 *
 * Processes pending emails in email_outbox.
 * Usage:
 *   php spark mail:send
 *   php spark mail:send --batch=50
 */
class MailSend extends BaseCommand
{
    protected $group       = 'Emails';
    protected $name        = 'mail:send';
    protected $description = 'Processes pending emails from the email_outbox table.';
    protected $usage       = 'mail:send [--batch=<count>]';
    protected $options     = [
        '--batch' => 'Number of emails to process per run (default 20, max 100).',
    ];

    public function run(array $params)
    {
        $batch = (int) ($params['batch'] ?? CLI::getOption('batch') ?? 20);
        if ($batch < 1) {
            $batch = 20;
        }

        CLI::write('[' . gmdate('Y-m-d H:i:s') . " UTC] Processing up to {$batch} email(s) from outbox...", 'yellow');

        $mailService = new MailService();
        $result = $mailService->processOutbox($batch);

        CLI::write("Emails sent: {$result['sent']}", 'green');
        if ($result['failed'] > 0) {
            CLI::write("Emails failed/retried: {$result['failed']}", 'red');
        }

        return EXIT_SUCCESS;
    }
}
