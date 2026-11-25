# Regenerating docs/SYSTEM_MAP.md

Static, no browser. From the wex-api root, with wex-web checked out at ../wex-web:

```sh
export S=$(mktemp -d)
(cd ../wex-web && node scripts/contract-check.js --json) > "$S/contract.json"
php spark migrate --all                                  # dev DB must be fully migrated
php scripts/system-map/schema.php writable/database/wex.sqlite > "$S/schema.md"
python3 scripts/system-map/genmap.py
```
