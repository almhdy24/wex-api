<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Additive + idempotent: structured specs, accessory sub-type, review flag and draft flag.
 *
 * Rollback (manual, only if ever needed — nothing here is read by old code):
 *   ALTER TABLE products DROP COLUMN subcategory, DROP COLUMN spec_data,
 *     DROP COLUMN specs_legacy, DROP COLUMN needs_review, DROP COLUMN is_draft;
 * The old `specs` column is never modified by this migration.
 */
class AddProductSpecSchemaColumns extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('products')) {
            return;
        }
        $add = [];
        if (!$this->db->fieldExists('subcategory', 'products')) {
            $add['subcategory'] = ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true];
        }
        if (!$this->db->fieldExists('spec_data', 'products')) {
            $add['spec_data'] = ['type' => 'TEXT', 'null' => true];
        }
        if (!$this->db->fieldExists('specs_legacy', 'products')) {
            $add['specs_legacy'] = ['type' => 'TEXT', 'null' => true];
        }
        if (!$this->db->fieldExists('needs_review', 'products')) {
            $add['needs_review'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0];
        }
        if (!$this->db->fieldExists('is_draft', 'products')) {
            $add['is_draft'] = ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0];
        }
        if ($add !== []) {
            $this->forge->addColumn('products', $add);
        }
    }

    public function down()
    {
        // Intentionally non-destructive: see the rollback note above.
    }
}
