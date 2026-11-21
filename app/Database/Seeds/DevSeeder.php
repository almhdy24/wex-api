<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

class DevSeeder extends Seeder
{
    public function run()
    {
        $env = getenv('CI_ENVIRONMENT') ?: (defined('ENVIRONMENT') ? ENVIRONMENT : 'development');
        if (strtolower($env) === 'production') {
            throw new RuntimeException('DevSeeder cannot be executed in production environment.');
        }

        $now = gmdate('Y-m-d H:i:s');

        // Check if an active payment account exists
        $hasAccount = $this->db->table('payment_accounts')->where('is_active', 1)->countAllResults();
        if ($hasAccount === 0) {
            $this->db->table('payment_accounts')->insert([
                'bank_name'      => 'بنك الخرطوم (تطبيق بنكك)',
                'account_name'   => 'Wex Technology - متجر التقنية',
                'account_number' => '100200300',
                'branch'         => 'الفرع الرئيسي - أم درمان',
                'notes'          => 'يرجى كتابة رقم الطلب في خانة الملاحظات عند التحويل عبر تطبيق بنكك.',
                'is_active'      => 1,
                'sort_order'     => 1,
                'created_at'     => $now,
                'updated_at'     => $now,
            ]);
        }
    }
}
