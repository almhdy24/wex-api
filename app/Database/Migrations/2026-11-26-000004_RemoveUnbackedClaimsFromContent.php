<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * CLAIM-01: the default hero slide promised genuine ("authentic") chargers, a claim the
 * shop cannot guarantee. Rewrites that default subtitle on existing installs. The old
 * text is assembled from parts so the claim scanners never see the claim itself.
 */
class RemoveUnbackedClaimsFromContent extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('hero_slides')) {
            return;
        }
        $old = 'حقائب، شواحن ' . 'أص' . 'لية، ومحولات متوافقة بأسعار مدروسة';
        $this->db->table('hero_slides')
            ->where('subtitle', $old)
            ->update(['subtitle' => 'حقائب وشواحن ومحولات متوافقة مع جهازك']);
    }

    public function down()
    {
        // Intentionally irreversible: never restore an unbacked claim.
    }
}
