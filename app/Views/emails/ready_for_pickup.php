<?= $this->extend('emails/layout') ?>

<?= $this->section('content') ?>
<h2 style="color: #0B3FBF; margin-top: 0; font-size: 18px;">طلبك جاهز للاستلام من المحل</h2>
<p>مرحباً <?= esc($user_name ?? 'عميلنا العزيز') ?>،</p>
<p>يسرنا إبلاغك بأن طلبك رقم <strong><?= esc($order_number) ?></strong> جاهز الآن للاستلام من فرعنا بأم درمان.</p>

<table class="info-table">
    <tr>
        <td class="label">عنوان المحل:</td>
        <td class="value">مول الواحة، الشهداء، أم درمان</td>
    </tr>
    <tr>
        <td class="label">ساعات العمل:</td>
        <td class="value">من 10 صباحاً حتى 10 مساءً (عدا الجمعة)</td>
    </tr>
    <?php if (!empty($shop_phone)): // never show a placeholder number to customers ?>
    <tr>
        <td class="label">هاتف المحل:</td>
        <td class="value" dir="ltr"><?= esc($shop_phone) ?></td>
    </tr>
    <?php endif; ?>
</table>

<p style="font-size: 13px; color: #555;">يرجى إبراز رقم الطلب (<?= esc($order_number) ?>) لموظف المبيعات عند الحضور.</p>
<?= $this->endSection() ?>
