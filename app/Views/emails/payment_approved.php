<?= $this->extend('emails/layout') ?>

<?= $this->section('content') ?>
<h2 style="color: #2E7D32; margin-top: 0; font-size: 18px;">تم تأكيد دفع الطلب بنجاح</h2>
<p>مرحباً <?= esc($user_name ?? 'عميلنا العزيز') ?>،</p>
<p>يسرنا إبلاغك بأنه تمت مطابقة التحويل البنكي وتأكيد طلبك رقم <strong><?= esc($order_number) ?></strong> بنجاح.</p>

<p>يقوم فريق المتجر حالياً بتجهيز طلبك وتغليفه للاستلام أو التوصيل.</p>

<table class="info-table">
    <tr>
        <td class="label">رقم الطلب:</td>
        <td class="value"><?= esc($order_number) ?></td>
    </tr>
    <tr>
        <td class="label">المبلغ المدفوع:</td>
        <td class="value"><?= number_format($total_sdg) ?> ج.س</td>
    </tr>
    <tr>
        <td class="label">نوع الاستلام:</td>
        <td class="value"><?= esc($delivery_type === 'pickup' ? 'استلام من المحل (أم درمان)' : 'توصيل عبر المندوب') ?></td>
    </tr>
</table>
<?= $this->endSection() ?>
