<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Default content named one physical place (Al-Waha mall, Omdurman). The shop address is
 * an admin setting ({{shop_address}}); rewrite the defaults only where still unmodified.
 */
class RemoveHardCodedLocation extends Migration
{
    private const REWRITES = [
        ['hero_slides', 'subtitle', 'أجهزة مستوردة متوفرة في فرعنا بأم درمان', 'أجهزة مستوردة بأسعار واضحة وتثبيت للسعر بعد الطلب'],
        ['payment_accounts', 'branch', 'الفرع الرئيسي - أم درمان', 'الفرع الرئيسي'],
        ['settings', 'value', 'https://maps.google.com/?q=Wex+Technology+Omdurman', ''],
        ['pages', 'body', "ويكس تكنولوجي - متجر لأجهزة اللابتوب ومستلزمات الحواسيب في أم درمان والسودان.\n\nنقدم أجهزة لابتوب لتناسب احتياجات المهندسين والطلاب ورواد الأعمال، مع وضوح في الأسعار بـ ج.س ونظام تثبيت السعر.\n\nمقرنا: مول الواحة، الشهداء، أم درمان.", "ويكس تكنولوجي - متجر لأجهزة اللابتوب ومستلزمات الحواسيب.\n\nنقدم أجهزة لابتوب لتناسب احتياجات المهندسين والطلاب ورواد الأعمال، مع وضوح في الأسعار بـ ج.س ونظام تثبيت السعر.\n\nمقرنا: {{shop_address}}."],
    ];

    public function up()
    {
        foreach (self::REWRITES as [$table, $col, $old, $new]) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->where($col, $old)->update([$col => $new]);
            }
        }
        // Settings that still hold the old default address/pickup text become empty/neutral.
        if ($this->db->tableExists('settings')) {
            $this->db->table('settings')->where('key', 'shop_address')->where('value', 'مول الواحة، الشهداء، أم درمان')->update(['value' => '']);
            $this->db->table('settings')->where('key', 'policy_pickup')
                ->where('value', 'يمكنك استلام الجهاز من مقرنا في مول الواحة، الشهداء، أم درمان.')
                ->update(['value' => 'يمكنك استلام الجهاز من المحل بعد تأكيد الطلب.']);
        }
    }

    public function down()
    {
        // Not restored: a hard-coded place is wrong for any shop that set its own address.
    }
}
