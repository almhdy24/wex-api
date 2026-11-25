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
        $this->fromEmail = env('MAIL_FROM_EMAIL', 'noreply@wextechnology.com');
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
        ]);

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
                $payload = $item->getPayload();
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

    protected function sendViaSmtp(string $to, string $subject, string $htmlBody, ?string $altText = null): bool
    {
        $email = Services::email();

        $email->setFrom($this->fromEmail, $this->fromName);
        $email->setTo($to);
        $email->setSubject($subject);
        $email->setMessage($htmlBody);

        if ($altText !== null && $altText !== '') {
            $email->setAltMessage($altText);
        }

        $sent = $email->send(false);

        if (!$sent) {
            log_message('error', 'SMTP Send Failed: {error}', [
                'error' => $email->printDebugger(['headers']),
            ]);
        }

        return $sent;
    }
}
