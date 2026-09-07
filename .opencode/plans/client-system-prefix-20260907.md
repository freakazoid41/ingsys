# Client System Prefix — GDZ / ADM Split (lifnr collision)

**Date:** 2026-09-07  
**Owner:** RedV  
**Status:** Plan — awaiting Master approval to build  
**Context:** `lifnr` (Cari Kodu, e.g. `0000300186` / `04546546`) is currently global unique. Same numeric code can belong to different legal clients in GDZ vs ADM. Today `SyncOrdersCommand:323 findOrCreateClient` checks `lifnr` alone (`se.entity_value = lifnr`), `Documents::tableList:139` and `Document_files:162` resolve `lifnr IN (...)` without system, so a reseller bound to GDZ `0000300186` sees ADM orders with same code. Master's request: treat client code as **2-part** `SYSTEM-NUMBER` (`GDZ-04546546` / `ADM-04546546`), add system selector on client form, composite save, system-aware order lookup, and show system in both places from screenshots.

---

## 1) Goal & UX

- **Client form** `panel/resources/js/components/coalparts/Form.vue:908 op-doc-client-form` — add a `select` (or toggle) **Sistem: GDZ / ADM** next to `Cari Kodu`. Save composite `SYSTEM-NUMBER` (display) while storing two atomic entities (`lifnr` numeric + `client_system`/`sys_code` = GDZ/ADM). Backward compat: existing rows with no `client_system` default to GDZ.
- **Order → Client link** — change from `order.spec_code (=LIFNR) == client.lifnr` to `order.spec_code == client.lifnr AND order.sys_code (BUKRS→GDZ/ADM) == client.client_system`. Sync and all manual flows obey this.
- **Table: Client list** `panel/resources/js/pages/coalsystem/Client/CList.vue:183` — add **Sistem** column (pill `GDZ #fff7ed/#fed7aa` / `ADM #eff6ff/#dbeafe`, `BOTH` if missing). `formatClientCard:105` shows `Cari Kodu: GDZ-0000300186` with system badge. Server adds `client_system` to `tableList` response.
- **Modal: Cari Seçiniz** second screenshot `panel/resources/js/composables/useClientModal.js` + `panel/resources/js/pages/coalsystem/Order/OList.vue:196 buildClientTable` + `DList:150` same — add **Sistem** column, value from same entity. Filtering stays same.
- **Inline reference: Bağlı Cariler** first screenshot `panel/resources/js/pages/coalsystem/Users/UForm.vue` (or `CForm` userclientgroup) — the read-only `Cari Kodu` / `Cari Başlık` table — add third column **Sistem** showing `GDZ/ADM` badge from `client_system`. Data comes from `PersonsServiceProvider:603 getPerson` already joins clients; extend to fetch `client_system`.

---

## 2) Data Model — No Migration Needed (EAV)

New EAV entity under `op-doc-client-form`:
- `entity_tag = client_system` (alt `sys_code` — pick one and keep consistent) `table_tag = sys_con_ops` `entity_value = GDZ | ADM`
- Keep `lifnr` as **numeric only** (`0000300186` stored as `0000300186`, not `GDZ-...`). Composite is **derived** `client_system + '-' + lifnr` for display/export, but **storage stays atomic** so range / like searches still work and existing rows stay valid.

Why atomic not string `GDZ-...` in `lifnr`:
- Minimal churn: `lifnr` stays `like` searchable (`%054646%`) already used in `Documents:446 sirket`.
- Easy fallback: `COALESCE(client_system,'GDZ')`.
- No need to rewrite existing `spec_code` (order side stays numeric) — join becomes 2-column.

Alternative considered & rejected: storing `lifnr = GDZ-000...` composite (would break 10-digit numeric validation `Form.vue:1055` and all existing `IN (lifnrs)`).

`documents.grp_code` stays as is — but when `client_system` is set, post-save we also update `documents.grp_code = client_system` so tenant filter `Documents.php:214 default grp_code ILIKE` stays consistent. If admin creates ADM client from GDZ host, doc will be `ADM` and still visible via explicit system column (or list bypasses tenant filter for `op-doc-client` — decision below).

---

## 3) Changes — File by File

### 3.1 Form — add system selector

**File:** `panel/resources/js/components/coalparts/Form.vue:908`
- Insert new field before `lifnr` (sub_4):
  ```js
  { type:'select', name:'client_system', label:'Sistem', col:4, required:true,
    options:[{value:'GDZ',label:'GDZ'},{value:'ADM',label:'ADM'}],
    class:['form-control','form-item'], oninput: submitDynamicChanges }
  ```
- Keep `lifnr` numeric 10-digit, placeholder `0000300186`, same `replace(/\D/g)` guard. Add helper `formatClientCodeDisplay()` for preview `GDZ-000...` if needed.
- `clicode` stays `readOnly` (qnid).

**File:** `panel/app/Providers/DocumentServiceProvider.php:370` client EAV block
- On create/update, if `client_system` in payload → persist it. On create default `GDZ` if missing.
- After `Documents` save, sync `documents.grp_code = client_system` (or keep both, but at least `grp_code` matches for legacy tenant filter).

### 3.2 Sync — make client lookup system-aware

**File:** `panel/app/Console/Commands/SyncOrdersCommand.php:318 findOrCreateClient`
- Signature becomes `findOrCreateClient(lifnr, name, clientTypeId, formTypeId, formMainId, sysCode)` where `sysCode = bukrsToSystem(bukrs)` (`4000→GDZ`, `5000→ADM` from existing `ReportServiceProvider:19 bukrsToSystem`).
- Query: add `client_system = sysCode` predicate:
  ```sql
  WHERE se.entity_tag='lifnr' AND se.entity_value=:lifnr
    AND EXISTS (SELECT 1 FROM sys_con_entities se2 JOIN sys_con_ops so2 ON so2.id=se2.conn_id
                WHERE so2.main_id = d.id AND se2.entity_tag='client_system' AND se2.entity_value=:sysCode)
  ```
  Fallback: if no row with `client_system`, treat missing as `GDZ` for backward compat (`COALESCE`).
- Insert: add `client_system` entity alongside `lifnr,title`.
- `createOrder` already does `spec_code=lifnr` + `sys_code=bukrs`; no change but verify `sys_code` entity is `bukrsToSystem` normalized.

**File:** `panel/app/Console/Commands/SyncOrdersCommand.php:397 reconcileResellerClientBindings`
- When fixing `cliid`, also compare `client_system` vs order `sys_code` so ADM reseller isn't repointed to GDZ client with same lifnr.

### 3.3 Order ↔ Client matching — everywhere LIFNR is resolved

Pattern to change: `SELECT lifnr FROM client WHERE qnid IN (...)` → also fetch `client_system`, then build map `sysCode -> [lifnrs]`. Then `spec_code IN (...)` must be AND `sys_code = ...`.

Files:

- **`panel/app/Models/Documents.php:136`** `op-doc-order` branch
  - Currently: `$lifRows = SELECT lifnr FROM ... WHERE d2.qnid IN ($qnidIn)`
  - New: `SELECT lifnr, client_system` (COALESCE to grp_code). Partition into `lifnrsGDZ`, `lifnrsADM`.
  - Build where: `(spec_code IN (gdz) AND sys_code='GDZ') OR (spec_code IN (adm) AND sys_code='ADM')` etc. Provide helper `buildSpecSysWhere(lifnrsBySys)`.
  - Same for `op-doc-order-item:153` and `op-doc-order-serial:166`.

- **`panel/app/Models/Document_files.php:162`** — same `lifnrs` fetch, but where on `d`/`parent d` needs dual check.

- **`panel/app/Providers/ReportServiceProvider.php:759 getResellerLifnrs` + `766 resellerOrderWhere`** — make it return `['GDZ'=>[...],'ADM'=>[...]]` or keep flat but also fetch system. Update callers `tedarik-06:165` LIFNR gate to system-aware.

- **`panel/app/Providers/PersonsServiceProvider.php:591 clientPermInfo`** — already returns `clientQnidList`; add `clientSysMap` fetch for debugging? Optional.

- **`panel/app/Http/Controllers/DocumentController.php:717 fileDetail`** LIFNR scope `750` — add system check.

- **`panel/app/Jobs/SendNotificationMailJob.php:608 etc`** reseller LIFNR match `tedarik-04..07` — already BUKRS-gated but LIFNR was flat. Change to require `spec_code IN (lifnrsForThatBukrs)`.

- **`panel/app/Models/Documents.php:462 sirket/tedarikci filter`** — optional: when admin filters `sirket=054646`, currently `spec_code ilike %054646%` across both systems; keep but display shows `ADM-054646` so user sees disambiguation.

### 3.4 Tables — expose system

**File:** `panel/app/Models/Documents.php:230 tableList` columns
- Add `'client_system' => "(SELECT se.entity_value FROM sys_con_entities se JOIN sys_con_ops so ON so.id=se.conn_id WHERE so.main_id=i.id AND se.entity_tag='client_system' LIMIT 1) as client_system"` but only for `op-doc-client`? Or generic. Simpler: add to `columns` map and include in `main_attr` LATERAL already aggregates all entities, so `rowFormatter` could parse it. However `CList` does `data[Key]=Value` from `main_attr`, so `client_system` will automatically appear as `data.client_system` without SQL change, as long as entity exists. But header needs dedicated column for sorting. Add `lifnr` already has. Add `client_system` column with `order true` and use `main_attr ilike` fallback if not in columns.

Better to add explicit column to avoid lateral JSON parse for sorting: add `client_system` column as above and use for header key.

**File:** `panel/resources/js/pages/coalsystem/Client/CList.vue:196 headers`
- Add `{ title:'Sistem', key:'client_system', order:true, type:'string', columnFormatter: pill }` after `Cari Kodu`. `rowFormatter:285` already maps all `main_attr` keys including `client_system`, so data will have it.
- Update `formatClientCard:124` to show `Cari Kodu: ADM-0000300186` or `GDZ-...` with small badge `client_system` pill.

**File:** `panel/app/Http/Controllers/ExportController.php:40 clients case`
- Add header `Sistem` → `client_system`, rowCallback decode `client_system ?? grp_code ?? 'GDZ'`.

### 3.5 Modals — Cari Seçiniz + Bağlı Cariler

**File:** `panel/resources/js/composables/useClientModal.js` (and `OList.vue:196 / DList.vue:150 buildClientTable`)
- Currently: fetch `POST /v1/table/documents {form-type:op-doc-client-form, type:op-doc-client, limit 200}` then map `main_attr → {id,lifnr,clititle,label}`.
- Add `client_system` to map: `client_system = parsed.client_system || 'GDZ'`.
- Add column definition for modal table: `{ title:'Sistem', key:'client_system', width:'90px'}` with pill formatter. Update modal layout to 3 columns (existing 2 → 3).

**File:** `panel/resources/js/pages/coalsystem/Users/UForm.vue` (or `CForm`? Check Bağlı Cariler section)
- Location: `UForm.vue` shows Bağlı Cariler table with `Cari Kodu / Cari Başlık`. Need to inspect `panel/resources/js/pages/coalsystem/Users/UForm.vue:???` — currently builds rows from `cliid → client qnid → title/lifnr`. Add third column `Sistem` fetching `client_system` via same `getFormData` or via `tableList` include.
- Quick fix: in `PersonsServiceProvider: getPerson` clients JSON already could include `client_system` if we join it; else frontend can fetch extra via `POST /v1/table/documents` ids.

**Files to verify via grep:**
- `panel/resources/js/pages/coalsystem/Users/UForm.vue`
- `panel/resources/js/components/Order/OrderItemTable.vue` (no)
- `panel/resources/js/pages/coalsystem/Order/OList.vue` client modal (already)

### 3.6 Validation & Helpers

- `Form.vue:1055` lifnr numeric guard `replace(/\D/g,'').slice(0,10)` stays; system select is separate so no need to embed dash in lifnr input. Display composite only in read-only pill/card.
- Add helper `bukrsToSystem` already in `ReportServiceProvider:19` — reuse via shared util or duplicate in `Documents.php` (currently has inline bukrs handling). Extract to `app/Helpers/SystemHelper.php` if time, else copy.
- `noInject` already used for `qnidIn`.

---

## 4) Migration / Backfill

- **Existing clients:** `SELECT d.id, d.qnid, se.entity_value lifnr FROM ...` where `client_system IS NULL` → `INSERT entity client_system='GDZ'` (or `grp_code` if `ADM`). One-off artisan command `php artisan clients:backfill-system` or inline in `findOrCreateClient` fallback (COALESCE). Safer to run once via tinker.
- **Existing orders:** already have `spec_code` numeric + `sys_code` `4000/GDZ` — no change. Link will now be composite, so GDZ orders will only match GDZ clients. No data loss.
- **Reseller bindings:** no change — they store `qnid` pointing to specific system client. After backfill, GDZ vs ADM qnids are distinct, so binding is correct.
- **Idempotency:** After change, `findOrCreateClient` with same lifnr but different system will create second client doc (desired). Need to ensure `--fresh` wipe doesn't delete ADM clients when syncing GDZ payload — separate by `grp_code`? `wipeExistingOrders` deletes orders only, not clients, so safe.

---

## 5) Risks & Gotchas

- **Tenant filter:** `Documents::tableList:214 default` `i.grp_code ILIKE SYS_CODE` would hide ADM clients when admin on GDZ host. For `op-doc-client` we should **bypass** grp_code filter or show all with pill, and filter by `client_system` entity instead. Change: `case 'op-doc-client':` should skip grp_code clause. Need to add `formType == 'op-doc-client' → no grp_code filter`.
- **Export / Search:** `sirket` filter `446` uses `spec_code ilike %value%` — if user types `ADM-054646`, need to strip prefix or search composite. Better to search `lifnr` numeric part only, but display shows prefix. Document that search is numeric-only, or make `sirket` search also on `client_system || '-' || lifnr`.
- **Performance:** Adding extra SELECT per `tableList` (lifnr+system) doubles? But still one query with extra column. Acceptable.
- **BC break:** Any raw SQL elsewhere that assumes `lifnr` is the only key (e.g., `SyncOrdersCommand:111 order_no exists`, not lifnr) is safe. Only lifnr scoping changes.

---

## 6) Testing Plan

- **Prep:** `php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh` gives 8/21 baseline GDZ. Create ADM client manually via `/coalpanel/client/form` — select ADM, lifnr `0000300186` (duplicate numeric), save. Verify `documents status1` + entity `client_system ADM`.
- **Sync duplicate:** Prepare JSON with one ADM order `LIFNR 0000300186 BUKRS 5000 EBELN 3510009999 MCOD1 ADM TEST` → `php artisan orders:sync --json=/tmp/adm.json` → should create **second** client? No, already exists, so `findOrCreateClient` should find ADM client, not reuse GDZ. Verify `stats clients 0`.
- **Reseller bind:** Create two resellers `reseller_gdz` bound to `GDZ-0000300186` and `reseller_adm` bound to `ADM-0000300186`. Login each → `/tedarikpanel/orders` should see only respective system order (`3510004400` vs `3510009999`). Verify `Documents::tableList` filtering.
- **UI:** Check `CList` shows Sistem pill, card shows `ADM-...` / `GDZ-...`. Check `Cari Seçiniz` modal 3 columns, sort by system. Check `UForm` Bağlı Cariler third column. Check export xlsx has Sistem column.
- **File detail:** `GET /api/v1/file-detail/:qnid` for ADM file as GDZ reseller → 403.
- **Notifications:** `ReportServiceProvider tedarik-01` for ADM order should only notify ADM `BOTH` or `ADM` users, not GDZ.

---

## 7) Files Touched (est. 10)

1. `panel/resources/js/components/coalparts/Form.vue:908` — add `client_system` select
2. `panel/app/Providers/DocumentServiceProvider.php:370` — persist `client_system`, sync `grp_code`
3. `panel/app/Console/Commands/SyncOrdersCommand.php:318` — system-aware `findOrCreateClient` + reconcile
4. `panel/app/Models/Documents.php:136,153,166,179,214,462` — dual lifnr+sys filtering, new column, export
5. `panel/app/Models/Document_files.php:162` — same
6. `panel/app/Providers/ReportServiceProvider.php:759,766` — `getResellerLifnrs` system map
7. `panel/app/Jobs/SendNotificationMailJob.php:608` — LIFNR+BUKRS duel
8. `panel/resources/js/pages/coalsystem/Client/CList.vue:196` — Sistem column + card
9. `panel/resources/js/composables/useClientModal.js` + `OList.vue:196` + `DList.vue:150` + `Users/UForm.vue` — modal + Bağlı Cariler third column
10. `panel/app/Http/Controllers/ExportController.php:40` — add Sistem header
11. `panel/documentation/*` + `memory/05-order-system-state.md` — update mechanics (system prefix, composite key)

---

## 8) Rollback

- Revert `client_system` entity optional — if missing, all code falls back to `GDZ` via `COALESCE`. No data loss. To undo, drop `client_system` entities: `DELETE FROM sys_con_entities WHERE entity_tag='client_system'`.

---

## 9) Open Questions for Master

- **Display format:** Confirm `GDZ-04546546` (with dash) vs `GDZ04546546` vs `GDZ / 04546546`? Plan assumes `SYSTEM-NUMBER` with dash as you wrote `ADM/GDZ-054646` (dash). We'll show pill + dash in code like `GDZ-0000300186`.
- **Default for new clients:** Assume `GDZ` pre-selected? Or no default, required choice?
- **Should client list be unfiltered across tenants** (show both GDZ/ADM always) or stay tenant-filtered but column shows? Plan proposes **show all** for `op-doc-client` (remove grp_code ILIKE for that type) so admin sees both without switching host.
- **Existing duplicate lifnr → ADM client:** Do you want me to auto-migrate current `0000300186` (YILDIZ etc.) to GDZ and prompt to create ADM twin on next ADM sync, or manually create ADM variant via form?

**Next step:** If you say “build it”, I’ll implement §3 in one go, run `php artisan migrate` (none), backfill, and verify with a dry-run ADM payload.
