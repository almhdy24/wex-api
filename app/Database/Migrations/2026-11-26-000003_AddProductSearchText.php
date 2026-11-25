<?php

namespace App\Database\Migrations;

use App\Libraries\ArabicSearch;
use CodeIgniter\Database\Migration;

/**
 * UX-SEARCH-01: normalised, cross-script search text per product (see ArabicSearch).
 */
class AddProductSearchText extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('products')) {
            return;
        }
        if (!$this->db->fieldExists('search_text', 'products')) {
            $this->forge->addColumn('products', ['search_text' => ['type' => 'TEXT', 'null' => true]]);
        }

        $brands = [];
        foreach ($this->db->table('brands')->select('id, name')->get()->getResultArray() as $b) {
            $brands[(int) $b['id']] = $b['name'];
        }
        $cats = [];
        foreach ($this->db->table('categories')->select('id, name_ar, name_en')->get()->getResultArray() as $c) {
            $cats[(int) $c['id']] = trim(($c['name_ar'] ?? '') . ' ' . ($c['name_en'] ?? ''));
        }
        foreach ($this->db->table('products')->get()->getResultArray() as $p) {
            $this->db->table('products')->where('id', $p['id'])->update(['search_text' => ArabicSearch::index(
                (string) $p['name'], (string) ($p['short_description'] ?? ''), (string) ($p['description'] ?? ''),
                (string) ($p['specs'] ?? ''), (string) ($brands[(int) ($p['brand_id'] ?? 0)] ?? ''), (string) ($cats[(int) ($p['category_id'] ?? 0)] ?? ''),
            )]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('products') && $this->db->fieldExists('search_text', 'products')) {
            $this->forge->dropColumn('products', 'search_text');
        }
    }
}
