<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run()
    {
        $now = gmdate('Y-m-d H:i:s');

        $settings = [
            ['key' => 'exchange_rate_sdg',      'value' => '650'],
            ['key' => 'lock_hours',              'value' => '4'],
            ['key' => 'app_discount_percent',    'value' => '3'],
            ['key' => 'rounding_step_sdg',       'value' => '100'],
            ['key' => 'low_stock_threshold',     'value' => '2'],
            ['key' => 'shop_name',               'value' => 'Wex Technology (ويكس تكنولوجي)'],
            ['key' => 'shop_address',            'value' => 'مول الواحة، الشهداء، أم درمان'],
            ['key' => 'shop_phone',              'value' => '+249 9XX XXX XXX'],
            ['key' => 'shop_whatsapp',           'value' => '249912345678'],
            ['key' => 'working_hours',           'value' => 'من 10 صباحاً إلى 10 مساءً، عدا الجمعة'],
            ['key' => 'bank_name',               'value' => 'بنك الخرطوم (تطبيق بنكك) - تجريبي'],
            ['key' => 'bank_account_name',       'value' => 'Wex Technology - ويكس تكنولوجي (حساب تجريبي)'],
            ['key' => 'bank_account_number',     'value' => '2849102'],
        ];

        foreach ($settings as $setting) {
            $existing = $this->db->table('settings')->where('key', $setting['key'])->get()->getRow();
            if ($existing) {
                $this->db->table('settings')->where('key', $setting['key'])->update([
                    'value'      => $setting['value'],
                    'updated_at' => $now,
                ]);
            } else {
                $this->db->table('settings')->insert([
                    'key'        => $setting['key'],
                    'value'      => $setting['value'],
                    'updated_at' => $now,
                ]);
            }
        }

        // Also record initial exchange rate in history if not already recorded
        $hasRateHistory = $this->db->table('exchange_rates')->countAllResults();
        if ($hasRateHistory === 0) {
            $this->db->table('exchange_rates')->insert([
                'rate_sdg'   => 650,
                'changed_by' => null,
                'created_at' => $now,
            ]);
        }
    }
}
