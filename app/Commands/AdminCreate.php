<?php

namespace App\Commands;

use App\Models\UserModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Wex;

class AdminCreate extends BaseCommand
{
    protected $group       = 'Admin';
    protected $name        = 'admin:create';
    protected $description = 'Creates a new administrator account or promotes an existing user to admin.';

    protected $usage = 'admin:create [options]';
    protected $arguments = [];
    protected $options = [
        '--email' => 'User email address to create or promote to admin',
        '--name'  => 'Admin user full name (optional)',
    ];

    public function run(array $params)
    {
        $email = CLI::getOption('email');
        $name  = CLI::getOption('name');

        if (empty($email)) {
            foreach ($_SERVER['argv'] ?? [] as $arg) {
                if (str_starts_with($arg, '--email=')) {
                    $email = substr($arg, 8);
                } elseif (str_starts_with($arg, '-email=')) {
                    $email = substr($arg, 7);
                } elseif (str_starts_with($arg, '--name=')) {
                    $name = substr($arg, 7);
                } elseif (str_starts_with($arg, '-name=')) {
                    $name = substr($arg, 6);
                } elseif (filter_var($arg, FILTER_VALIDATE_EMAIL)) {
                    $email = $arg;
                }
            }
        }

        if (empty($email)) {
            $email = CLI::prompt('Enter admin email address');
        }

        $email = strtolower(trim((string) $email));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            CLI::error('Invalid email address format.');
            return;
        }

        if (empty($name)) {
            $name = 'مدير النظام';
        }

        $userModel = new UserModel();
        $user      = $userModel->where('email', $email)->first();
        $now       = gmdate('Y-m-d H:i:s');

        if ($user) {
            $userModel->update($user->id, [
                'role'              => Wex::ROLE_ADMIN,
                'email_verified_at' => $user->email_verified_at ?? $now,
                'is_active'         => 1,
                'updated_at'        => $now,
            ]);

            CLI::write("User [{$email}] was successfully promoted to ADMIN.", 'green');
        } else {
            $userModel->insert([
                'name'                => $name,
                'email'               => $email,
                'phone'               => null,
                'role'                => Wex::ROLE_ADMIN,
                'email_verified_at'   => $now,
                'email_notifications' => 1,
                'is_active'           => 1,
                'last_login_at'       => null,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);

            CLI::write("Administrator [{$email}] was created successfully.", 'green');
        }
    }
}
