<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use Config\Wex;

class AdminSeeder extends Seeder
{
    public function run()
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@wextechnology.com');
        $now        = gmdate('Y-m-d H:i:s');

        $existing = $this->db->table('users')->where('email', $adminEmail)->get()->getRow();

        if ($existing) {
            $this->db->table('users')->where('id', $existing->id)->update([
                'role'              => Wex::ROLE_ADMIN,
                'email_verified_at' => $existing->email_verified_at ?? $now,
                'is_active'         => 1,
                'updated_at'        => $now,
            ]);
        } else {
            $this->db->table('users')->insert([
                'name'                => 'مدير النظام',
                'email'               => $adminEmail,
                'phone'               => null,
                'role'                => Wex::ROLE_ADMIN,
                'email_verified_at'   => $now,
                'email_notifications' => 1,
                'is_active'           => 1,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }
    }
}
