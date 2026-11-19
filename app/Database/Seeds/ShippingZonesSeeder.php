<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ShippingZonesSeeder extends Seeder
{
    public function run()
    {
        $now = gmdate('Y-m-d H:i:s');

        $zones = [
            ['state_name_ar' => 'أم درمان',      'fee_sdg' => 5000],
            ['state_name_ar' => 'الخرطوم',       'fee_sdg' => 6000],
            ['state_name_ar' => 'بحري',          'fee_sdg' => 6000],
            ['state_name_ar' => 'الجزيرة',       'fee_sdg' => 9000],
            ['state_name_ar' => 'البحر الأحمر',  'fee_sdg' => 15000],
            ['state_name_ar' => 'كسلا',          'fee_sdg' => 14000],
            ['state_name_ar' => 'القضارف',       'fee_sdg' => 13000],
            ['state_name_ar' => 'سنار',          'fee_sdg' => 11000],
            ['state_name_ar' => 'النيل الأبيض',   'fee_sdg' => 10000],
            ['state_name_ar' => 'النيل الأزرق',   'fee_sdg' => 13000],
            ['state_name_ar' => 'نهر النيل',     'fee_sdg' => 10000],
            ['state_name_ar' => 'الشمالية',      'fee_sdg' => 12000],
            ['state_name_ar' => 'شمال كردفان',   'fee_sdg' => 14000],
            ['state_name_ar' => 'جنوب كردفان',   'fee_sdg' => 18000],
            ['state_name_ar' => 'غرب كردفان',    'fee_sdg' => 18000],
            ['state_name_ar' => 'شمال دارفور',   'fee_sdg' => 20000],
            ['state_name_ar' => 'جنوب دارفور',   'fee_sdg' => 22000],
            ['state_name_ar' => 'غرب دارفور',    'fee_sdg' => 24000],
            ['state_name_ar' => 'وسط دارفور',    'fee_sdg' => 22000],
            ['state_name_ar' => 'شرق دارفور',    'fee_sdg' => 22000],
        ];

        foreach ($zones as $zone) {
            $existing = $this->db->table('shipping_zones')
                ->where('state_name_ar', $zone['state_name_ar'])
                ->get()
                ->getRow();

            if ($existing) {
                $this->db->table('shipping_zones')
                    ->where('id', $existing->id)
                    ->update([
                        'fee_sdg'    => $zone['fee_sdg'],
                        'is_active'  => 1,
                        'updated_at' => $now,
                    ]);
            } else {
                $this->db->table('shipping_zones')->insert([
                    'state_name_ar' => $zone['state_name_ar'],
                    'fee_sdg'       => $zone['fee_sdg'],
                    'is_active'     => 1,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ]);
            }
        }
    }
}
