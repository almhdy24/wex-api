<?= $this->extend('emails/layout') ?>

<?= $this->section('content') ?>
<h2 style="color: #0B3FBF; margin-top: 0; font-size: 18px;">تنبيه أمني: تم تعديل بيانات الدفع البنكية</h2>
<p>مرحباً،</p>
<p>نحيطكم علماً بأنه قد تم تنفيذ إجراء إداري لتعديل بيانات حسابات الدفع البنكية في متجر ويكس تكنولوجي.</p>

<table class="info-table">
    <tr>
        <td class="label">الإجراء المتخذ:</td>
        <td class="value"><strong><?= esc($action_title ?? 'تعديل بيانات الدفع') ?></strong></td>
    </tr>
    <?php if (!empty($bank_name)): ?>
    <tr>
        <td class="label">اسم البنك:</td>
        <td class="value"><?= esc($bank_name) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($account_name)): ?>
    <tr>
        <td class="label">صاحب الحساب:</td>
        <td class="value"><?= esc($account_name) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($account_number)): ?>
    <tr>
        <td class="label">رقم الحساب:</td>
        <td class="value"><?= esc($account_number) ?></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td class="label">معرف المشرف:</td>
        <td class="value">المستخدم رقم #<?= esc($admin_user_id ?? 'غير محدد') ?></td>
    </tr>
    <tr>
        <td class="label">وقت التعديل:</td>
        <td class="value"><?= esc($changed_at ?? date('Y-m-d H:i:s')) ?> (توقيت جرينتش)</td>
    </tr>
</table>

<p style="margin-top: 16px; color: #555; font-size: 13px;">
إذا لم تكن أنت من أجرى هذا التعديل أو لم يتم بتنسيق مسبق، يرجى الدخول فوراً إلى لوحة التحكم ومراجعة سجل التدقيق وحسابات الدفع وتغيير كلمات المرور.
</p>
<?= $this->endSection() ?>
