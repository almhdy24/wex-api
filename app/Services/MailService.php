<?php

namespace App\Services;

use App\Entities\EmailOutbox;
use App\Models\EmailOutboxModel;
use Config\Services;
use Config\Wex;

/**
 * MailService
 *
 * Handles sending emails (via SMTP or writing to local disk logs),
 * managing the asynchronous Email Outbox pattern, and processing queued emails.
 */
class MailService
{
    protected string $driver;
    protected string $fromEmail;
    protected string $fromName;
    protected EmailOutboxModel $outbox;

    public function __construct(?EmailOutboxModel $outbox = null)
    {
        $this->driver    = env('MAIL_DRIVER', 'log');
        $this->fromEmail = \App\Libraries\SiteIdentity::fromEmail();
        $this->fromName  = env('MAIL_FROM_NAME', 'Wex Technology');
        $this->outbox    = $outbox ?? new EmailOutboxModel();
    }

    /**
     * Sends an email immediately or writes it to writable/logs/emails/ depending on MAIL_DRIVER.
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $altText = null): bool
    {
        if ($this->driver === 'log') {
            return $this->writeToLog($to, $subject, $htmlBody, $altText);
        }

        return $this->sendViaSmtp($to, $subject, $htmlBody, $altText);
    }

    /**
     * Sends an OTP verification code email synchronously.
     */
    public function sendOtp(string $toEmail, string $code): bool
    {
        $subject = 'رمز التحقق من ويكس تكنولوجي: ' . $code;

        $html = view('emails/otp', [
            'code'      => $code,
            'app_url'   => env('APP_URL', 'http://localhost:3000'),
            'shop_name' => 'Wex Technology - ويكس تكنولوجي',
        ] + $this->shopFacts());

        $alt = "رمز الدخول الخاص بك إلى ويكس تكنولوجي هو: {$code}\nينتهي الرمز خلال 10 دقائق.\nإذا لم تطلب هذا الرمز يمكنك تجاهل هذه الرسالة.";

        return $this->send($toEmail, $subject, $html, $alt);
    }

    /**
     * Enqueues an email into the email_outbox table for asynchronous background sending.
     */
    public function enqueue(
        ?int $userId,
        string $toEmail,
        string $subject,
        string $template,
        array $payload = []
    ): int {
        $now = gmdate('Y-m-d H:i:s');

        $outboxItem = new EmailOutbox([
            'user_id'      => $userId,
            'to_email'     => $toEmail,
            'subject'      => $subject,
            'template'     => $template,
            'payload'      => $payload,
            'status'       => Wex::EMAIL_STATUS_PENDING,
            'attempts'     => 0,
            'available_at' => $now,
            'created_at'   => $now,
        ]);

        return (int) $this->outbox->insert($outboxItem);
    }

    /**
     * Processes pending emails in the outbox.
     * Retries failed items up to 5 times with exponential backoff.
     *
     * @return array{sent: int, failed: int}
     */
    public function processOutbox(int $batchSize = 20): array
    {
        $batchSize = max(1, min(100, $batchSize));
        $now = gmdate('Y-m-d H:i:s');

        $pending = $this->outbox
            ->where('status', Wex::EMAIL_STATUS_PENDING)
            ->where('available_at <=', $now)
            ->where('attempts <', 5)
            ->orderBy('id', 'ASC')
            ->findAll($batchSize);

        $sentCount   = 0;
        $failedCount = 0;

        foreach ($pending as $item) {
            // Lease the row first: overlapping mail:send runs (cron + slow SMTP) must not
            // both send it. A crashed run's row becomes available again after the lease.
            if (!$this->claim((int) $item->id)) {
                continue;
            }

            $html = '';
            try {
                $payload = $item->getPayload() + $this->shopFacts();
                $html = view('emails/' . $item->template, $payload);
            } catch (\Throwable $e) {
                // If template missing or render fails, record error
                $this->markFailed($item, 'فشل في بناء قالب البريد: ' . $e->getMessage());
                $failedCount++;
                continue;
            }

            try {
                $success = $this->send($item->to_email, $item->subject, $html);
                if ($success) {
                    $this->outbox->update($item->id, [
                        'status'     => Wex::EMAIL_STATUS_SENT,
                        'sent_at'    => gmdate('Y-m-d H:i:s'),
                        'last_error' => null,
                    ]);
                    $sentCount++;
                } else {
                    $this->markFailed($item, 'تعذر إرسال البريد عبر الخادم');
                    $failedCount++;
                }
            } catch (\Throwable $e) {
                $this->markFailed($item, $e->getMessage());
                $failedCount++;
            }
        }

        return ['sent' => $sentCount, 'failed' => $failedCount];
    }

    public const CLAIM_LEASE_SECONDS = 600;

    protected function claim(int $id): bool
    {
        $this->outbox->builder()
            ->where('id', $id)
            ->where('status', Wex::EMAIL_STATUS_PENDING)
            ->where('available_at <=', gmdate('Y-m-d H:i:s'))
            ->update(['available_at' => gmdate('Y-m-d H:i:s', time() + self::CLAIM_LEASE_SECONDS)]);

        return $this->outbox->db->affectedRows() === 1;
    }

    /**
     * Helper to update retry status and calculate exponential backoff.
     */
    protected function markFailed(EmailOutbox $item, string $errorMessage): void
    {
        $attempts = (int) $item->attempts + 1;
        $isMaxAttempts = ($attempts >= 5);

        // Exponential backoff: 2^attempts minutes (2m, 4m, 8m, 16m)
        $backoffSeconds = pow(2, $attempts) * 60;
        $nextAvailable  = gmdate('Y-m-d H:i:s', time() + $backoffSeconds);

        $this->outbox->update($item->id, [
            'status'       => $isMaxAttempts ? Wex::EMAIL_STATUS_FAILED : Wex::EMAIL_STATUS_PENDING,
            'attempts'     => $attempts,
            'last_error'   => mb_substr($errorMessage, 0, 500),
            'available_at' => $nextAvailable,
        ]);
    }

    protected function writeToLog(string $to, string $subject, string $htmlBody, ?string $altText = null): bool
    {
        $dir = WRITEPATH . 'logs/emails';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = sprintf(
            '%s/email_%s_%s.log',
            $dir,
            gmdate('Ymd_His'),
            substr(bin2hex(random_bytes(4)), 0, 6)
        );

        $content = "=== WEX TECHNOLOGY EMAIL LOG ===\n"
            . "Date: " . gmdate('Y-m-d H:i:s') . " UTC\n"
            . "To: " . $to . "\n"
            . "From: " . $this->fromName . " <" . $this->fromEmail . ">\n"
            . "Subject: " . $subject . "\n"
            . "--- PLAIN TEXT BODY ---\n"
            . ($altText ?? strip_tags($htmlBody)) . "\n"
            . "--- HTML BODY ---\n"
            . $htmlBody . "\n"
            . "================================\n";

        return (bool) file_put_contents($filename, $content, LOCK_EX);
    }

    /** Shop address / hours / phone from admin settings (empty when not set). */
    protected function shopFacts(): array
    {
        try {
            $s = new \App\Services\SettingService();
            return [
                'shop_address'  => trim((string) $s->get('shop_address', '')),
                'working_hours' => trim((string) $s->get('working_hours', '')),
            ];
        } catch (\Throwable $e) {
            return ['shop_address' => '', 'working_hours' => ''];
        }
    }

    protected function sendViaSmtp(string $to, string $subject, string $htmlBody, ?string $altText = null): bool
    {
        // MAIL_DRIVER=smtp must actually send over the wire: CI4's Config\Email defaults to
        // PHP mail() and plain text, which silently ignored the SMTP settings and mangled the
        // HTML body. The transport is configurable because shared hosts (HelioHost/Plesk) often
        // block outbound SMTP ports and only accept mail through the local MTA:
        //   MAIL_PROTOCOL = smtp     (default) -> email.SMTP* settings
        //   MAIL_PROTOCOL = sendmail            -> local MTA at MAIL_SENDMAIL_PATH
        //   MAIL_PROTOCOL = mail                -> PHP mail()
        $config   = config('Email');
        $protocol = strtolower(trim((string) env('MAIL_PROTOCOL', 'smtp'))) ?: 'smtp';

        $config->protocol = in_array($protocol, ['smtp', 'sendmail', 'mail'], true) ? $protocol : 'smtp';
        $config->mailType = 'html';
        $config->SMTPTimeout = max(15, (int) $config->SMTPTimeout);
        if ($config->protocol === 'sendmail') {
            $config->mailPath = (string) env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail');
        }

        $email = Services::email($config, false);

        $email->setFrom($this->fromEmail, $this->fromName);
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMessage($htmlBody);

        if ($altText !== null && $altText !== '') {
            $email->setAltMessage($altText);
        }

        $sent = $email->send(false);

        if (!$sent) {
            // Log only the transport and the SMTP reason, never the full message: the OTP code
            // travels in the subject, and the debugger dump would leak it into writable/logs.
            log_message('error', 'Mail send failed (protocol={protocol}, host={host}): {reason}', [
                'protocol' => $config->protocol,
                'host'     => $config->SMTPHost,
                'reason'   => $this->smtpErrorReason($email),
            ]);
        }

        return $sent;
    }

    /** The SMTP failure reason, with the message body/subject (which carries the OTP) stripped out. */
    private function smtpErrorReason(\CodeIgniter\Email\Email $email): string
    {
        $debug = strip_tags($email->printDebugger(['headers']));
        $lines = [];
        foreach (preg_split('/\R/', $debug) as $line) {
            $line = trim($line);
            // Keep only CI4's own status lines; drop the echoed RFC822 message and its headers.
            if ($line !== '' && preg_match('/unable to|connect|smtp|refused|timed out|auth|tls|ssl|certificate/i', $line)) {
                $lines[] = $line;
            }
        }

        return $lines === [] ? 'unknown SMTP error' : implode(' | ', array_slice($lines, 0, 4));
    }
}
