#!/data/data/com.termux/files/usr/bin/bash
set -e

DB_PATH="/data/data/com.termux/files/home/projects/wex-api/writable/database/wex.sqlite"

if [ ! -f "$DB_PATH" ]; then
  echo "FAIL: SQLite database not found at ${DB_PATH}"
  exit 1
fi

echo "=== Database Content Audit for Forbidden Claims & Sample Data ==="
echo "Database: ${DB_PATH}"

PATTERN="ضمان|فحص|معاينة|اختبار|تجربة|مختارة بعناية|استبدال|استرجاع|معتمد|وكيل|رسمي|مضمون|2849102|WEX-DBG|sample"

FOUND_COUNT=0

check_table() {
  local table="$1"
  local columns="$2"
  local select_cols="$3"

  local where_clause=""
  IFS=',' read -ra COLS <<< "$columns"
  for col in "${COLS[@]}"; do
    col=$(echo "$col" | tr -d ' ')
    if [ -n "$where_clause" ]; then
      where_clause="${where_clause} OR "
    fi
    # Use SQLite LIKE for each word in pattern or REGEXP if available
    where_clause="${where_clause} (${col} LIKE '%ضمان%' OR ${col} LIKE '%فحص%' OR ${col} LIKE '%معاينة%' OR ${col} LIKE '%اختبار%' OR ${col} LIKE '%تجربة%' OR ${col} LIKE '%مختارة بعناية%' OR ${col} LIKE '%استبدال%' OR ${col} LIKE '%استرجاع%' OR ${col} LIKE '%معتمد%' OR ${col} LIKE '%وكيل%' OR ${col} LIKE '%رسمي%' OR ${col} LIKE '%مضمون%' OR ${col} LIKE '%2849102%' OR ${col} LIKE '%WEX-DBG%' OR ${col} LIKE '%sample%')"
  done

  local query="SELECT '${table}' as tbl, id, ${select_cols} FROM ${table} WHERE ${where_clause};"
  if [ "$table" = "settings" ]; then
    query="SELECT '${table}' as tbl, key, value FROM settings WHERE (key LIKE '%ضمان%' OR key LIKE '%فحص%' OR key LIKE '%معاينة%' OR key LIKE '%اختبار%' OR key LIKE '%تجربة%' OR key LIKE '%مختارة بعناية%' OR key LIKE '%استبدال%' OR key LIKE '%استرجاع%' OR key LIKE '%معتمد%' OR key LIKE '%وكيل%' OR key LIKE '%رسمي%' OR key LIKE '%مضمون%' OR key LIKE '%2849102%' OR key LIKE '%WEX-DBG%' OR key LIKE '%sample%' OR value LIKE '%ضمان%' OR value LIKE '%فحص%' OR value LIKE '%معاينة%' OR value LIKE '%اختبار%' OR value LIKE '%تجربة%' OR value LIKE '%مختارة بعناية%' OR value LIKE '%استبدال%' OR value LIKE '%استرجاع%' OR value LIKE '%معتمد%' OR value LIKE '%وكيل%' OR value LIKE '%رسمي%' OR value LIKE '%مضمون%' OR value LIKE '%2849102%' OR value LIKE '%WEX-DBG%' OR value LIKE '%sample%');"
  fi

  local res
  res=$(sqlite3 "$DB_PATH" "$query" 2>/dev/null || true)
  if [ -n "$res" ]; then
    echo "⚠️ Table [${table}] contains forbidden claims or sample values:"
    echo "$res"
    local count
    count=$(echo "$res" | wc -l)
    FOUND_COUNT=$((FOUND_COUNT + count))
  fi
}

check_table "hero_slides" "title, subtitle" "title, subtitle"
check_table "pages" "title, body" "title, slug"
check_table "faq_items" "question, answer" "question"
check_table "settings" "key, value" "key, value"
check_table "products" "name, description, specs" "name"
check_table "payment_accounts" "bank_name, account_number, account_name" "bank_name, account_number"
check_table "announcements" "title, body" "title"

# Also check for WEX-DBG orders
DBG_ORDERS=$(sqlite3 "$DB_PATH" "SELECT 'orders', id, order_number FROM orders WHERE order_number LIKE 'WEX-DBG-%';" 2>/dev/null || true)
if [ -n "$DBG_ORDERS" ]; then
  echo "⚠️ Table [orders] contains debug test orders (WEX-DBG-%):"
  echo "$DBG_ORDERS"
  count=$(echo "$DBG_ORDERS" | wc -l)
  FOUND_COUNT=$((FOUND_COUNT + count))
fi

echo ""
if [ "$FOUND_COUNT" -gt 0 ]; then
  echo "❌ FAIL: DB content audit found ${FOUND_COUNT} violating row(s)."
  exit 1
else
  echo "✅ PASS: Zero forbidden claims or placeholder values found in database."
  exit 0
fi
