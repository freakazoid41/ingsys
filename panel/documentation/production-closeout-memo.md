# Production closeout memo

Run these before/after the production build goes live.

## 1. Database — disable JIT
The local profiling found PostgreSQL JIT compiling every complex document
query for ~350ms (notification feed 3.5s). Disable it on the production
database as well:

```sql
ALTER DATABASE "<production_db>" SET jit = off;
```

Verify (new connection):

```sql
SHOW jit;   -- off
```

If the server is built without LLVM, `jit` is already `off` — nothing to do.

## 2. `.env`
```env
APP_ENV=production
APP_DEBUG=false
IS_TEST=false
```

`IS_TEST=true` bypasses 2FA mail/SMS and reCAPTCHA and disables tender mail
fan-out — it must never be true in production.

## 3. Build & cache
```bash
npm run build
php artisan migrate --force     # NOT migrate:fresh — that wipes live data
php artisan optimize
```

Restart PHP-FPM and the queue worker after `optimize`.

## 4. Queues
Tender notifications use `afterResponse()`; with `QUEUE_CONNECTION=sync`
the mail fan-out still runs on the request. For production prefer a real
queue worker:

```env
QUEUE_CONNECTION=database
```
```bash
php artisan queue:work --tries=3
```

## 5. Smoke checks
- Real login with 2FA code (mail/SMS must actually arrive).
- Dashboard loads; bell returns quickly (~150ms cold, ~20ms warm).
- Create/activate a tender; verify no 20-30s stall.
- Supplier offer page loads category file templates.

## 6. Rollback note
If a reporting query genuinely needs JIT later:

```sql
ALTER DATABASE "<production_db>" RESET jit;
```

or enable it per session/query only.

## 7. Known open security items (do not go live with these)
- CSRF validation is disabled globally (`bootstrap/app.php` → `validateCsrfTokens(except: ['*'])`).
- `DEV_ADMIN` receives a fixed `111111` 2FA code in **any** environment — unset the env var.
- `/order-file/{doc}` serves files by token without an ownership re-check.
- Legacy file bytes were not in the import dump — clone `public/documents/` before go-live or downloads 404.

## 8. Legacy import audit (2026-11-19)
- `orders:import-legacy` verified against the live `livetdedarik_src` snapshot: all 12 legacy status keys are mapped, source `sys_code` values are clean (4000/5000; suppliers 40005000→BOTH), log types fully mapped, item titles ≤40 chars (no truncation loss). Counts reconcile: files −1,302 (factory, by design) −12 orphans; transactions −2,654 orphans + status backfill; users +3 kept accounts.
- **Fixed two script bugs:** (a) item `prod_code` entity used `$ln->prod_code.'**'.$ebelp` instead of the precomputed `$prodCode` — on any re-run every SAP `MATNR**EBELP` code would get a second `**00010` suffix and break SAP-sync matching; (b) serial `production_date` was truncated to month start (`Y-m-01`) — now stores the full `Y-m-d` (the `uretim_tarihi` filter is an `ilike '%YYYY-MM%'`, so month filtering still works).
- **Note:** live DB qnids only match the current snapshot for ~67% of orders / ~65% of items — the live import ran against an earlier dump with empty qnids (script generated UUIDs). Fresh re-imports from the current snapshot copy every qnid. Internal-only, no external references.
- Live data currently has month-truncated `production_date` on 154k serials from the old import — a repair pass from the snapshot is possible if the exact day matters.
