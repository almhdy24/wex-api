<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAltToProductImagesTable extends Migration
{
    public function up()
    {
        $fields = [
            'alt' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
        ];

        if ($this->db->tableExists('product_images') && !$this->db->fieldExists('alt', 'product_images')) {
            $this->forge->addColumn('product_images', $fields);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('product_images') && $this->db->fieldExists('alt', 'product_images')) {
            $this->forge->dropColumn('product_images', 'alt');
        }
    }
}
