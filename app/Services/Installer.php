<?php

namespace App\Services;

use App\Exceptions\ValidationFailedException;
use App\Models\UserModel;
use Config\Wex;

/**
 * Self-locking first-run installer for hosts without SSH.
 * Enabled only while INSTALL_TOKEN (>= 32 chars) is set; refuses forever after
 * WRITEPATH/install.lock exists. Runs all migrations and creates the first admin.
 */
class Installer
{
    public function isEnabled(): bool
    {
        return strlen((string) env('INSTALL_TOKEN', '')) >= 32;
    }

    public function isLocked(): bool
    {
        return is_file($this->lockPath());
    }

    public function tokenIsValid(string $presented): bool
    {
        return $this->isEnabled() && $presented !== '' && hash_equals((string) env('INSTALL_TOKEN'), $presented);
    }

    /**
     * @return array{migrated: bool, admin_email: string, admin_created: bool}
     */
    public function install(string $adminEmail, string $adminName, bool $sampleContent = false): array
    {
        $adminEmail = strtolower(trim($adminEmail));
        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationFailedException(['admin_email' => 'بريد المدير مطلوب ويجب أن يكون صحيحاً.']);
        }

        // Take the lock first (exclusive create): two concurrent installs cannot both run.
        $fh = @fopen($this->lockPath(), 'x');
        if ($fh === false) {
            throw new \App\Exceptions\ConflictException('تم التثبيت مسبقاً.');
        }
        fwrite($fh, gmdate('c') . "\n");
        fclose($fh);

        try {
            service('migrations')->setNamespace(null)->latest();

            // Production data only: defaults, ordering closed, structural categories.
            $seeder = \Config\Database::seeder();
            $seeder->setSilent(true);
            $seeder->call(\App\Database\Seeds\ProductionSeeder::class);
            // Optional demo catalogue (OFF by default): brands, zones and sample products.
            if ($sampleContent) {
                $seeder->call(\App\Database\Seeds\BrandsSeeder::class);
                $seeder->call(\App\Database\Seeds\ShippingZonesSeeder::class);
                $seeder->call(\App\Database\Seeds\ProductsSeeder::class);
            }

            $users = new UserModel();
            $existing = $users->where('email', $adminEmail)->first();
            $created = false;
            if ($existing) {
                $users->update($existing->id, ['role' => Wex::ROLE_ADMIN, 'is_active' => 1]);
            } else {
                $users->insert([
                    'name'                => trim($adminName) !== '' ? trim($adminName) : 'مدير المتجر',
                    'email'               => $adminEmail,
                    'role'                => Wex::ROLE_ADMIN,
                    'email_verified_at'   => gmdate('Y-m-d H:i:s'),
                    'email_notifications' => 1,
                    'is_active'           => 1,
                ]);
                $created = true;
            }
        } catch (\Throwable $e) {
            @unlink($this->lockPath()); // allow a retry after fixing the cause
            throw $e;
        }

        log_message('critical', 'Installer completed for {email}. Remove INSTALL_TOKEN from .env.', ['email' => $adminEmail]);

        return ['migrated' => true, 'admin_email' => $adminEmail, 'admin_created' => $created, 'sample_content' => $sampleContent];
    }

    /** Secrets the app needs in .env (values are never displayed once set). */
    public const SECRET_KEYS = ['JWT_SECRET', 'encryption.key', 'CRON_TOKEN', 'VAPID_PUBLIC_KEY', 'VAPID_PRIVATE_KEY'];

    /**
     * Host requirements for the installer page.
     *
     * @return list<array{label: string, ok: bool, detail: string, required: bool}>
     */
    public function requirements(): array
    {
        $checks = [];
        $add = static function (string $label, bool $ok, string $detail, bool $required = true) use (&$checks) {
            $checks[] = compact('label', 'ok', 'detail', 'required');
        };
        $add('إصدار PHP 8.3 أو أحدث', version_compare(PHP_VERSION, '8.3.0', '>='), PHP_VERSION);
        foreach (['mysqli', 'mbstring', 'intl', 'gd', 'json', 'openssl', 'curl', 'fileinfo'] as $ext) {
            $add('امتداد ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'مفعّل' : 'غير مفعّل', $ext !== 'curl');
        }
        foreach (['', 'cache', 'logs', 'session', 'uploads/proofs'] as $dir) {
            $path = rtrim(WRITEPATH . $dir, '/');
            $add('مجلد writable/' . $dir . ' قابل للكتابة', is_dir($path) && is_writable($path), $dir === '' ? 'writable' : $dir);
        }
        $pub = FCPATH . '../uploads/products';
        $add('مجلد uploads/products قابل للكتابة', is_dir($pub) && is_writable($pub), 'uploads/products');
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
        $add('الاتصال عبر HTTPS', $https, $https ? 'نعم' : 'فعّل شهادة SSL قبل الإطلاق', false);
        // Reaching this page at /api/install at all proves the .htaccess rewrite works.
        $add('إعادة توجيه الروابط (mod_rewrite)', true, 'يعمل');
        $add('حد رفع الملفات', $this->bytes((string) ini_get('upload_max_filesize')) >= 5 * 1024 * 1024, (string) ini_get('upload_max_filesize'), false);
        $add('حد الذاكرة', $this->bytes((string) ini_get('memory_limit')) >= 128 * 1024 * 1024 || ini_get('memory_limit') === '-1', (string) ini_get('memory_limit'), false);

        return $checks;
    }

    /** @return array{ok: bool, detail: string} Never includes credentials. */
    public function databaseCheck(): array
    {
        try {
            $db = \Config\Database::connect();
            $db->initialize();
            $version = method_exists($db, 'getVersion') ? (string) $db->getVersion() : '';
            return ['ok' => true, 'detail' => trim($db->DBDriver . ' ' . $version)];
        } catch (\Throwable $e) {
            return ['ok' => false, 'detail' => 'تعذّر الاتصال بقاعدة البيانات. راجع بيانات database.default في ملف .env.'];
        }
    }

    /** @return array<string, bool> key => is set (values never returned) */
    public function secretsStatus(): array
    {
        $out = [];
        foreach (self::SECRET_KEYS as $k) {
            $out[$k] = trim((string) env($k, '')) !== '';
        }
        return $out;
    }

    /**
     * Generates every MISSING secret and writes it into .env. Existing values are never read
     * back or replaced. Returns the generated values only if .env could not be written (so
     * the owner can paste them once); otherwise an empty array.
     *
     * @return array{written: bool, values: array<string, string>}
     */
    public function generateMissingSecrets(): array
    {
        $values = [];
        $status = $this->secretsStatus();
        if (!$status['JWT_SECRET']) {
            $values['JWT_SECRET'] = bin2hex(random_bytes(32));
        }
        if (!$status['encryption.key']) {
            $values['encryption.key'] = 'hex2bin:' . bin2hex(random_bytes(32));
        }
        if (!$status['CRON_TOKEN']) {
            $values['CRON_TOKEN'] = bin2hex(random_bytes(24));
        }
        if ((!$status['VAPID_PUBLIC_KEY'] || !$status['VAPID_PRIVATE_KEY']) && class_exists(\Minishlink\WebPush\VAPID::class)) {
            try {
                $keys = \Minishlink\WebPush\VAPID::createVapidKeys();
                $values['VAPID_PUBLIC_KEY'] = $keys['publicKey'];
                $values['VAPID_PRIVATE_KEY'] = $keys['privateKey'];
            } catch (\Throwable $e) {
                // openssl without EC support: VAPID can be generated later with `php spark push:keys`
            }
        }
        if ($values === []) {
            return ['written' => true, 'values' => []];
        }

        $envFile = ROOTPATH . '.env';
        $content = is_file($envFile) ? (string) file_get_contents($envFile) : '';
        foreach ($values as $k => $v) {
            $re = '/^[ \t]*' . preg_quote($k, '/') . '[ \t]*=[ \t]*$/m';
            if (preg_match($re, $content)) {
                $content = (string) preg_replace($re, $k . ' = ' . $v, $content, 1);
            } else {
                $content = rtrim($content) . "\n" . $k . ' = ' . $v . "\n";
            }
        }
        $written = is_file($envFile) && is_writable($envFile) && file_put_contents($envFile, $content, LOCK_EX) !== false;

        return ['written' => $written, 'values' => $written ? [] : $values];
    }

    private function bytes(string $v): int
    {
        $v = trim($v);
        $n = (int) $v;
        return match (strtolower(substr($v, -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }

    protected function lockPath(): string
    {
        return WRITEPATH . 'install.lock';
    }
}
