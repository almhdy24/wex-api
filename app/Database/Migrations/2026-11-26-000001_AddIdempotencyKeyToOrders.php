<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Adds orders.idempotency_key so a double-submitted checkout (double tap, retry
 * after a timeout) returns the original order instead of reserving stock twice.
 * NULLs are allowed many times by the unique index on both SQLite and MySQL.
 */
class AddIdempotencyKeyToOrders extends Migration
{
    private const INDEX = 'orders_user_idempotency_unique';

    public function up()
    {
        if (!$this->db->tableExists('orders')) {
            return;
        }

        if (!$this->db->fieldExists('idempotency_key', 'orders')) {
            $this->forge->addColumn('orders', [
                'idempotency_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 64,
                    'null'       => true,
                ],
            ]);
        }

        if (!array_key_exists(self::INDEX, $this->db->getIndexData('orders'))) {
            $table = $this->db->prefixTable('orders');
            $this->db->query('CREATE UNIQUE INDEX ' . self::INDEX . " ON {$table} (user_id, idempotency_key)");
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('orders') || !$this->db->fieldExists('idempotency_key', 'orders')) {
            return;
        }

        $table = $this->db->prefixTable('orders');
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('DROP INDEX IF EXISTS ' . self::INDEX);
        } else {
            $this->db->query('DROP INDEX ' . self::INDEX . " ON {$table}");
        }

        $this->forge->dropColumn('orders', 'idempotency_key');
    }
}
