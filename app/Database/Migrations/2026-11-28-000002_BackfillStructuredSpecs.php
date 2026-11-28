<?php

namespace App\Database\Migrations;

use App\Libraries\SpecSchema;
use CodeIgniter\Database\Migration;

/**
 * Best-effort migration of free-text `specs` into structured `spec_data`.
 * Idempotent: only rows with no spec_data/specs_legacy yet are touched, and the original text is
 * always copied to `specs_legacy` first. Rows the normalizer cannot fully place get
 * needs_review=1 (shown in the admin) and keep their original `specs` untouched.
 * Fully parsed rows get a regenerated `specs` label map, which also fixes swapped
 * screen / graphics-card values.
 *
 * Rollback: UPDATE products SET specs = specs_legacy WHERE specs_legacy IS NOT NULL;
 *           then clear spec_data / specs_legacy / needs_review if desired.
 */
class BackfillStructuredSpecs extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('products') || !$this->db->fieldExists('spec_data', 'products')) {
            return;
        }
        $rows = $this->db->table('products')
            ->select('id, type, specs')
            ->where('spec_data IS NULL', null, false)
            ->where('specs_legacy IS NULL', null, false)
            ->get()->getResultArray();

        foreach ($rows as $r) {
            $legacy = json_decode((string) ($r['specs'] ?? ''), true);
            if (!is_array($legacy) || $legacy === []) {
                continue;
            }
            $res = SpecSchema::fromLegacy((string) $r['type'], $legacy);
            $upd = [
                'specs_legacy' => (string) $r['specs'],
                'needs_review' => $res['needs_review'] ? 1 : 0,
            ];
            if ($res['data'] !== []) {
                $upd['spec_data'] = json_encode($res['data'], JSON_UNESCAPED_UNICODE);
                if (!$res['needs_review']) {
                    $map = SpecSchema::labelMap((string) $r['type'], $res['data']);
                    if ($map !== []) {
                        $upd['specs'] = json_encode($map, JSON_UNESCAPED_UNICODE);
                    }
                }
            }
            $this->db->table('products')->where('id', $r['id'])->update($upd);
        }
    }

    public function down()
    {
        // Non-destructive: restore with the rollback SQL in the class comment.
    }
}
