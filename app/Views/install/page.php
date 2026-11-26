<?php
/** @var string $step token|check|done|locked */
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?><!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>تثبيت المتجر</title>
<style>
  * { box-sizing: border-box; }
  body { margin: 0; background: #EAF3FF; color: #061A55; font-family: Tajawal, "Segoe UI", Tahoma, Arial, sans-serif; line-height: 1.7; }
  main { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }
  h1 { font-size: 22px; margin: 0 0 4px; }
  h2 { font-size: 17px; margin: 24px 0 8px; }
  .card { background: #fff; border: 1px solid #D5E2F7; border-radius: 14px; padding: 16px; margin-top: 16px; }
  .muted { color: #4B5B85; font-size: 14px; }
  .err { background: #FDECEC; border: 1px solid #F5B5B5; color: #8A1C1C; border-radius: 10px; padding: 10px 12px; margin-top: 12px; }
  .warn { background: #FFF8EB; border: 2px solid #D97706; border-radius: 12px; padding: 14px; margin-top: 16px; font-weight: 700; }
  .ok { color: #15803D; font-weight: 700; } .no { color: #B91C1C; font-weight: 700; } .opt { color: #B45309; font-weight: 700; }
  table { width: 100%; border-collapse: collapse; font-size: 14px; }
  td { padding: 8px 4px; border-bottom: 1px solid #EAF3FF; vertical-align: top; }
  label { display: block; font-weight: 700; margin: 12px 0 4px; }
  input[type=text], input[type=email], input[type=password] { width: 100%; min-height: 46px; padding: 8px 12px; border: 1px solid #D5E2F7; border-radius: 10px; font: inherit; }
  .check { display: flex; gap: 8px; align-items: center; margin-top: 12px; font-weight: 400; }
  button { margin-top: 16px; min-height: 48px; padding: 0 20px; border: 0; border-radius: 10px; background: #0B3FBF; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }
  button.secondary { background: #fff; color: #0B3FBF; border: 1px solid #0B3FBF; }
  code { direction: ltr; unicode-bidi: isolate; background: #F4F8FE; padding: 2px 6px; border-radius: 6px; word-break: break-all; }
</style>
</head>
<body>
<main>
  <h1>تثبيت المتجر</h1>
  <p class="muted">يعمل هذا المعالج مرة واحدة فقط. احذف <code>INSTALL_TOKEN</code> من ملف <code>.env</code> بعد الانتهاء.</p>

  <?php if (!empty($error)): ?><div class="err" role="alert"><?= $e($error) ?></div><?php endif; ?>

  <?php if ($step === 'token'): ?>
    <form method="post" class="card" autocomplete="off">
      <label for="t">رمز التثبيت (INSTALL_TOKEN)</label>
      <input id="t" name="install_token" type="password" required minlength="32">
      <button type="submit">متابعة</button>
    </form>

  <?php elseif ($step === 'locked'): ?>
    <div class="warn">تم تثبيت المتجر مسبقاً. احذف السطر <code>INSTALL_TOKEN</code> من ملف <code>.env</code> الآن ليتوقف هذا الرابط نهائياً.</div>

  <?php elseif ($step === 'done'): ?>
    <div class="card">
      <h2 class="ok">تم التثبيت بنجاح</h2>
      <p>حساب المدير: <code><?= $e($result['admin_email']) ?></code>. سجّل الدخول من <code>/login</code> برمز التحقق الذي يصل إلى هذا البريد.</p>
      <p class="muted">استقبال الطلبات مغلق حتى تُكمل إعداد المتجر من لوحة الإدارة (بيانات التواصل، العنوان، ساعات العمل، حساب الدفع، سعر الصرف).</p>
    </div>
    <div class="warn">مهم: احذف السطر <code>INSTALL_TOKEN</code> من ملف <code>.env</code> الآن.</div>

  <?php else: /* check */ ?>
    <div class="card">
      <h2>فحص الاستضافة</h2>
      <table>
        <?php foreach ($requirements as $r): ?>
          <tr>
            <td><?= $e($r['label']) ?></td>
            <td><code><?= $e($r['detail']) ?></code></td>
            <td class="<?= $r['ok'] ? 'ok' : ($r['required'] ? 'no' : 'opt') ?>"><?= $r['ok'] ? 'سليم' : ($r['required'] ? 'مطلوب' : 'يُنصح به') ?></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td>الاتصال بقاعدة البيانات</td>
          <td><code><?= $e($database['detail']) ?></code></td>
          <td class="<?= $database['ok'] ? 'ok' : 'no' ?>"><?= $database['ok'] ? 'سليم' : 'مطلوب' ?></td>
        </tr>
      </table>
    </div>

    <div class="card">
      <h2>المفاتيح السرية في ملف .env</h2>
      <table>
        <?php foreach ($secrets as $k => $set): ?>
          <tr><td><code><?= $e($k) ?></code></td><td class="<?= $set ? 'ok' : 'no' ?>"><?= $set ? 'مضبوط' : 'غير مضبوط' ?></td></tr>
        <?php endforeach; ?>
      </table>
      <?php if (!empty($secrets_result)): ?>
        <?php if ($secrets_result['written']): ?>
          <p class="ok">تم توليد المفاتيح الناقصة وحفظها في ملف .env.</p>
        <?php else: ?>
          <div class="warn">تعذّرت الكتابة في ملف .env. انسخ هذه القيم إليه الآن (لن تظهر مرة أخرى):
            <?php foreach ($secrets_result['values'] as $k => $v): ?><div><code><?= $e($k) ?> = <?= $e($v) ?></code></div><?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
      <?php if (in_array(false, $secrets, true)): ?>
        <form method="post">
          <input type="hidden" name="install_token" value="<?= $e($token) ?>">
          <input type="hidden" name="action" value="secrets">
          <button type="submit" class="secondary">توليد المفاتيح الناقصة</button>
        </form>
      <?php endif; ?>
    </div>

    <form method="post" class="card" autocomplete="off">
      <h2>حساب المدير</h2>
      <p class="muted">لا توجد كلمات مرور: يدخل المدير برمز تحقق يصل إلى بريده، لذا يجب أن يكون إعداد البريد (SMTP) صحيحاً.</p>
      <input type="hidden" name="install_token" value="<?= $e($token) ?>">
      <input type="hidden" name="action" value="install">
      <label for="em">بريد المدير</label>
      <input id="em" name="admin_email" type="email" required>
      <label for="nm">اسم المدير</label>
      <input id="nm" name="admin_name" type="text" value="مدير المتجر">
      <label class="check"><input type="checkbox" name="sample_content" value="1"> إضافة محتوى تجريبي (أجهزة وماركات ومناطق توصيل للتجربة فقط)</label>
      <button type="submit">تثبيت المتجر</button>
    </form>
  <?php endif; ?>
</main>
</body>
</html>
