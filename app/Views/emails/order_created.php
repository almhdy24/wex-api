<?= $this->extend('emails/layout') ?>

<?= $this->section('content') ?>
<h2 style="color: #061A55; margin-top: 0; font-size: 18px;">تم استلام طلبك بنجاح</h2>
<p>مرحباً <?= esc($user_name ?? 'عميلنا العزيز') ?>،</p>
<p>تم استلام طلبك رقم <strong><?= esc($order_number) ?></strong> وتثبيت السعر بالجنيه السوداني حتى <strong><?= esc($lock_expires_at) ?></strong>.</p>

<table class="info-table">
    <tr>
        <td class="label">رقم الطلب:</td>
        <td class="value"><?= esc($order_number) ?></td>
    </tr>
    <tr>
        <td class="label">نوع الاستلام:</td>
        <td class="value"><?= esc($delivery_type === 'pickup' ? 'استلام من المحل (أم درمان)' : 'توصيل عبر المندوب') ?></td>
    </tr>
    <tr>
        <td class="label">المبلغ المطلوب تحويله:</td>
        <td class="value" style="color: #0B3FBF; font-size: 16px;"><?= number_format($total_sdg) ?> جنيه سوداني</td>
    </tr>
    <tr>
        <td class="label">انتهاء فترة حجز السعر:</td>
        <td class="value" style="color: #D32F2F;"><?= esc($lock_expires_at) ?></td>
    </tr>
</table>

<div style="background-color: #FFF8E1; border: 1px solid #FFE082; border-radius: 6px; padding: 16px; margin: 20px 0;">
    <h3 style="margin-top: 0; color: #E65100; font-size: 15px;">بيانات التحويل عبر تطبيق بنكك:</h3>
    <p style="margin: 4px 0;"><strong>اسم البنك:</strong> بنك الخرطوم (تطبيق بنكك)</p>
    <p style="margin: 4px 0;"><strong>اسم الحساب:</strong> <?= esc($bank_account_name ?? 'Wex Technology') ?></p>
    <p style="margin: 4px 0;"><strong>رقم الحساب:</strong> <span style="font-size: 16px; font-weight: bold; letter-spacing: 2px; color: #061A55;"><?= esc($bank_account_number ?? '2849102') ?></span></p>
    <p style="margin: 8px 0 0; font-size: 13px; color: #666;">يرجى كتابة رقم الطلب (<?= esc($order_number) ?>) في خانة الملاحظات عند التحويل، ثم رفع صورة الإشعار من التطبيق.</p>
</div>
<?= $this->endSection() ?>
