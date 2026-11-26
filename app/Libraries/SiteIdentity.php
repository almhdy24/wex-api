<?php

namespace App\Libraries;

/**
 * Sender identity derived from configuration, never from a hard-coded domain
 * (earlier defaults pointed at wextechnology.com / wex.sd, which are not the shop's).
 */
final class SiteIdentity
{
    /** Host of app.baseURL / APP_URL, or '' when unset. */
    public static function host(): string
    {
        $url = (string) (env('app.baseURL') ?: env('APP_URL') ?: config('App')->baseURL ?? '');
        $host = (string) (parse_url($url, PHP_URL_HOST) ?? '');
        return in_array($host, ['', 'localhost', '127.0.0.1'], true) ? '' : strtolower($host);
    }

    /** MAIL_FROM_EMAIL, else noreply@<site host>, else '' (mail is then not sendable). */
    public static function fromEmail(): string
    {
        $configured = trim((string) env('MAIL_FROM_EMAIL', ''));
        if ($configured !== '') {
            return $configured;
        }
        $host = preg_replace('/^www\./', '', self::host());
        return $host !== '' ? 'noreply@' . $host : '';
    }

    /** VAPID_SUBJECT, else mailto:<from email>, else the site URL. */
    public static function vapidSubject(): string
    {
        $configured = trim((string) env('VAPID_SUBJECT', ''));
        if ($configured !== '') {
            return $configured;
        }
        $from = self::fromEmail();
        if ($from !== '') {
            return 'mailto:' . $from;
        }
        $host = self::host();
        return $host !== '' ? 'https://' . $host : '';
    }
}
