<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Data typo seen in production: "HP BROPOOK 440 G7" (should be "HP ProBook 440 G7").
 * Fixes the visible text only; slugs stay stable so existing links keep working.
 */
class FixProductNameTypos extends Migration
{
    private const FIXES = ['/bropook/i' => 'ProBook', '/probok/i' => 'ProBook', '/elitebok/i' => 'EliteBook'];

    public function up()
    {
        if (!$this->db->tableExists('products')) {
            return;
        }
        $rows = $this->db->table('products')->select('id, name, short_description, description')->get()->getResultArray();
        foreach ($rows as $r) {
            $upd = [];
            foreach (['name', 'short_description', 'description'] as $col) {
                $v = (string) ($r[$col] ?? '');
                $n = preg_replace(array_keys(self::FIXES), array_values(self::FIXES), $v);
                if ($n !== null && $n !== $v) {
                    $upd[$col] = $n;
                }
            }
            if ($upd) {
                $this->db->table('products')->where('id', $r['id'])->update($upd);
            }
        }
    }

    public function down()
    {
        // Not restored: the previous text was a typo.
    }
}
