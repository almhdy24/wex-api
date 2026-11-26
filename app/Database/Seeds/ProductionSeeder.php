<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * What a fresh production install needs and nothing else (run by the installer):
 * operating defaults, ordering CLOSED until the admin completes the setup checklist, and
 * the three structural categories the storefront navigation uses. No products, brands,
 * zones, payment accounts, rate or contact data: those are the shop's real data, entered
 * in admin. Only inserts missing keys, so re-running never overwrites the shop's values.
 */
class ProductionSeeder extends Seeder
{
    public function run()
    {
        $now = gmdate('Y-m-d H:i:s');
        $defaults = [
            'orders_enabled'       => '0',
            'lock_hours'           => '4',
            'app_discount_percent' => '3',
            'rounding_step_sdg'    => '100',
            'low_stock_threshold'  => '2',
            'shop_name'            => 'ويكس تكنولوجي',
            'push_enabled'         => '1',
            'ticker_enabled'       => '0',
            'prices_updated_at'    => $now,
        ];

        foreach ($defaults as $key => $value) {
            $exists = $this->db->table('settings')->where('key', $key)->countAllResults();
            if ($exists === 0) {
                $this->db->table('settings')->insert(['key' => $key, 'value' => $value, 'updated_at' => $now]);
            } elseif ($key === 'orders_enabled') {
                // The content migration seeds orders_enabled = 1; a fresh shop starts closed.
                $this->db->table('settings')->where('key', $key)->update(['value' => '0', 'updated_at' => $now]);
            }
        }

        $this->call(CategoriesSeeder::class);
    }
}
