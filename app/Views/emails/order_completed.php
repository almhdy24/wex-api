<?= $this->extend('emails/layout') ?>

<?= $this->section('content') ?>
<h2 style="color: #2E7D32; margin-top: 0; font-size: 18px;">تم تسليم طلبك بنجاح</h2>
<p>مرحباً <?= esc($user_name ?? 'عميلنا العزيز') ?>،</p>
<p>تم تسليم طلبك رقم <strong><?= esc($order_number) ?></strong> بنجاح. شكراً لثقتك في ويكس تكنولوجي.</p>

<p>جميع أجهزتنا تخضع لضمان الفحص والتجربة الموضح في تفاصيل الفاتورة. إذا كانت لديك أي استفسارات أو ملاحظات، يمكنك التواصل معنا في أي وقت.</p>

<table class="info-table">
    <tr>
        <td class="label">رقم الطلب:</td>
        <td class="value"><?= esc($order_number) ?></td>
    </tr>
    <tr>
        <td class="label">إجمالي القيمة:</td>
        <td class="value"><?= number_format($total_sdg) ?> جنيه سوداني</td>
    </tr>
</table>
<?= $this->endSection() ?>
