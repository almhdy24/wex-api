<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPricesUpdatedAtAndOrderItemSdgColumns extends Migration
{
    public function up()
    {
        // 1. Add unit_price_sdg and line_total_sdg to order_items
        $fields = [
            'unit_price_sdg' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'line_total_sdg' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
        ];

        $this->forge->addColumn('order_items', $fields);

        // 2. Backfill existing order_items rows if any
        $orderItems = $this->db->table('order_items')->get()->getResultArray();
        if (!empty($orderItems)) {
            foreach ($orderItems as $item) {
                // Get order rate snapshot or fallback to 650
                $order = $this->db->table('orders')->where('id', $item['order_id'])->get()->getRow();
                $rate = $order ? (int) $order->rate_sdg_snapshot : 650;
                $unitPriceAed = (int) $item['unit_price_aed'];
                $qty = (int) $item['quantity'];

                // Rounding step 100
                $rawUnitPriceSdg = $unitPriceAed * $rate;
                $unitPriceSdg = (int) (ceil($rawUnitPriceSdg / 100) * 100);
                $lineTotalSdg = $unitPriceSdg * $qty;

                $this->db->table('order_items')->where('id', $item['id'])->update([
                    'unit_price_sdg' => $unitPriceSdg,
                    'line_total_sdg' => $lineTotalSdg,
                ]);
            }
        }

        // 3. Ensure prices_updated_at setting exists
        $now = gmdate('Y-m-d H:i:s');
        $exists = $this->db->table('settings')->where('key', 'prices_updated_at')->countAllResults();
        if ($exists === 0) {
            $this->db->table('settings')->insert([
                'key'        => 'prices_updated_at',
                'value'      => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('order_items', ['unit_price_sdg', 'line_total_sdg']);
        $this->db->table('settings')->where('key', 'prices_updated_at')->delete();
    }
}
