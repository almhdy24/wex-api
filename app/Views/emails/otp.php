<?php
$content = '
<h2 style="color: #061A55; margin-top: 0; font-size: 18px;">رمز التحقق لتسجيل الدخول</h2>
<p>مرحباً بك في منصة ويكس تكنولوجي.</p>
<p>استخدم رمز التحقق التالي لإتمام تسجيل الدخول إلى حسابك:</p>

<div class="code-box">' . esc($code) . '</div>

<p style="font-size: 13px; color: #555555;">
الرمز صالح لمدة <strong>10 دقائق</strong> فقط، وللاستخدام لمرة واحدة.
</p>
<p style="font-size: 13px; color: #777777;">
إذا لم تكن قد طلبت هذا الرمز، فيمكنك تجاهل هذا البريد بأمان ولن يتمكن أحد من الدخول لحسابك دون هذا الرمز.
</p>
';

echo view('emails/layout', [
    'title'   => 'رمز التحقق - ويكس تكنولوجي',
    'content' => $content,
]);
