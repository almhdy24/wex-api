<?= $this->extend('emails/layout') ?>

<?= $this->section('content') ?>
<h2 style="color: #D32F2F; margin-top: 0; font-size: 18px;">تعذر تأكيد إشعار التحويل</h2>
<p>مرحباً <?= esc($user_name ?? 'عميلنا العزيز') ?>،</p>
<p>نعتذر عن إبلاغك بأنه تعذر قبول إشعار التحويل البنكي لطلبك رقم <strong><?= esc($order_number) ?></strong>.</p>

<?php if (!empty($reason)): ?>
<div style="background-color: #FFEBEE; border: 1px solid #FFCDD2; border-radius: 6px; padding: 14px; margin: 16px 0; color: #C62828;">
    <strong>سبب الرفض:</strong> <?= esc($reason) ?>
</div>
<?php endif; ?>

<p>إذا كانت فترة حجز السعر لا تزال سارية، يمكنك تسجيل الدخول إلى حسابك ورفع إشعار تحويل صحيح قبل انتهاء الوقت.</p>
<?= $this->endSection() ?>
