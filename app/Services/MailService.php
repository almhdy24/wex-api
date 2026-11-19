<?php

namespace App\Services;

use Config\Services;

/**
 * MailService
 *
 * Handles sending emails either via SMTP or writing to local disk log files.
 */
class MailService
{
    protected string $driver;
    protected string $fromEmail;
    protected string $fromName;

    public function __construct()
    {
        $this->driver    = env('MAIL_DRIVER', 'log');
        $this->fromEmail = env('MAIL_FROM_EMAIL', 'noreply@wextechnology.com');
        $this->fromName  = env('MAIL_FROM_NAME', 'Wex Technology');
    }

    /**
     * Sends an email or writes it to writable/logs/emails/ depending on MAIL_DRIVER.
     */
    public function send(string $to, string $subject, string $htmlBody, ?string $altText = null): bool
    {
        if ($this->driver === 'log') {
            return $this->writeToLog($to, $subject, $htmlBody, $altText);
        }

        return $this->sendViaSmtp($to, $subject, $htmlBody, $altText);
    }

    /**
     * Sends an OTP verification code email.
     */
    public function sendOtp(string $toEmail, string $code): bool
    {
        $subject = 'رمز التحقق من ويكس تكنولوجي: ' . $code;

        $html = view('emails/otp', [
            'code'     => $code,
            'app_url'  => env('APP_URL', 'http://localhost:3000'),
            'shop_name'=> 'Wex Technology - ويكس تكنولوجي',
        ]);

        $alt = "رمز الدخول الخاص بك إلى ويكس تكنولوجي هو: {$code}\nينتهي الرمز خلال 10 دقائق.\nإذا لم تطلب هذا الرمز يمكنك تجاهل هذه الرسالة.";

        return $this->send($toEmail, $subject, $html, $alt);
    }

    /**
     * Renders an email view template wrapped in the shared email layout.
     */
    public function renderTemplate(string $template, array $data = []): string
    {
        return view('emails/' . $template, $data);
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
