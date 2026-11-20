# Wex Technology - Go-Live & Operations Guide

دليل الإطلاق والتشغيل النهائي لنظام ويكس تكنولوجي (Wex Technology).

---

## 1. متطلبات الخادم والبيئة الإنتاجية
- **نظام التشغيل:** Linux (Ubuntu 22.04 LTS أو Debian 12 موصى به).
- **الخادم الويب:** Nginx مع دعم HTTP/2 و SSL/TLS (Let's Encrypt / Certbot).
- **مترجم PHP:** PHP 8.2 أو أحدث مع الامتدادات التالية:
  - `php-fpm`, `php-sqlite3` (أو `php-mysql`), `php-mbstring`, `php-intl`, `php-gd`, `php-curl`, `php-xml`.
- **الذاكرة:** حد أدنى 1GB RAM (2GB+ موصى به في بيئة الإنتاج).

---

## 2. إعداد النطاق وشهادة الأمان (Domain & HTTPS)
1. **ربط الـ DNS:**
   - السجل `A` لنطاق الـ API: `api.wextechnology.sd` -> IP السيرفر.
   - السجل `A` لنطاق المتجر واللوحة: `wextechnology.sd` (أو `app.wextechnology.sd`).
2. **إصدار شهادة SSL مجانية عبر Certbot:**
   ```bash
   sudo certbot --nginx -d api.wextechnology.sd -d wextechnology.sd
   ```

---

## 3. إعدادات البيئة الإنتاجية (.env)
تأكد من ضبط المتغيرات الآتية في `.env` للواجهة الخلفية:
```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://api.wextechnology.sd/'
app.forceGlobalSecureRequests = true

# إعداد البريد الإلكتروني (SMTP)
MAIL_DRIVER = smtp
SMTP_HOST = mail.wextechnology.sd
SMTP_PORT = 587
SMTP_USER = no-reply@wextechnology.sd
SMTP_PASS = 'StrongPasswordHere'
SMTP_CRYPTO = tls
MAIL_FROM_EMAIL = no-reply@wextechnology.sd
MAIL_FROM_NAME = 'Wex Technology - ويكس تكنولوجي'
SHOP_NOTIFY_EMAIL = owner@wextechnology.sd

# مفاتيح التحقق عبر Google OAuth
GOOGLE_CLIENT_ID = 'your-production-google-client-id.apps.googleusercontent.com'

# أسرار التشفير وJWT
JWT_SECRET = 'your-super-secret-jwt-key-min-64-characters'
ENCRYPTION_KEY = 'your-hex-32-byte-key'
```

---

## 4. الجدولة الزمنية والمهام الدورية (Cron Jobs)
أضف المهام الدورية الآتية إلى `crontab -e` لمستخدم الويب (`www-data`):
```cron
# 1. فحص وانتهاء صلاحية مهلة تثبيت الأسعار (كل دقيقة)
* * * * * cd /var/www/wex-api && php spark orders:check-locks > /dev/null 2>&1

# 2. إرسال رسائل البريد الصادرة من جدول outbox (كل دقيقة)
* * * * * cd /var/www/wex-api && php spark mail:process-outbox > /dev/null 2>&1

# 3. النسخ الاحتياطي التلقائي اليومي لقاعدة البيانات (الساعة 3:00 فجراً بتوقيت الخرطوم)
0 3 * * * /var/www/scripts/backup-db.sh > /dev/null 2>&1
```

---

## 5. النسخ الاحتياطي واستعادة البيانات (Backups)
سكربت نسخ احتياطي نموذجي `backup-db.sh`:
```bash
#!/usr/bin/env bash
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/wex"
mkdir -p "$BACKUP_DIR"

# قاعدة البيانات (SQLite / MySQL)
cp /var/www/wex-api/writable/database/wex.sqlite "$BACKUP_DIR/wex_$DATE.sqlite"
gzip -f "$BACKUP_DIR/wex_$DATE.sqlite"

# الصور المرفوعة
tar -czf "$BACKUP_DIR/uploads_$DATE.tar.gz" -C /var/www/wex-api/public/uploads .

# حذف النسخ الأقدم من 30 يوماً
find "$BACKUP_DIR" -type f -mtime +30 -delete
```

---

## 6. تجهيز المسؤول الأول (First Admin Setup)
بعد تشغيل التهجيرات الأولية:
```bash
php spark migrate --all
php spark admin:create --name "صاحب المتجر" --email "owner@wextechnology.sd" --phone "0912345678" --role "admin"
```

---

## 7. قائمة التحقق قبل فتح المتجر للجمهور (Pre-Launch Checklist)
- [ ] تهجير قاعدة البيانات بنجاح: `php spark migrate`.
- [ ] إدخال حساب بنكي حقيقي واحد على الأقل في شاشة "حسابات الدفع" وتفعيله وتأكيده.
- [ ] تحديث سعر صرف الدرهم لليوم في شاشة "سعر الصرف".
- [ ] التحقق من أرقام التواصل وروابط الواتساب وساعات العمل الحقيقية في إعدادات المتجر.
- [ ] مراجعة ونشر مسودات الصفحات الثابتة (سياسة الخصوصية، شروط الاستخدام، سياسة الاسترجاع، عن المحل).
- [ ] إجراء طلب تجريبي كامل (إنشاء طلب، التحقق من تثبيت السعر، رفع إشعار، اعتماد، تجهيز، وتسليم).
