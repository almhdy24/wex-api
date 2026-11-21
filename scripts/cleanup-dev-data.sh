#!/data/data/com.termux/files/usr/bin/bash
set -e

# Refuse to run in production
CURRENT_ENV="${CI_ENVIRONMENT:-${ENVIRONMENT:-development}}"
if [ "$CURRENT_ENV" = "production" ]; then
  echo "❌ ERROR: cleanup-dev-data.sh refuses to run when CI_ENVIRONMENT=production!" >&2
  exit 1
fi

API_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DB_PATH="${API_DIR}/writable/database/wex.sqlite"
UPLOADS_DIR="${API_DIR}/writable/uploads"

if [ ! -f "$DB_PATH" ]; then
  echo "⚠️ SQLite database not found at ${DB_PATH}. Nothing to clean."
  exit 0
fi

echo "=== Cleaning Up Dev and Debug Test Data ==="
echo "Target DB: ${DB_PATH}"

# 1. Find and delete physical proof files for WEX-DBG-% orders
PROOF_FILES=$(sqlite3 "$DB_PATH" "SELECT file_path FROM payment_proofs WHERE order_id IN (SELECT id FROM orders WHERE order_number LIKE 'WEX-DBG-%');" 2>/dev/null || true)
if [ -n "$PROOF_FILES" ]; then
  echo "Deleting physical proof files for WEX-DBG-% orders..."
  while IFS= read -r fpath; do
    [ -z "$fpath" ] && continue
    FULL_PATH="${API_DIR}/writable/${fpath}"
    if [ -f "$FULL_PATH" ]; then
      rm -f "$FULL_PATH"
      echo "  Removed: $FULL_PATH"
    fi
  done <<< "$PROOF_FILES"
fi

# 2. Delete WEX-DBG-% orders and dependent rows
echo "Deleting WEX-DBG-% order records..."
sqlite3 "$DB_PATH" << 'EOF'
DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE order_number LIKE 'WEX-DBG-%');
DELETE FROM order_events WHERE order_id IN (SELECT id FROM orders WHERE order_number LIKE 'WEX-DBG-%');
DELETE FROM payment_proofs WHERE order_id IN (SELECT id FROM orders WHERE order_number LIKE 'WEX-DBG-%');
DELETE FROM notifications WHERE order_id IN (SELECT id FROM orders WHERE order_number LIKE 'WEX-DBG-%');
DELETE FROM orders WHERE order_number LIKE 'WEX-DBG-%';
EOF

# 3. Clean test users created by agents (excluding admin@wextechnology.com and real users)
echo "Cleaning test users created by automated test agents..."
TEST_USER_PROOF_FILES=$(sqlite3 "$DB_PATH" "SELECT file_path FROM payment_proofs WHERE order_id IN (SELECT id FROM orders WHERE user_id IN (SELECT id FROM users WHERE (email LIKE '%@example.com' OR email LIKE 'customer@test.com' OR email LIKE 'other@test.com' OR email LIKE 'testuser@%') AND email != 'admin@wextechnology.com'));" 2>/dev/null || true)
if [ -n "$TEST_USER_PROOF_FILES" ]; then
  while IFS= read -r fpath; do
    [ -z "$fpath" ] && continue
    FULL_PATH="${API_DIR}/writable/${fpath}"
    if [ -f "$FULL_PATH" ]; then
      rm -f "$FULL_PATH"
    fi
  done <<< "$TEST_USER_PROOF_FILES"
fi

sqlite3 "$DB_PATH" << 'EOF'
DELETE FROM order_items WHERE order_id IN (SELECT id FROM orders WHERE user_id IN (SELECT id FROM users WHERE (email LIKE '%@example.com' OR email LIKE 'customer@test.com' OR email LIKE 'other@test.com' OR email LIKE 'testuser@%') AND email != 'admin@wextechnology.com'));
DELETE FROM order_events WHERE order_id IN (SELECT id FROM orders WHERE user_id IN (SELECT id FROM users WHERE (email LIKE '%@example.com' OR email LIKE 'customer@test.com' OR email LIKE 'other@test.com' OR email LIKE 'testuser@%') AND email != 'admin@wextechnology.com'));
DELETE FROM payment_proofs WHERE order_id IN (SELECT id FROM orders WHERE user_id IN (SELECT id FROM users WHERE (email LIKE '%@example.com' OR email LIKE 'customer@test.com' OR email LIKE 'other@test.com' OR email LIKE 'testuser@%') AND email != 'admin@wextechnology.com'));
DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE (email LIKE '%@example.com' OR email LIKE 'customer@test.com' OR email LIKE 'other@test.com' OR email LIKE 'testuser@%') AND email != 'admin@wextechnology.com');
DELETE FROM orders WHERE user_id IN (SELECT id FROM users WHERE (email LIKE '%@example.com' OR email LIKE 'customer@test.com' OR email LIKE 'other@test.com' OR email LIKE 'testuser@%') AND email != 'admin@wextechnology.com');
DELETE FROM otp_codes WHERE email IN (SELECT email FROM users WHERE (email LIKE '%@example.com' OR email LIKE 'customer@test.com' OR email LIKE 'other@test.com' OR email LIKE 'testuser@%') AND email != 'admin@wextechnology.com');
DELETE FROM refresh_tokens WHERE user_id IN (SELECT id FROM users WHERE (email LIKE '%@example.com' OR email LIKE 'customer@test.com' OR email LIKE 'other@test.com' OR email LIKE 'testuser@%') AND email != 'admin@wextechnology.com');
DELETE FROM users WHERE (email LIKE '%@example.com' OR email LIKE 'customer@test.com' OR email LIKE 'other@test.com' OR email LIKE 'testuser@%') AND email != 'admin@wextechnology.com';
EOF

# 4. Clean claims and placeholder values in content tables
echo "Sanitizing content tables (hero_slides, pages, settings, payment_accounts)..."
NOW=$(date -u +"%Y-%m-%d %H:%M:%S")

sqlite3 "$DB_PATH" << EOF
-- Hero slides factual texts
UPDATE hero_slides SET
  title = 'أجهزة لابتوب لأعمالك ودراستك',
  subtitle = 'أجهزة مستوردة متوفرة في فرعنا بأم درمان'
WHERE id = 1;

UPDATE hero_slides SET
  title = 'تثبيت السعر والدفع عبر بنكك',
  subtitle = 'استقرار السعر من لحظة الطلب وحتى إتمام التحويل البنكي خلال المهلة المحددة'
WHERE id = 2;

-- Pages factual texts
UPDATE pages SET
  title = 'سياسة الاستلام والتسليم',
  body = 'نحرص على الشفافية:\n\n1. الأجهزة المعروضة مستوردة.\n2. يتأكد المشتري من مطابقة الجهاز ومواصفاته الفنية عند الاستلام.\n3. يتم استلام الطلبات من مقرنا أو عبر مندوب التوصيل.\n\n(ملاحظة للإدارة: هذه مسودة أولية يُراجعها صاحب المحل).'
WHERE slug = 'returns';

UPDATE pages SET
  body = 'ويكس تكنولوجي - متجر لأجهزة اللابتوب ومستلزمات الحواسيب في أم درمان والسودان.\n\nنقدم أجهزة لابتوب لتناسب احتياجات المهندسين والطلاب ورواد الأعمال، مع وضوح في الأسعار بـ ج.س ونظام تثبيت السعر.\n\nمقرنا: مول الواحة، الشهداء، أم درمان.'
WHERE slug = 'about';

-- Clean placeholder bank data from settings
DELETE FROM settings WHERE key = 'bank_account_number' AND value = '2849102';
DELETE FROM settings WHERE key = 'bank_name' AND value LIKE '%تجريبي%';
DELETE FROM settings WHERE key = 'bank_account_name' AND value LIKE '%تجريبي%';

-- Clean placeholder bank data from payment_accounts
DELETE FROM payment_accounts WHERE account_number = '2849102';

-- Ensure a valid active payment account exists for local dev
INSERT INTO payment_accounts (bank_name, account_name, account_number, branch, notes, is_active, sort_order, created_at, updated_at)
SELECT 'بنك الخرطوم (تطبيق بنكك)', 'Wex Technology - متجر التقنية', '100200300', 'الفرع الرئيسي - أم درمان', 'يرجى كتابة رقم الطلب في خانة الملاحظات عند التحويل عبر تطبيق بنكك.', 1, 1, '${NOW}', '${NOW}'
WHERE NOT EXISTS (SELECT 1 FROM payment_accounts WHERE is_active = 1);
EOF

echo "✅ Dev data cleanup completed successfully."
