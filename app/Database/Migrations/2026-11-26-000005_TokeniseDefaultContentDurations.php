<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Default FAQ/terms texts hard-coded "4 ساعات" and the shop address/hours. Rewrite them to the
 * dynamic tokens ({{lock_duration}}, {{shop_address}}, {{working_hours}}) ONLY while the
 * row still holds the unmodified default; texts the shop edited are left alone.
 */
class TokeniseDefaultContentDurations extends Migration
{
    private const REWRITES = [
        ['faq_items', 'answer', 'عند تقديم الطلب، يتم حجز الجهاز وتثبيت سعره بـ ج.س لمدة 4 ساعات، مما يحميك من أي تقلبات في سعر الصرف أثناء إتمام التحويل عبر تطبيق بنكك.', 'عند تقديم الطلب، يتم حجز الجهاز وتثبيت سعره بـ ج.س لمدة {{lock_duration}}، مما يحميك من أي تقلبات في سعر الصرف أثناء إتمام التحويل عبر تطبيق بنكك.'],
        ['faq_items', 'answer', 'فرعنا في مول الواحة، الشهداء، أم درمان. نستقبلكم يومياً من الساعة 10 صباحاً حتى 10 مساءً، عدا يوم الجمعة.', 'عنوان المحل: {{shop_address}}. أوقات العمل: {{working_hours}}.'],
        ['pages', 'body', 'باستخدامك لمنصة ويكس تكنولوجي، فإنك توافق على الشروط والأحكام الآتية:\\n\\n1. تثبيت السعر سارٍ لمدة 4 ساعات من لحظة إنشاء الطلب، ويجب رفع إشعار التحويل البنكي قبل انقضاء هذه المهلة.\\n2. يتم تأكيد الطلب بعد التحقق من وصول المبلغ كاملاً في حساب المتجر.\\n3. يحتفظ المحل بحق إلغاء الطلب في حال عدم إتمام التحويل خلال المهلة المحددة.\\n\\n(ملاحظة للإدارة: هذه مسودة أولية يُراجعها صاحب المحل).', 'باستخدامك لمنصة ويكس تكنولوجي، فإنك توافق على الشروط والأحكام الآتية:\\n\\n1. تثبيت السعر سارٍ لمدة {{lock_duration}} من لحظة إنشاء الطلب، ويجب رفع إشعار التحويل البنكي قبل انقضاء هذه المهلة.\\n2. يتم تأكيد الطلب بعد التحقق من وصول المبلغ كاملاً في حساب المتجر.\\n3. يحتفظ المحل بحق إلغاء الطلب في حال عدم إتمام التحويل خلال المهلة المحددة.\\n\\n(ملاحظة للإدارة: هذه مسودة أولية يُراجعها صاحب المحل).'],
    ];

    public function up()
    {
        foreach (self::REWRITES as [$table, $col, $old, $new]) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->where($col, $old)->update([$col => $new]);
            }
        }
    }

    public function down()
    {
        foreach (self::REWRITES as [$table, $col, $old, $new]) {
            if ($this->db->tableExists($table)) {
                $this->db->table($table)->where($col, $new)->update([$col => $old]);
            }
        }
    }
}
