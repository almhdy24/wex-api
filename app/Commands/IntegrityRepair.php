<?php

namespace App\Commands;

use App\Services\IntegrityService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class IntegrityRepair extends BaseCommand
{
    protected $group       = 'Integrity';
    protected $name        = 'integrity:repair';
    protected $description = 'Safely repairs order and payment proof integrity violations.';
    protected $usage       = 'integrity:repair [--apply] [--force]';
    protected $options     = [
        '--apply' => 'Execute changes (defaults to dry-run preview).',
        '--force' => 'Permits execution in production environment.',
    ];

    public function run(array $params)
    {
        CLI::write('=== معالجة وإصلاح اتساق البيانات (Integrity Repair) ===', 'yellow');

        $isApply = in_array('--apply', $params, true) || CLI::getOption('apply');
        $isForce = in_array('--force', $params, true) || CLI::getOption('force');

        $env = ENVIRONMENT ?? 'development';
        if (($env === 'production' || env('CI_ENVIRONMENT') === 'production') && !$isForce) {
            CLI::error('❌ تنبيه أمان: يُمنع تشغيل الإصلاح في بيئة الإنتاج بدون تمرير المعامل --force');
            return 1;
        }

        if (!$isApply) {
            CLI::write('ℹ️ وضع المعاينة (Dry Run) مفعل: لن يتم تعديل قاعدة البيانات. استخدم --apply للتطبيق الفعلي.', 'cyan');
        } else {
            CLI::write('⚡ وضع التطبيق الفعلي (--apply): جاري تطبيق الإصلاحات الآمنة...', 'purple');
        }

        $integrityService = new IntegrityService();

        try {
            $result = $integrityService->repairIntegrity(dryRun: !$isApply, force: $isForce);
        } catch (\Throwable $e) {
            CLI::error('❌ حدث خطأ أثناء المعالجة: ' . $e->getMessage());
            return 1;
        }

        $actionsCount = $result['actions_count'];
        if ($actionsCount === 0) {
            CLI::write('✅ لا توجد مخالفات قابلة للإصلاح التلقائي حالياً.', 'green');
            return 0;
        }

        CLI::write("تم حصر {$actionsCount} إجراء إصلاح:", 'yellow');
        foreach ($result['actions'] as $act) {
            $prefix = $isApply ? '✔ [تَمَّ]' : '👁 [معاينة]';
            CLI::write("  {$prefix} {$act['action']}", $isApply ? 'green' : 'white');
        }

        if (!$isApply) {
            CLI::newLine();
            CLI::write('لتطبيق هذه الإجراءات، أعد تشغيل الأمر مع إضافة --apply: php spark integrity:repair --apply', 'cyan');
        } else {
            CLI::newLine();
            CLI::write('✅ تم تطبيق كافة الإصلاحات بنجاح.', 'green');
        }

        return 0;
    }
}
