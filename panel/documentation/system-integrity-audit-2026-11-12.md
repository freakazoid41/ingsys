# System Integrity Audit — Docs vs Code (2026-11-12)

> Scope: `panel/documentation/**` + `memory/**` against the live `tedarikNewApp` schema/code.
> Environment: PostgreSQL `127.0.0.1:5431` (Docker `tedarikNewApp`), Laravel test DB `komur_test`.
> Commands used: `php artisan test`, `php artisan route:list`, `php artisan migrate:status`, `php artisan tinker`, `npm run build`, direct SQL integrity queries.
> **Post-audit tender closeout (same day):** admin/coalpanel-only tender entry, deadline, server-side offer item/file validation, per-file acceptance, and 5 tender notification types were added. Tedarik is intentionally kept separate (no İhale menu/tile). See `modules/tender.md` for the current open list.

## 1. Verdict

- **Core EAV mechanics are intact**: no orphan `sys_con_ops` / `sys_con_entities`, no broken `replaced_id` chains, no duplicate active file slots, no invalid dictionary foreign keys, no orphan transaction targets.
- **All tests pass after tender closeout** (1 real-SMS test skipped): `63 passed, 1 skipped, 228 assertions`.
- **Production build passes**: Vite `✓ built in 4.67s`.
- **Tender hardening is in place** (status forgery, terminal lock, duplicate offers, rejected-winner, transaction, table scoping, admin/İB inspect, product count).
- **Documentation is not uniformly current**. The mechanics bibles are close; the permission/notification/export/count snapshots had drift and are corrected in this pass.
- **Two real data gaps remain**: 2,676 active imported orders have no `op_id=0` transaction, and 18 active Planlama docs were missing birth statuses (now repaired for Planlama).

## 2. Verified mechanics

| Mechanic | Result | Evidence |
|---|---|---|
| EAV document engine | OK | `DocumentServiceProvider::registerContent/getFormData`; 208,882 documents; 0 orphan ops/entities |
| File versioning | OK | 0 broken `replaced_id`, 0 multiple active files per entity tag, 0 `relation='-'` ghosts, 0 temp older than 24h |
| Server-side field authority | OK | `GENERIC_WRITABLE_MAIN_FIELDS` = title/starting_at/ending_at; status/identity not writable via generic payload |
| Permissions / roles | OK after repair | 37 catalog codes, 5 role templates; reseller now includes `per-041-01`; 147/147 reseller persons updated |
| Session / force logout | OK | `CheckPermissionVersion`, `active_sessions`, 83 active session rows |
| File status machine | OK | `waiting → accepted/rejected → refreshed`; `Document_files::tableList` reseller SQL fixed |
| Audit logging | OK | `user_logs` + `transactions`; enrichment via `AuditService`; no orphan log/transaction types |
| Notification receiving | OK | 15 live types (`tedarik-01..07`, `planlama-01..03`, `ihale-01/02`, `offer-01..03`) in `SystemController`/`ReportServiceProvider` |
| Notification sending | OK | 15 types dispatched; BUKRS/LIFNR gates covered by tests; PNG mail logos |
| Export | OK | clients, documents, products, productcategories, kvas, notificationlogs, users, userlogs; legacy offers/requests branches retained |
| Order / Tedarik pipeline | Mostly OK | tests cover create→send→file status→cancel/rename; status sync all-accepted→`ready_for_shipment` |
| Planlama pipeline | Code OK, data repaired | `awaiting → man-approved/man-rejected → std-approved`; backfill command added |
| Tender pipeline | OK after hardening | activate/complete/accept/reject + snapshot + terminal locks + duplicate guard |
| Products / categories / kVA | OK | 1 category, 9 products, 10 kVA docs; 14 category file-template rows (84 entities) |

## 3. Real defects found and fixed in this audit

1. **`Document_files::tableList` malformed SQL** — missing `join` before `sys_con_entities sce3` in the reseller spec/sys scoping. Broke `tedarik-04` notification fetch (`SQLSTATE 42601`). Fixed in `app/Models/Document_files.php`.
2. **`SmsService` typed-property fatal** — `Config::get()` returned null into `string` properties when credentials were absent, so log-only mode/tests crashed before the log-only check. Fixed with string casts; only the real gateway call still fails closed.
3. **Controller raw-SQL policy violation** — `DocumentController::requestFiles` had `DB::select`, failing the regression guard. Moved into `DocumentServiceProvider::latestFileTransactions()`.
4. **Stale mail-logo tests** — asserted legacy `gdz.jpg`/`adm.jpg`, while the live mail layout embeds `mail-gdz.png`/`mail-adm.png` as data URIs. Tests now assert `TedarikMailHelper::logoUrl()`.
5. **Supplier access on clean seed** — reseller role lacked `per-041-01`; `/coalpanel` 403. Added to role JSON, reseeded roles, propagated to all 147 reseller persons.
6. **Tender security/integrity** — generic `set-status` blocked for tender/offer types; server-side terminal lock from stored state; offer chain; rejected offers cannot win; winner+snapshot+both statuses in one transaction; duplicate supplier+tender offer blocked; table endpoint checks all type filters; reseller offer rows scoped; admin/İB can open offer detail.
7. **Tender UI correctness** — snapshot-only read after completion (no live library fallback), winner picker hides rejected offers, product count uses server `row_count`.
8. **Broken admin dashboard action** — “Yeni Talep Oluştur” pointed at removed `RequestForm`; now “Siparişleri Aç” → `OrderList`. Reseller dashboard no longer mounts the dead legacy `ClientOfferTable`.
9. **Kalite permission mismatch** — `OList` required `per-05-03`, while `OForm`/backend required `per-07-02`; aligned `OList` to `per-07-02`.
10. **Planlama birth statuses** — 18 active demo/E2E docs had no status transaction. Added `planlama:repair-statuses` (dry-run supported) and ran it; 7 İhtiyaç + 11 Talep now have birth statuses.

## 4. Remaining data / pipeline risks

- **2,676 active imported orders have no `op_id=0` transaction.** Their list status is blank and status-driven notifications cannot fire. Do not blindly assign a status; decide per source order or re-import with source status.
- **Planlama inactive docs (4 İhtiyaç + 5 Talep)** were skipped by the backfill because `documents.status=0`; they are not listed, but any restoration should backfill them first.
- **Seeder-created clients/products/categories have no birth transaction** because their seeders insert rows directly instead of `registerContent`. They are not status-driven, but audit history is absent.
- **Legacy dictionary rows remain**: `op-doc-offer`, `op-doc-offer-form`, `op-doc-flat`, `op-doc-client-main`, and legacy status groups. No documents use them, but they are not purged.
- **`request:autoclose` is legacy/dead** for the new Planlama model (targets `op-doc-request` + `contract_end_date` and processes only the first row). It is still scheduled at 01:00. Decide to remove/repurpose.
- **Tender closeout delivered after this audit:** admin/coalpanel-only supplier entry (`/coalpanel/ihaleler*`), 5 notification types (`ihale-01/02`, `offer-01..03`), deadline enforcement, server-side offer/file validation, per-file acceptance, and `TenderPipelineTest`. Remaining tender scope: invites, prices/order handoff, multi-lot, auto-disqualification cron.
- **`ClientStats.vue`, `ClientOfferTable.vue`, `OfferTable.vue`, `OfferRequestTable.vue`, `DashboardCalendar.vue`, `DashboardRequestTables.vue`** are unused legacy components; some still point at removed route names. They are not mounted, but should be deleted or marked.
- **`notification_logs` has 52 email `error` rows** (historic); verify the current relay/SMTP setup before treating mail delivery as green.
- **`Documents::tableList` / `Document_files::tableList`** still use string-built SQL with `noInject`; the tender/offer path is permission-gated now, but broader per-type hardening is still advisable.

## 5. Documentation authority map

| File | State |
|---|---|
| `form-system-mechanics.md` | Canonical; accurate mechanics, some stale line numbers/legacy names |
| `file-versioning-system.md` | Canonical; accurate |
| `logging-mechanics.md` | Canonical; accurate |
| `session-and-login-mechanics.md` | Canonical; accurate |
| `single-session-enforcement-system.md` | Accurate |
| `tedarik-system-process.md` | Accurate mechanics; permission/role counts corrected (Kalite = `per-07-02`) |
| `modules/order-suppliment.md` | Test supplement; supplier permission text corrected |
| `modules/order-planning.md` | Accurate; notification-surface claim corrected; rejected is re-openable, not terminal |
| `modules/products.md` | Accurate; 37-permission count corrected |
| `modules/tender.md` | Accurate after hardening; dictionary/demo-data sections corrected |
| `export-system.md` | Model list updated (products/categories/kvas) |
| `permission-system-analysis.md` | Current catalog/roles patched; older narrative sections remain historical |
| `notification-receiving-system.md` | Top banner marks live 15-type feed; legacy `notif-00..03` sections are historical |
| `notification-sending-system.md` | TEDARIK 7 + PLANLAMA 3 + TENDER 5 list; duplicate handler bullets removed |
| `optimize-suggestions.md` | Historical optimization notes; some legacy offer examples |
| `memory/00-core-overview.md` | Counts/types/routes corrected to 5 roles / 37 perms / live doc families |
| `memory/05-order-system-state.md` | Live counts updated; 4 tenders / 0 offers confirmed |
| `memory/01-04, 06-07` | Summaries/recipes; trust the canonical mechanics docs for details |

## 6. Verification commands

```bash
cd panel
php artisan test                         # 63 passed, 1 skipped, 228 assertions
npm run build                            # ✓ built
php artisan route:list                   # 74 routes
php artisan migrate:status               # all migrations ran
php artisan planlama:repair-statuses --dry-run
```

DB integrity spot checks:

```sql
-- all zero
orphan sys_con_entities / sys_con_ops / transactions
broken document_files.replaced_id
multiple active files per entity tag
document_files.relation='-'
```

## 7. Recommended next pass

1. Decide the 2,676 order status backfill (source-status mapping, not blanket birth).
2. Remove/repurpose `request:autoclose`; add Planlama deadline semantics only when product defines them.
3. Tender next scope: participant/invite model, deadline auto-disqualification cron, prices, order handoff, multi-lot.
4. Delete/archive unused legacy dashboard/offer components.
5. Keep extending `TenderPipelineTest` for matrix file validation and admin offer inspect.
