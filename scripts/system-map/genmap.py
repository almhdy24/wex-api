import json,re,glob,os,subprocess,collections
API=os.path.abspath(os.path.join(os.path.dirname(__file__), '..', '..')); WEB=os.environ.get('WEX_WEB_DIR', os.path.join(API, '..', 'wex-web')); S=os.environ['S']
d=json.load(open(f'{S}/contract.json'))
# --- routes with filters
routes=[]; stack=[]
for line in open(f'{API}/app/Config/Routes.php'):
    g=re.search(r"\$routes->group\(\s*'([^']*)'(.*?)static function",line)
    if g:
        f=re.findall(r"'filter'\s*=>\s*(\[[^\]]*\]|'[^']*')",g.group(2) or '')
        fl=re.findall(r"'([^']+)'",f[0]) if f else []
        stack.append((g.group(1),fl)); continue
    if re.match(r"^\s*}\);",line): stack.pop(); continue
    r=re.search(r"\$routes->(get|post|put|patch|delete)\(\s*'([^']*)'\s*,\s*'([^']+)'(.*)",line)
    if not r: continue
    segs=[x.strip('/') for x,_ in stack]+[r.group(2).strip('/')]
    path='/'+'/'.join(s for s in segs if s)
    path=re.sub(r"\(:(segment|num|any)\)",":param",path).replace('/api/v1','',1) or '/'
    filters=[]
    for _,fl in stack: filters+=fl
    rf=re.findall(r"'filter'\s*=>\s*(\[[^\]]*\]|'[^']*')",r.group(4))
    if rf: filters+=re.findall(r"'([^']+)'",rf[0])
    filters=list(dict.fromkeys(filters))
    routes.append((r.group(1).upper(),path,', '.join(filters) or 'public',r.group(3).split('/')[0]))
callers=collections.defaultdict(list)
def norm(p): return p
def match(m,p,rm,rp):
    a=p.split('/'); b=rp.split('/')
    return m==rm and len(a)==len(b) and all(x==y or y==':param' or x==':param' for x,y in zip(a,b))
for c in d['calls']:
    best=None
    for (rm,rp,_,_) in routes:
        if match(c['method'],c['path'],rm,rp):
            lit=sum(1 for s in rp.split('/') if s!=':param')
            if not best or lit>best[0]: best=(lit,rm,rp)
    if best: callers[(best[1],best[2])].append(f"{c['file']}:{c['line']}")
out=[]
w=out.append
w('# Wex System Map\n')
w('_Generated 2026-11-25 from source by the deep-review pass (routes: `app/Config/Routes.php`; schema: PRAGMA introspection of a fully migrated SQLite DB; frontend calls: `wex-web/scripts/contract-check.js --json`). Regenerate rather than hand-edit._\n')
w('## Contents\n1. API routes\n2. Database schema\n3. Models (`$allowedFields`)\n4. Frontend API calls\n5. Settings keys\n6. State machines (order / payment / price lock)\n7. Environment variables\n8. Contract check status\n')
w(f'## 1. API routes ({len(routes)})\n\nBase path `/api/v1`. Global filters on every request: `cors` (required), `security`, `forcehttps`, `throttle:120,60,global`. `auth` = Bearer JWT (HS256, 15 min) + active-user check; `role:x,y` = user.role in list (both group and route `role:` filters run). Responses use the envelope `{{success, message, data, errors, meta}}`.\n')
w('| method | path | filters | handler | frontend callers |\n|---|---|---|---|---|')
for m,p,f,h in routes:
    cs=callers.get((m,p),[])
    w(f"| {m} | `{p}` | {f} | `{h}` | {', '.join(cs[:3])+(' …' if len(cs)>3 else '') if cs else '—'} |")
w('\n## 2. Database schema\n\nMoney is stored as integers (`*_aed`, `*_sdg`). Timestamps are written as UTC (`gmdate`). FKs are enforced on SQLite (`foreignKeys => true`) and MySQL.\n')
w(open(f'{S}/schema.md').read())
w('## 3. Models (`$allowedFields`)\n')
for mf in sorted(glob.glob(f'{API}/app/Models/*.php')):
    s=open(mf).read()
    t=re.search(r"\$table\s*=\s*'([^']+)'",s); af=re.search(r"\$allowedFields\s*=\s*\[(.*?)\]",s,re.S)
    soft='soft deletes' if re.search(r"useSoftDeletes\s*=\s*true",s) else ''
    fields=', '.join(re.findall(r"'([^']+)'",af.group(1))) if af else '—'
    w(f"- **{os.path.basename(mf)[:-4]}** (`{t.group(1) if t else '?'}`{', '+soft if soft else ''}): {fields}")
w(f"\n## 4. Frontend API calls ({d['callCount']})\n\n| file:line | method | path | request body keys |\n|---|---|---|---|")
for c in d['calls']:
    w(f"| {c['file']}:{c['line']} | {c['method']} | `{c['path']}` | {', '.join(c['keys']) if c['keys'] else '—'} |")
# settings
keys=collections.defaultdict(set)
for f in glob.glob(f'{API}/app/**/*.php',recursive=True):
    for k in re.findall(r"(?:settingService|settings|SettingService\(\))->get\(\s*'([a-z_]+)'",open(f).read()):
        keys[k].add(os.path.relpath(f,API))
ss=open(f'{API}/app/Services/SettingService.php').read()
allowed=re.findall(r"'([a-z_]+)'",re.search(r"ALLOWED_SETTING_KEYS\s*=\s*\[(.*?)\]",ss,re.S).group(1))
w('\n## 5. Settings keys\n\nAdmin-writable via `PATCH /admin/settings` (whitelist `SettingService::ALLOWED_SETTING_KEYS`): '+', '.join(f'`{k}`' for k in allowed)+'.\n\nRead in code:\n')
for k in sorted(keys): w(f"- `{k}` — {', '.join(sorted(keys[k]))}")
w('\nPublic exposure: `GET /shop` (SettingsController::shop) maps an explicit subset; exchange rate, bank details and rounding step are not exposed to customers.\n')
sm=open(f'{API}/app/Services/OrderStateMachine.php').read()
w('## 6. State machines\n\n### Order status (single source: `app/Services/OrderStateMachine.php`)\n')
blk=re.search(r"ALLOWED_TRANSITIONS = \[(.*?)\n    \];",sm,re.S).group(1)
for fr,tos in re.findall(r"Wex::ORDER_STATUS_(\w+)\s*=>\s*\[(.*?)\]",blk,re.S):
    t=re.findall(r"ORDER_STATUS_(\w+)",tos)
    w(f"- `{fr.lower()}` → {', '.join('`'+x.lower()+'`' for x in t) or '(terminal)'}")
w('''
Rules enforced server-side (after this review):
- Every status write is compare-and-set on the status that was validated (`OrderService::casStatus`); a concurrent change returns 409.
- `ready_for_pickup` only for pickup orders, `out_for_delivery` only for delivery orders.
- Stock is reserved at creation (conditional `UPDATE ... WHERE stock_quantity >= ?`) and restored exactly once on cancel/expire from any non-terminal state.
- `pending_payment` → `expired` happens lazily on read and via `php spark orders:expire` (same `refreshExpiry` path, transactional).
- Customers may cancel only in `pending_payment`; reorder creates a fresh order at current prices.
- Order creation accepts `Idempotency-Key` (unique per user).

### Payment proof
`pending` → `approved` | `rejected` (admin review, only while the order is `payment_review`), `pending` → `replaced` (customer re-upload during review). Approve: order `payment_review` → `confirmed`. Reject: back to `pending_payment` if the lock is still valid, otherwise `expired` with stock restored.

### Price lock
`lock_expires_at = created_at + lock_hours` (setting, default 4h, server clock, UTC). Admin may extend 1–72h while `pending_payment`/`payment_review`. The web countdown uses the server clock offset taken from `Cache-Control: no-store` responses only (cached SW responses are ignored); the API exposes `Date` via CORS.

### Web mirror
`wex-web/src/utils/orderState.js` derives UI state only (labels, next action, countdown); it is display logic and never authoritative.
''')
envs=collections.defaultdict(set)
for f in glob.glob(f'{API}/app/**/*.php',recursive=True):
    for k in re.findall(r"env\(\s*'([A-Za-z_.]+)'",open(f).read()): envs[k].add(os.path.relpath(f,API))
w('## 7. Environment variables\n\n### wex-api (`env()` reads)\n')
for k in sorted(envs): w(f"- `{k}` — {', '.join(sorted(envs[k]))}")
vite=set()
for f in glob.glob(f'{WEB}/src/**/*.js*',recursive=True)+[f'{WEB}/vite.config.js']:
    vite|=set(re.findall(r"import\.meta\.env\.(VITE_\w+)",open(f).read()))
w('\nAlso: `CI_ENVIRONMENT` (must be `production` in prod), `TESTING_LOGIN_ENABLED` (CI only), `database.*`, `app.baseURL`.\n\n### wex-web (build-time, public)\n')
for k in sorted(vite): w(f"- `{k}`")
w(f"\n## 8. Contract check\n\n`wex-web/scripts/contract-check.js` (in `npm run check:all`, CI): {d['routeCount']} routes, {d['callCount']} calls, **{len(d['errors'])} errors**. Checks route existence, HTTP method and request-body keys. Response fields are **not** statically checked (UNVERIFIED; covered only by e2e).\n\nAPI routes without a frontend caller (expected: health, refresh via fetch, content aliases, show-by-id endpoints, fake-log debug):\n")
for x in d['warns']:
    if x.startswith('UNUSED'): w('- '+x.replace('UNUSED  ',''))
open(f'{API}/docs/SYSTEM_MAP.md','w').write('\n'.join(out)+'\n')
print(len(out),'lines')
