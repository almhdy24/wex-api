<?= $this->extend('emails/layout') ?>

<?= $this->section('content') ?>
<h2 style="color: #061A55; margin-top: 0; font-size: 18px;">تم استلام إشعار التحويل بنجاح</h2>
<p>مرحباً <?= esc($user_name ?? 'عميلنا العزيز') ?>،</p>
<p>تم استلام صورة إشعار التحويل البنكي لطلبك رقم <strong><?= esc($order_number) ?></strong> وجاري الآن مراجعته وتأكيده مع كشف حساب البنك من قبل فريق المتجر.</p>

<p>سنقوم بإشعارك فور اكتمال المراجعة وتأكيد الطلب.</p>

<table class="info-table">
    <tr>
        <td class="label">رقم الطلب:</td>
        <td class="value"><?= esc($order_number) ?></td>
    </tr>
    <tr>
        <td class="label">الحالة الحالية:</td>
        <td class="value" style="color: #E65100;">مراجعة التحويل البنكي</td>
    </tr>
</table>
<?= $this->endSection() ?>
