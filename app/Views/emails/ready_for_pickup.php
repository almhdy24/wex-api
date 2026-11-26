<?= $this->extend('emails/layout') ?>

<?= $this->section('content') ?>
<h2 style="color: #0B3FBF; margin-top: 0; font-size: 18px;">طلبك جاهز للاستلام من المحل</h2>
<p>مرحباً <?= esc($user_name ?? 'عميلنا العزيز') ?>،</p>
<p>يسرنا إبلاغك بأن طلبك رقم <strong><?= esc($order_number) ?></strong> جاهز الآن للاستلام من المحل.</p>

<table class="info-table">
    <?php if (!empty($shop_address)): // from admin settings; never a hard-coded place ?>
    <tr>
        <td class="label">عنوان المحل:</td>
        <td class="value"><?= esc($shop_address) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($working_hours)): ?>
    <tr>
        <td class="label">ساعات العمل:</td>
        <td class="value"><?= esc($working_hours) ?></td>
    </tr>
    <?php endif; ?>
    <?php if (!empty($shop_phone)): // never show a placeholder number to customers ?>
    <tr>
        <td class="label">هاتف المحل:</td>
        <td class="value" dir="ltr"><?= esc($shop_phone) ?></td>
    </tr>
    <?php endif; ?>
</table>

<p style="font-size: 13px; color: #555;">يرجى إبراز رقم الطلب (<?= esc($order_number) ?>) لموظف المبيعات عند الحضور.</p>
<?= $this->endSection() ?>
