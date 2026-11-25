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
            ['key' => 'prices_updated_at',      'value' => $now],
            ['key' => 'lock_hours',              'value' => '4'],
            ['key' => 'app_discount_percent',    'value' => '3'],
            ['key' => 'rounding_step_sdg',       'value' => '100'],
            ['key' => 'low_stock_threshold',     'value' => '2'],
            ['key' => 'shop_name',               'value' => 'Wex Technology (ويكس تكنولوجي)'],
            ['key' => 'shop_address',            'value' => 'مول الواحة، الشهداء، أم درمان'],
            ['key' => 'shop_phone',              'value' => ''], // set the real number in admin settings
            ['key' => 'shop_whatsapp',           'value' => ''], // never seed a placeholder: it is shown to customers
            ['key' => 'working_hours',           'value' => 'من 10 صباحاً إلى 10 مساءً، عدا الجمعة'],
            ['key' => 'push_enabled',            'value' => '1'],
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
