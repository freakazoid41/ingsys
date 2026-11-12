# Performance diagnosis — 2026-11-12

## Symptom
The whole panel felt "remarkably slow": page clicks waited, the bell/dashboard lagged, and login took several seconds. No single API looked slow at first because each page shell was ~100ms; the cost was hidden in one background request.

## Root causes (measured)

| # | Cause | Evidence |
|---|---|---|
| 1 | **PostgreSQL JIT enabled** (`jit=on`, `jit_above_cost=100000`) | Every complex document query spent **~350ms compiling** before returning. 15 notification categories ⇒ **~3.5s** per bell call. `EXPLAIN ANALYZE` showed 355ms before the first row on a 16-row result. |
| 2 | **Notification feed fetched entire result sets** | `getTedarikOrders` had no LIMIT: `doc_trans_order_transfer_sent` pulled **31,360 orders** per request, then sliced to 10 in PHP. |
| 3 | **Missing indexes** | `sys_options` 95% seq-scan (**6.5M scans**, no `op_key` index); no `(type_id, id DESC)` on `documents`; no `(type_id, id DESC)` on `transactions`. |
| 4 | **2FA mail/SMS sent in test mode** | `IS_TEST=true` still sent real SMTP/SMS; login POST took **3.8s** although SQL was only ~38ms. |
| 5 | **Duplicate notification fetches** | Multiple dashboard widgets each called `/api/v1/notifications` on mount; the layout header added one more. |
| 6 | **Heavy dashboard aggregates** | `admin-stats` ran 18 COUNT queries (~385ms), `admin-monthly` 11 COUNTs (~240ms). |

The dev server is single-threaded (`php artisan serve`), so any slow request blocked every other request — one 3.5s bell call made the whole UI feel frozen.

## Fixes
- `ALTER DATABASE "tedarikNewApp" SET jit = off;` — **biggest win**. JIT helps heavy analytics; here it was pure per-query overhead.
- `ReportServiceProvider::getTedarikOrders` now resolves the latest 30 `transactions` ids first (`latestOrderTargetIds`) and loads only those documents via a new `target_ids` filter.
- Notification fetchers use `scale.limit = 30` + `noCount` (`Documents`/`Document_files`), so no full scan and no wide COUNT.
- Migration `2026_11_12_000002_add_performance_indexes`: `sys_options(op_key)`, `sys_options(group_key)`, `documents(type_id, id DESC)`, `transactions(target_id, id DESC)`, `notification_reads(user_id, op_key)`.
- Migration `2026_11_12_000003_add_transaction_type_index`: `transactions(type_id, id DESC)`.
- `SystemController::getNotifications` caches the feed for 15s per user/person/type; mark-read/mark-all invalidate it.
- Frontend `navigationStore.getNotifications` coalesces in-flight calls and reuses the result for 20s.
- `ReportController::dashboard` caches aggregates for 30s per type/period/system/user.
- `AuthController`: `IS_TEST=true` returns the fixed 2FA code without sending mail/SMS, and no longer requires a reCAPTCHA token in test mode.

## Before → after (local)

| Path | Before | After |
|---|---|---|
| `/api/v1/notifications` (cold) | 3,490–3,880 ms | ~130–150 ms |
| `/api/v1/notifications` (warm) | 3,500 ms | ~20 ms |
| Login POST (`/auth/login/tedarik`) | ~3,850 ms | ~270 ms |
| `dashboard/admin-orders` | ~800 ms | ~68 ms |
| Page shell loads | ~300 ms (blocked) | 70–100 ms |

Verification: `php artisan test` ⇒ **63 passed, 1 skipped, 228 assertions**.

## Notes for deploys
- The JIT change is a **database setting**, not in code. Apply it to any other environment where the same query pattern is slow:
  `ALTER DATABASE "<db>" SET jit = off;`
- `IS_TEST` must stay **false** in production (2FA bypass and reCAPTCHA bypass are gated on it).

Production deployment checklist: `documentation/production-closeout-memo.md`.
