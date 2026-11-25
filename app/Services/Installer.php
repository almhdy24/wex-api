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
    public function install(string $adminEmail, string $adminName): array
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

        return ['migrated' => true, 'admin_email' => $adminEmail, 'admin_created' => $created];
    }

    protected function lockPath(): string
    {
        return WRITEPATH . 'install.lock';
    }
}
