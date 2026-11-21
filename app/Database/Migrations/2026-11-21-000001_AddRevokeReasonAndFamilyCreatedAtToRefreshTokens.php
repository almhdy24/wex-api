<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddRevokeReasonAndFamilyCreatedAtToRefreshTokens extends Migration
{
    public function up()
    {
        $fields = [
            'revoke_reason' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => true,
                'after'      => 'revoked_at',
            ],
            'family_created_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'created_at',
            ],
        ];

        // Check if columns already exist before adding
        if (!$this->db->fieldExists('revoke_reason', 'refresh_tokens')) {
            $this->forge->addColumn('refresh_tokens', ['revoke_reason' => $fields['revoke_reason']]);
        }

        if (!$this->db->fieldExists('family_created_at', 'refresh_tokens')) {
            $this->forge->addColumn('refresh_tokens', ['family_created_at' => $fields['family_created_at']]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('revoke_reason', 'refresh_tokens')) {
            $this->forge->dropColumn('refresh_tokens', 'revoke_reason');
        }

        if ($this->db->fieldExists('family_created_at', 'refresh_tokens')) {
            $this->forge->dropColumn('refresh_tokens', 'family_created_at');
        }
    }
}
