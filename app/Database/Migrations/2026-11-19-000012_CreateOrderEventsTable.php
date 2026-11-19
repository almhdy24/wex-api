<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOrderEventsTable extends Migration
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
            'order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'from_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'to_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
            ],
            'actor_user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true, // null = system
            ],
            'note' => [
                'type' => 'TEXT',
                'null' => true, // note shown to customer in timeline
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('order_id');
        $this->forge->addKey('actor_user_id');
        $this->forge->addForeignKey('order_id', 'orders', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('order_events', true);
    }

    public function down()
    {
        $this->forge->dropTable('order_events', true);
    }
}
