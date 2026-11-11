# Order Management System — Current State (2026-09-09)

> **Read after `00-core-overview.md`. Mechanics live in `panel/documentation/*` — this file is LIVE snapshot only, no mechanic duplication.**
> **Control panel DONE. Tedarik panel DONE & POLISHED. Tenants GDZ/ADM. SAP group-by-EBELN. Permissions rebuilt 2026-09-04. Dashboards rebuilt 2026-09-06. Bilgilendirmeler DONE 2026-09-07. Email redesign DONE 2026-09-09.**

## 1. Latest (2026-11-11, newest first)
- **Tender files hidden from DList (2026-11-11):** `Document_files::tableList` now excludes parents `op-doc-ihale` / `op-doc-ihale-offer` / `op-doc-ihale-offer-item` / `op-doc-client-docs`, so tender spec + offer + shared-library files no longer show in `/coalpanel/documents` (or its export). Verified: dummy tender file → not in list; normal files unaffected (14,227 rows).
- **Permission model restructure (2026-11-11):** İhale now has exactly two codes — `per-041-03` **İhale Oluşturma / Yönetme** (create/edit tender, activate, complete+winner, review queue, offer/file statuses) and `per-041-03-01` **Teklif Oluşturma** (own offers + own library, requires bound client). `per-041-03-02` retired (removed from catalog + role templates). Ürün split: `per-09-01` read, `per-09-02` **Ürün Oluşturma/Düzenleme**, `per-09-03` **Kategori Oluşturma/Düzenleme** (new). `docPermCheck` updated (`op-doc-ihale` read `03,03-01`; category edit `09-03`). Controller gates: activate/complete/accept/reject/file-reject = `03`; tender-products + client-docs allow `03-01`; ownership + role detection now fall back to `users.role`/person clients for Bearer-token requests. Roles resynced (Tedarikçi lost `03`, keeps `03-01`; Admin/Ürün Kategori Yöneticisi gained `09-03`), all 264 users' permission entities rewritten + version bumped. İhale module tile enabled. Sidebar/router gates mirror the split (`03||03-01` for list, `03` for Yeni İhale/Teklifler, `03-01` for Teklif Ver). Verified over HTTP with reseller token: tender read 200, activate 403, accept 403, tender-products 200, own library 200, foreign library 403.
- **Offer file rejection (2026-11-11):** `offer-file-rejected` (Dosya Reddedildi) seeded. Admin opens a cell's Dosya İşlemleri → **Dosyayı Reddet** (reason required) → `POST /v1/offers/file-reject` writes an `op_id=1` file transaction, then auto-rejects the offer with `Dosya reddedildi: <note>`. `ClientDocsService::listing` now attaches `file.last_status`, so the tile turns red and the modal shows the reason on reload. Verified live (file last = offer-file-rejected, offer = offer-rejected, registry last_status attached); smoke data cleaned.
- **Offer status machine (2026-11-11):** `offer-sended` (birth) → `offer-rejected` (reason required) → resave flips back to `offer-sended`; `offer-accepted` completes the tender with that supplier as winner. New endpoints `POST /v1/offers/reject` `{id,note}` and `POST /v1/offers/accept` `{id}`; accept reuses `completeTenderWithWinner`. `IhOfferList` got a Durum pill (reason under rejected) + ✔/✖ actions and is now **admin-only (`per-041-03-02`)** — sidebar/guard updated so resellers can't see other suppliers' offers. `IhOffer` shows red reject banner with reason or green accepted banner. Verified live: sended → rejected → resend → sended → accept → offer-accepted + tender-complated + winner.
- **Tender status machine (2026-11-11):** `tender-created` (birth) → `tender-activated` → `tender-complated`. Seeded in `op-trans-op-doc-ihale` (`ClientDocsSeeder`, legacy `tr-tender-complated` retired, 4 existing tenders backfilled to `tender-created`). Birth map in `registerContent` now writes `tender-created` for `op-doc-ihale`. `setStatus` guards the chain. Offers are server-blocked unless the tender is **activated**; completed = locked. `POST /v1/tenders/activate`; `POST /v1/tenders/complete` now requires `winner_supplier_qnid` (+title), validates the winner has an offer, freezes winner entities (`winner_supplier_qnid/title`, `winner_offer_qnid`) + per-offer snapshot, then writes `tender-complated`. `IhList` actions are status-aware (🚀 Aktif Et / ✔ Tamamla with winner modal / Teklif Ver only when active) and show 🏆 winner under the Durum pill. `IhOffer` shows "İhale henüz aktif değil" and disables uploads/save until activated. Verified end-to-end (created→offer blocked; activated→offer OK; complete→winner+snapshot; cleanup left 4 tenders at created).
- **Shared client document library (2026-11-11):** files now belong to **client + product code + requirement + kVA**, not to a tender. New `op-doc-client-docs` registry (one per supplier) with slot tags `cdf_<sha1>**client_docs**slot_<sha1>`; stock file versioning per slot; `document_files.valid_until` migration; expiry blocks offer save. Endpoints: `GET /v1/client-docs/{supplier}`, `POST /v1/client-docs/slot`, `POST /v1/tenders/complete`. `IhOffer` uploads straight into the registry (expiry prompt, `Paylaşımlı` pill, `Geçmiş (n)` version modal), resolves live everywhere, and becomes read-only after completion. Tender completion writes `tr-tender-complated` + freezes a JSON `snapshot` entity into each offer (closed tenders keep the pre-lock versions). `IhList` gained Durum pill + ✔ Tamamla. Verified over HTTP (temp upload with expiry → slot → list → 2 versions → replacement; completion snapshot). Demand: `ClientDocsSeeder`.
- **Teklif Ver menu entry removed (2026-11-11):** sidebar İhale Yönetimi = İhaleler · Yeni İhale · Teklifler. The per-row **Teklif Ver** icon stays in `IhList` (gated `per-041-03-01`) and opens `IhOffer?ihale=<qnid>`; `Teklifler` list "Aç" also opens offers.
- **Demo tenders with multi-product rows (2026-11-11):** `TenderDemoSeeder.php` created `IH-2026-001` (3 kalem: 630×2, 1000×1, 1250×3), `IH-2026-002` (4 kalem: 50×6, 100×4, 160×2, 250×3), `IH-2026-003` (2 kalem: 400×5, 1600×1) through `registerContent` (real transactions/logs). Row keys carry unique first tokens so the Form engine renders every card. Verified `row_count` = UI card count = 3/4/2. Idempotent by `ihale_kodu`.
- **İhale + Teklif list pages (2026-11-11):** `IhList` (`/coalpanel/ihaleler`) and `IhOfferList` (`/coalpanel/ihaleler/teklifler`) built. Tender list: ajax PickleTable, `all` search, product count derived from `ihale_product_rows` row keys, spec pill, actions (Düzenle · Teklifleri Gör · Teklif Ver · Sil). Offers list: admin queue with per-tender filter via `?ihale=<qnid>` (`attr` filter), item count from new conditional `row_count` column in `Documents::tableList`. Sidebar now İhaleler · Yeni İhale · Teklifler · Teklif Ver; route guard `per-041-03`; `IhForm` saves back to the list. Verified end-to-end with a smoke tender/offer (row_count 2/2, since deleted). **Gap:** supplier shell still has no `/tedarikpanel/ihaleler*` route, so resellers can't open IhOffer from UI yet.
- **Catalogue + tender reset (2026-11-11):** all demo products (15), product categories (9) and tender docs (ihale + offer) hard-deleted with their EAV/files/transactions/logs. New catalogue via `TransformerCatalogSeeder.php`: category **33 kV Hermetik Dağıtım Transformatörü** (`TRF-33KV`) carrying the 14-row reference-image file template (Evrak No `0–6` Tip Test + `8–14` Diğer Belgeler; rows 9/10/12 = `kva=[]` spanning, rest explicit 9 kVA) + **9 products** `TRF-33-050 … TRF-33-1600` (ST, linked `category_qnid`/`category_title`). Category qnid `edd5210c-123a-49c5-9bfd-e0296caeddc0`. Verified via `tableList`: 1 category, 9 products; ihale/offer = 0. Details `panel/documentation/modules/tender.md §7`.
- **Product categories LIVE:** `op-doc-product-category(-form)` + `per-09/09-01/09-02` + `immutable-product-manager` role (admin assigns via user form, needs `op-pert-admin` person type) + `PCList/PCForm` (`/coalpanel/product-categories`, own Ürün menu) + export branch. Seeded via `ProductCategorySeeder` + JSON role files + `SysRoleTemplateSeeder`. E2E: admin create+list, supplier 403, role can manage but not orders. Fixed hardcoded DEV_ADMIN list in `AuthController::getPermissions` (was missing per-09).
- **Ürünler LIVE:** `op-doc-product(-form)` (Ürün Kodu + Kısa Ad + Birim ST/KG/M + Kategori) under same per-09 domain + `immutable-product-manager` role. Category picker = server-paginated ajax PickleTable modal (big-data safe), stores `category_qnid` + denormalized `category_title`. `PList/PForm` (`/coalpanel/products`, Ürün menu) + `products` export branch. E2E: modal pick + save + list, reseller 403.
- **Kategori dosya şablonları LIVE:** repeatable `category_files` rows on category form (Evrak No + Standart/Belge Adı + Dosya Grubu + Dosya Tipleri preset + **Geçerli Güçler tag-input typeahead** like the reference — type to filter, Enter/click to pill, ×/Backspace to remove, empty = all powers; new tag-input engine renderer fed live from Güç Tanımları). Pure pointer definitions, no file bytes — future module collects uploads against them. E2E: typeahead pick + save + readback.
- **Güç Tanımları (kVA) LIVE:** `op-doc-kva(-form)` + `KvList/KvForm` (`/coalpanel/kva`, Ürün menu) + export. Rows are dimension entries `{Başlık:'KVA', Değer:'1600'}` — 9 standards seeded, users add more from UI. These feed the category file-def multi-checkboxes (new `checks` engine field, dropdown shows "KVA 1600"). E2E: custom "2000" created. (Güç/kVA varyantları removed per Master — not product-related.)
- **Legacy import LIVE:** old TedarikApp dump (`livetdedarik_2026-11-10`, 144MB) imported via new `orders:import-legacy` (`ImportLegacyCommand.php`, temp DB `livetdedarik_src`): 10,591 orders + 44,042 items + 154,066 serials + 36,160 files (version chains rebuilt) + 112,444 transactions (incl 19,900 line-inspections) + 261 users (role-mapped, kbbozat kept reseller) + 89,120 logs + 121 clients + `original_quantity` backfill (23,089 lines). Skipped: 1,302 factory files (system comes later), 12 orphan serials/files (parent deleted), 3,781 logs of deleted users. Open: file BYTES not in dump (clone `public/documents/` at go-live or downloads 404), statuses verified via `getFormData`.
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
