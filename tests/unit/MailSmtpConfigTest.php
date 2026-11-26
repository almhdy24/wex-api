<?php

namespace Tests\Unit;

use App\Services\MailService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * MAIL_DRIVER=smtp used to fall through to PHP mail() (Config\Email defaults to
 * protocol 'mail', mailType 'text'), so production SMTP settings were ignored.
 */
final class MailSmtpConfigTest extends CIUnitTestCase
{
    public function testSmtpDriverSendsWithSmtpProtocolAndHtml(): void
    {
        putenv('MAIL_DRIVER=smtp');
        $_ENV['MAIL_DRIVER'] = 'smtp';
        $cfg = config('Email');
        $cfg->protocol = 'mail';
        $cfg->mailType = 'text';
        $cfg->SMTPHost = '127.0.0.1';
        $cfg->SMTPPort = 1; // nothing listens: fails fast, we only check the configuration used
        $cfg->SMTPTimeout = 1;

        $ok = (new MailService())->send('owner@test.invalid', 's', '<b>x</b>', 'x');

        $this->assertFalse($ok);
        $this->assertSame('smtp', config('Email')->protocol);
        $this->assertSame('html', config('Email')->mailType);

        putenv('MAIL_DRIVER');
        unset($_ENV['MAIL_DRIVER']);
    }
}
