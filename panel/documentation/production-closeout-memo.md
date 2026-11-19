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

## 9. Workflow audit (2026-11-19)
Full crosscheck of Tedarik / İhale / Malzeme Standardizasyon against the documented mechanics. **Fixed in this pass:**
- **Shared EAV write engine:** a PUT/POST can no longer reference a `sys_con_ops` id from another document/form — conn ownership (`main_id` + `type_id`) is validated before any entity write. This closes the cross-document hijack (offer → tender/category/product).
- **Form engine:** loaded values are seeded into `dynamicF`, so an untouched edit submits its required fields — **tender edit was 422ing on its own unchanged data**; fixed and verified with/without a new spec file.
- **Tedarik sevk:** ship no longer accepts a client-supplied `sevk_kontrol_ok=1` (stored logistics flag only); replacing proforma/irsaliye resets the flag; `approved` is blocked for cancelled/already-approved/rejected orders; file-status sync cannot reopen terminal or sevk-chain orders.
- **Partial transfer:** selected items must belong to the order and be order-item docs, are deduplicated, and cumulative split cannot exceed on-hand quantity; empty partial payloads are rejected.
- **Permissions:** order/catalog table reads and exports now require their document read permission; catalog is global (GDZ+ADM) instead of tenant-filtered; supplier order ownership extended to `sevkAdvance` and `orderPartitions`.
- **Catalog integrity:** duplicate `prod_code`/category `code` blocked on create+update; product category must be active and real; category/kVA/scope deletion blocked while referenced; product category validated on update.
- **İhale:** rejected library files no longer satisfy offer validation; duplicate products in a tender are rejected; non-numeric offer quantities fail; tender deletion blocked while offers exist; stored-offer supplier ownership checked on GET/PUT/DELETE; `per-041-03` reviewers can read product/category templates; notification links now point to `/ihalepanel/ihaleler*`.
- **Runtime:** PHP 8.2 PUT-multipart parser fixed (was calling PHP 8.4 `request_parse_body`, missing `FileBag` import); notification recipients fixed (plain-string group entities were silently returning zero users — 97 recipients/group restored); Siparişler Excel export implemented (was 404).
- Verified: PHPUnit 66/247, both Excel catalog cross-checks, product/category/tender edit, order/offer lists, exports, no new runtime errors.

**Open (reported, not changed):** order identity fields (`order_no`, `spec_code`, `sys_code`) are still writable by suppliers; required transfer files/`removedData` are not fully locked after send; serial quantity sums are UI-only; item DELETE ownership/idempotency edge cases; `depo_yeri` visible to suppliers; offer flow: soft-deleted offers can still win, tender/supplier reassignment on offer update, 10+ row file substring matching, file-type extension not enforced server-side, per-product kVA scope not modelled, `/order-file/{qnid}` has no ownership check, offer create/complete races; standardizasyon: server-side required-field/unit whitelists, kVA/scope duplicate codes, stale denormalized category titles, seeder code-rename/soft-delete gaps, empty export 404, tenant-blind purge.
