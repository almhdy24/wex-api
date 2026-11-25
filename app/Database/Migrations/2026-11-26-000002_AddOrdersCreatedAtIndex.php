<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * D-7: dashboard "today" counts, admin date filters and reports scan orders by created_at.
 */
class AddOrdersCreatedAtIndex extends Migration
{
    private const INDEX = 'orders_created_at_index';

    public function up()
    {
        if ($this->db->tableExists('orders') && !array_key_exists(self::INDEX, $this->db->getIndexData('orders'))) {
            $this->db->query('CREATE INDEX ' . self::INDEX . ' ON ' . $this->db->prefixTable('orders') . ' (created_at)');
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('orders') || !array_key_exists(self::INDEX, $this->db->getIndexData('orders'))) {
            return;
        }
        if ($this->db->DBDriver === 'SQLite3') {
            $this->db->query('DROP INDEX IF EXISTS ' . self::INDEX);
        } else {
            $this->db->query('DROP INDEX ' . self::INDEX . ' ON ' . $this->db->prefixTable('orders'));
        }
    }
}
