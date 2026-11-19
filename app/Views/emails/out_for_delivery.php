<?= $this->extend('emails/layout') ?>

<?= $this->section('content') ?>
<h2 style="color: #0B3FBF; margin-top: 0; font-size: 18px;">طلبك خرج مع مندوب التوصيل</h2>
<p>مرحباً <?= esc($user_name ?? 'عميلنا العزيز') ?>،</p>
<p>يسعدنا إبلاغك بأن طلبك رقم <strong><?= esc($order_number) ?></strong> في طريقه إليك الآن مع مندوب التوصيل.</p>

<table class="info-table">
    <tr>
        <td class="label">اسم المندوب:</td>
        <td class="value"><?= esc($courier_name ?? 'مندوب ويكس تكنولوجي') ?></td>
    </tr>
    <?php if (!empty($courier_phone)): ?>
    <tr>
        <td class="label">هاتف المندوب:</td>
        <td class="value"><a href="tel:<?= esc($courier_phone) ?>"><?= esc($courier_phone) ?></a></td>
    </tr>
    <?php endif; ?>
    <tr>
        <td class="label">عنوان التوصيل:</td>
        <td class="value"><?= esc($delivery_address ?? 'العنوان المسجل في الطلب') ?></td>
    </tr>
</table>

<p style="font-size: 13px; color: #555;">سيتواصل معك المندوب هاتفياً لتنسيق وقت ومكان الاستلام المحدد.</p>
<?= $this->endSection() ?>
