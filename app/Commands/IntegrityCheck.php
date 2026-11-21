<?php

namespace App\Commands;

use App\Services\IntegrityService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class IntegrityCheck extends BaseCommand
{
    protected $group       = 'Integrity';
    protected $name        = 'integrity:check';
    protected $description = 'Checks database and filesystem consistency for orders, proofs, and events.';
    protected $usage       = 'integrity:check';

    public function run(array $params)
    {
        CLI::write('=== فحص اتساق بيانات الطلبات وإشعارات الدفع (Integrity Check) ===', 'yellow');

        $integrityService = new IntegrityService();
        $report = $integrityService->checkIntegrity();

        $totalViolations = $report['total_violations'];

        if ($totalViolations === 0) {
            CLI::write('✅ لم يتم العثور على أي مخالفات. جميع البيانات متسقة 100%.', 'green');
            return 0;
        }

        CLI::error("❌ تم العثور على {$totalViolations} مخالفة/حالة عدم اتساق في البيانات:");
        CLI::newLine();

        $vMap = [
            'payment_review_without_pending_proof' => 'طلبات بحالة مراجعة الدفع بدون إشعار معلق:',
            'pending_proof_not_in_payment_review'  => 'إشعارات معلقة لطلبات ليست في حالة مراجعة الدفع:',
            'proof_file_missing'                  => 'إشعارات دفع مفقودة الملفات من القرص:',
            'confirmed_without_approved_proof'     => 'طلبات مؤكدة بدون إشعار تحويل معتمد:',
            'approved_proof_unconfirmed_order'     => 'إشعارات معتمدة لطلبات غير مؤكدة:',
            'pending_payment_past_lock'            => 'طلبات انتظار الدفع المنتهية صلاحية حجز السعر:',
            'order_events_inconsistent'            => 'سجلات تتبع غير متطابقة مع حالة الطلب الحالية:',
            'stock_mismatches'                     => 'فروقات في المخزون للطلبات الملغية:',
        ];

        foreach ($report['violations'] as $category => $items) {
            if (empty($items)) {
                continue;
            }

            $catTitle = $vMap[$category] ?? $category;
            CLI::write("  ▶ {$catTitle} (" . count($items) . ")", 'red');
            foreach ($items as $it) {
                CLI::write("    - " . ($it['message'] ?? json_encode($it, JSON_UNESCAPED_UNICODE)), 'white');
            }
            CLI::newLine();
        }

        return 1;
    }
}
