<?= $this->extend('emails/layout') ?>

<?= $this->section('content') ?>
<h2 style="color: #D32F2F; margin-top: 0; font-size: 18px;">تنبيه: اقتراب انتهاء فترة حجز السعر</h2>
<p>مرحباً <?= esc($user_name ?? 'عميلنا العزيز') ?>،</p>
<p>نود تذكيرك بأن فترة حجز السعر لطلبك رقم <strong><?= esc($order_number) ?></strong> ستنتهي خلال <strong>30 دقيقة</strong> (عند <?= esc($lock_expires_at) ?>).</p>

<p>لتجنب إلغاء الطلب وإعادة المنتجات إلى المخزون، يرجى إتمام التحويل البنكي ورفع صورة الإشعار عبر التطبيق قبل انتهاء الوقت المحدد.</p>

<table class="info-table">
    <tr>
        <td class="label">رقم الطلب:</td>
        <td class="value"><?= esc($order_number) ?></td>
    </tr>
    <tr>
        <td class="label">المبلغ المطلوب:</td>
        <td class="value" style="color: #0B3FBF; font-weight: bold;"><?= number_format($total_sdg) ?> جنيه سوداني</td>
    </tr>
    <tr>
        <td class="label">رقم حساب بنكك:</td>
        <td class="value" style="font-size: 16px;"><?= esc($bank_account_number ?? '2849102') ?></td>
    </tr>
</table>
<?= $this->endSection() ?>
