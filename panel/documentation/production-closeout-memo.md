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
