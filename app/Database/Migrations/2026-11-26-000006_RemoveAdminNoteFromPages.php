<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * The default store pages ended with "(ملاحظة للإدارة: هذه مسودة أولية يُراجعها صاحب المحل)",
 * an internal note that customers saw on /pages/terms etc. Strip it wherever it remains;
 * the rest of an owner-edited page is untouched.
 */
class RemoveAdminNoteFromPages extends Migration
{
    private const NOTE = '(ملاحظة للإدارة: هذه مسودة أولية يُراجعها صاحب المحل)';

    public function up()
    {
        if (!$this->db->tableExists('pages')) {
            return;
        }
        foreach ($this->db->table('pages')->select('id, body')->like('body', self::NOTE)->get()->getResultArray() as $row) {
            $body = rtrim(str_replace(self::NOTE, '', (string) $row['body']));
            $this->db->table('pages')->where('id', $row['id'])->update(['body' => $body]);
        }
    }

    public function down()
    {
        // Not restored: the note was never meant for customers.
    }
}
