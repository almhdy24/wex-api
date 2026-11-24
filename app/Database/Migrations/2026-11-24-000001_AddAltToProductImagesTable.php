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

        $this->forge->addColumn('product_images', $fields);
    }

    public function down()
    {
        $this->forge->dropColumn('product_images', 'alt');
    }
}
