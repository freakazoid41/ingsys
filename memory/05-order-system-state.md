# Order Management System — Current State (2026-09-09)

> **Read after `00-core-overview.md`. Mechanics live in `panel/documentation/*` — this file is LIVE snapshot only, no mechanic duplication.**
> **Control panel DONE. Tedarik panel DONE & POLISHED. Tenants GDZ/ADM. SAP group-by-EBELN. Permissions rebuilt 2026-09-04. Dashboards rebuilt 2026-09-06. Bilgilendirmeler DONE 2026-09-07. Email redesign DONE 2026-09-09.**

## 1. Latest (2026-09-09, newest first)
- **Planlama front panel 1:1 Tedarik clone:** `PlanlamaPanel.vue` full Tedarik shell (1360px frame, typewriter scroll, sidebar, pickletable CSS unchanged) — labels Ana Sayfa + İhtiyaç Listesi + Talep Dökümanları + Bilgilendirmeler badge (planlama-only count), Çıkış fix. New route `/planlamapanel/bilgilendirmeler` (`PlanlamaBilgilendirmeler.vue` planlama-only feed → `PlanlamaFForm`). Dashboard `PlanlamaDashboard` composes 7 new `components/Dashboard/Planlama/*` (Header/Stats/Status/Monthly/Recent/Activity/QuickActions) — all frontend-only (tableList + notifications, no new backend). Build 4.81s.
- **Form engine nested-row align fix:** `Form.vue` sub/multiple inner `.row` also matched Bootstrap `.row>*` gutter padding → inset vs full-width siblings. Neutralized outer edges via `.area-target` CSS (verified pixel-perfect in headless render). Fixes month row + all sub grids app-wide.
- **Malzeme Planlama shell:** `per-041-05` module only (Admin only). `role_details.json` + `coal_roles_templates.json` + `SysRoleTemplateSeeder` seeded, `AuthController getAvailableModules` + `planlamaAuth /planlamapanel` + `planlamaapp.blade.php` + `PlanlamaPanel.vue` + `pages/planlama/Dashboard.vue` placeholder + routes `/coalpanel/planlama` + `/planlamapanel`. Build 4.04s. Content later.
- **Malzeme Planlama subs:** `per-041-05-01 Müdürlük Talep Onay` + `per-041-05-02 Müdürlük İhtiyaç Oluşturma` + `per-041-05-03 Talep Yükleme` under `per-041-05` (Admin only, others `no`). Seeded + verified.
- **System clients (enums, full lock):** `90000001 Planlama Müd.` + `90000002 Sistem İşletme Müd.` + `90000003 Bakım Müd.` × `GDZ/ADM` = 6 docs via `SystemClientSeeder.php` idempotent (`lifnr+client_system`, `is_system=1`). `Documents::tableList` excludes system from `op-doc-client` by default (`system_only=1` opts in). New admin tab **Malzeme Standartizasyon → Müdürlükler** (`MList.vue` read-only, `per-041-05`, `/coalpanel/mudurlukler`). Verified: default 9, system 6.
- **Planlama first form (requests):** `op-doc-request/item` + `op-doc-request-form/item-form` + `doc_trans_request_created` via `PlanlamaRequestSeeder.php` (display: İhtiyaç Listesi). `Form.vue`: header (Başlık + desc, no Müdürlük) + `request_items` multiple (prod/title/unit, quantity forced `0` hidden). Pages `RList + RForm` shared both panels, menu İhtiyaç Listesi (`per-041-05-02`). Perms: read/edit → İhtiyaç Oluşturma, status → Talep Onay. Build clean.
- **Cross-check 2026-11-09 (no breakage):** all Form.vue engine edits surgical (select-into-input-group, readonly select, isMonth readonly, textarea margin); Documents tableList additive only (person_id col + is_system filters, verified 12 orders / 9+6 clients live); getFormData LEFT JOIN fix verified on order detail; docPermCheck additive; all notif surfaces share buildNotificationRows (toolbar == Bilgilendirmeler == dashboards); planlama-02 fail-closed without session; module switcher planlama entry enabled (was dead disabled). New files tracked: lib/talepRows.js, lib/planlamaStatus.js, demo xlsx.
- **Planlama approval chain (PIPELINE LIVE):** `tr-request-man-awaiting` (birth) → `man-approved/man-rejected` (05-01) → `std-approved` (05-04). New perm `per-041-05-04 Malzeme Standardizasyon Yöneticiliği` + notif types `planlama-01/02/03`. Step 2 create → mail 05-01 group; step 3 reject → mail direct to uploader; approve → mail 05-04 group. Bell in all panels + Bilgilendirmeler + planlama bell, `?next=` gateway ext to `/planlamapanel/*`. Verified live transitions + fetchers.
- **Cleanup pass:** `requestFiles` rewritten tag-based (no replaced_id walk); shared `lib/talepRows.js` + `lib/planlamaStatus.js` (TForm/RForm/RList migrated); `Document_files.$fillable += replaced_id`; stale PUT response refreshed after requeue; `firma_adi` re-asserted from `mudur_qnid` on edit; empty-group mail fallback to perm holders. NOT changed: reseller scoping for requestf lists (would blind approvers — owner-scoped bell + group gates cover visibility).
- **late4 LOGO EQUALITY:** GDZ vs ADM aspect mismatch fixed CSS-only (`logo-adm` shave). Rule: NEVER edit logo viewBoxes. Mail PNGs re-baked.
- **late3 TENANT LOGOS:** auth blades + `TedarikPanel.vue` sidebar switch `GDZ.svg` / `adm-logo.svg` by `SYS_CODE`. Mails follow ORDER system, unaffected.
- **late2 FILE→ORDER FIX:** `DocumentController` new `resolveFileOrderDoc()` (entity-conn based, no parent overshoot) + `file_qnid` for detail CTA. `DList.vue:850` JSON leak fixed via shared `noteOf()`.
- **late1 EMAIL REDESIGN:** `TedarikMailHelper.php` NEW + `layout.blade.php` tedarik theme + `?next=` gateway + CID logos. kadir (BOTH, all 7 groups) got 7×GDZ + 7×ADM for `3510004400-1`, all `sent`. Spec: `notification-sending-system.md:9`, gateway: `session-and-login-mechanics.md:12`.
- **2026-09-08 late03 LEAK FIX:** `downloadAllOrderFiles` / `listOrderFiles` / `syncOrderStatusFromFiles` scoped by `(d.id + dt.op_key=order) OR (parent + item)` — base no longer leaks clone files. `OForm.vue:151` `hasAnyFile` hides strip when empty.
- **2026-09-07 late15-18:** imalatci print trap (`tedarikImalatci` preferred over `getFieldValue()`), tedarik-03 resend on refresh, save loading + Detail Kalite, list Kalite confirm.
- Full per-commit history removed — use `git log`. Current behavior: `panel/documentation/tedarik-system-process.md`.

## 2. What Changed Since Coal
Same EAV skeleton, new business. No migration for business fields.

| Layer | Coal | Now |
|-------|------|-----|
| DB | `b2x` 5431 | `tedarikNewApp` 5431, 23 migrations, `GRP GDZ` default |
| Doc types | request/offer/client/user/flat | `op-doc-client/order/item/serial` only (request/offer/transfer PURGED) |
| Forms | 5 legacy | `op-doc-order-form` (order_no/buying_no/spec/sys/ctitle/created readOnly + desc + imalatci + kabul/cins single) + `item-form` (prod/title/qty/unit + test single + images multi) + `serial-form` + `client-form` (+lifnr +client_system) |
| Status | — | `created → transfer_sent → ready → approved/rejected` + `files_rejected`. Kalite `approved` from ANY |
| File types | 5 | + `transfer_kabul/cins`, `item_test`, `item_images` |
| Perms | 19 | 27 (`per-00/041/04/05-01..05/06/07/061`), `per-08` dead. 4 immutable roles |
| Frontend | 17 routes | `/coalpanel/orders` + `/tedarikpanel` (7 tabs), shared `OList/OForm/DList/DForm` via `isTedarik` |
| Auth | blue + orange | single orange `tedariklogin`, `/module-select` post-2FA, `DEV_ADMIN` + `111111` IS_TEST |
| Tenants | YATAGAN/CATES | `adm ? ADM : GDZ` |

## 3. Data Model — Critical (no FKs)
1. **Order↔Client = `LIFNR+SYSTEM`:** `order.spec_code = client.lifnr AND order.sys_code (BUKRS→GDZ/ADM) = client.client_system`. Display `SYSTEM-LIFNR`.
2. **Items** = `op-doc-order-item` (`parent_id=order.id`). Fields `prod_code (=MATNR**EBELP)`, `title`, `quantity`, `unit` (ST int / KG,M float).
3. **Serials** = `op-doc-order-serial` (`parent_id=item.id`). `serial_no`, `production_date` (YYYY-MM-01), `quantity`, `unit`. KG/M required, ST<300 optional, ST≥300 none.
4. **Partial → clone** `op-doc-order` `transfer_no=EBELN-X` only when split. Restores qty on delete.
5. Rules detail: `panel/documentation/tedarik-system-process.md:2`

## 4. Flow (5 lines, detail in mechanics)
SAP flat rows → `orders:sync` groups by EBELN → supplier 6-step detail (transfer type → desc → imalatci → print PDFs → files → Gönder `processOrderTransfer`) → admin `DList/DForm` approve/reject → `syncOrderStatusFromFiles` auto `files_rejected ↔ transfer_sent` → Kalite close / cancel / rename.
Test plan: `panel/documentation/tedarik-system-process.md:12`. File rules: `file-versioning-system.md`. Logs: `logging-mechanics.md`. Notifs: `notification-*.md`.

## 5. Credentials & Infra
- Docker `tedarikNewApp` `127.0.0.1:5431`, `.env` `DB_DATABASE=tedarikNewApp`
- `php artisan serve --host=127.0.0.1 --port=8000`
- Logins: `kadir@kontent.com.tr / Kadir412.` → `111111` → `/coalpanel` or `/tedarik`
- Fresh: `php artisan orders:reset` (wipe serials/files/reads/cache + `sync --fresh`). Payload `/tmp/sap_fresh_payload.json` 21 rows → 8 EBELN. See `memory/07`.

## 6. How To Resume
```bash
docker start tedarikNewApp
cd panel && php artisan migrate:status # 23 done
cd panel && npm run build # after any Vue edit
php artisan serve --host=127.0.0.1 --port=8000
php artisan orders:reset # clean slate when needed
```

## 7. Active Debt (history removed)
- File replacement FINAL: new `sys_con_entities` row per upload, active = `document_files.status=1`, `new.replaced_id=old`. Do NOT change without Master.
- `ready_for_shipment` full lock; `files_rejected` keeps files editable.
- `getFormData json_agg ORDER BY t.id` — last = current.
- ST cap 300. PHP `??` vs `?:` on `''`. Export PDFs before `{model}` wildcard.
- Open: `Documents::tableList parent_type_id=0` items in main lists; security (`DEV_ADMIN`, public reset, CSRF off, `/order-file` IDOR, `pickle` key); `B2X` stopped container; `permission_version file` store flaky.
- Shared-pages decision: `OList/OForm/DList/DForm` stay SHARED via `isTedarik`, fork only if supplier flow diverges.

## 8. Memory Map
| File | Contents |
|------|----------|
| `00` | architecture + doc map |
| `01` | Form.vue engine intro (canonical: `form-system-mechanics.md`) |
| `02` | EAV intro (canonical: `form-system-mechanics.md`) |
| `03` | new doc-type recipe |
| `04` | patterns intro (canonical: mechanics) |
| `05` | THIS live snapshot |
| `06` | next steps |
| `07` | SAP sync command |
| `idea` | original spec |
