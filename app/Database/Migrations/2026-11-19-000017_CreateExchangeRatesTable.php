<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExchangeRatesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'rate_sdg' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'changed_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('changed_by');
        $this->forge->addKey('created_at');
        $this->forge->addForeignKey('changed_by', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('exchange_rates', true);
    }

    public function down()
    {
        $this->forge->dropTable('exchange_rates', true);
    }
}
