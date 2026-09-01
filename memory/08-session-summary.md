# INGSYS — Session Handoff Summary (2026-09-02)

> **Read this first (2 min).** Then `05-order-system-state.md` (LIVE state), `06-roadmap-next.md` (what's next).
> Consolidates the 8 memory docs — deep-dives stay in their files.

---

## 1. What This Is

A **generic EAV document engine** (Laravel 12 / PHP 8.2 / PostgreSQL / Vue 3 SPA) now running as an **Order Management System** on branch `tedarikPanel`. The coal app (KomurTedarik) is legacy weight. New doc-types/apps = config + `Form.vue` schema, **no migrations** (see `03-app-factory-guide.md`).

## 2. Live State

| Thing | Value |
|---|---|
| Branch | `tedarikPanel` (order system) — `CoalSYS` has aligned file-versioning (`073b8de`) |
| DB | `tedarikNewApp` @ `127.0.0.1:5431` (docker `tedarikNewApp`, image `61d0571c2f7b`) |
| Panel | `php artisan serve --host=127.0.0.1 --port=8000` |
| Login | `kadir@kontent.com.tr / Kadir412.` → 2FA `111111` |
| Data | 8 orders / 21 items / 8 clients / ~files (live, grows) / 0 serials / 39+ trans (fresh sync 2026-09-01, `/tmp/sap_fresh_payload.json` 21 rows → 8 EBELN, mixed ST/KG/M, all ST<300; 2026-09-02 fixes on top) |
| Resync | `cp /tmp/sap_payload.json /tmp/sap_fresh_payload.json && php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh` + manual wipe serials/files/storage (see `05` §9) |

## 3. Order System In One Screen

- **Doc types:** `op-doc-order` (header) · `op-doc-order-item` (Kalem, `parent_id=order`) · `op-doc-order-serial` (Seri, `parent_id=item`) · `op-doc-client` (Cari).
- **Order↔Client link = `LIFNR` string** (`order.spec_code = client.lifnr`), keep leading zeros. No FK.
- **Transfers are NOT a type:** partial split = clone `op-doc-order` with `transfer_no=EBELN-X`. `op-doc-transfer` seeded but unused.
- **Status:** `created → transfer_sent → ready_for_shipment → approved/rejected` + `files_rejected`.
- **Send happens on SAVE:** `PUT /v1/document/{id}` + `transfer_mode` (+ `selected_items` / `item_serials`) → `DocumentServiceProvider::processOrderTransfer`.

## 4. Master's Rules (do not violate)

1. **Once partitioned → always Parçalı.** `hasActivePartitions(baseNo)` blocks Tek Seferde (backend guard + `OForm::checkHasPartitions` disables the radio). Removing ALL clones (`status=0`) unlocks Tek Seferde again.
2. **Remove a clone → quantity restored.** `Parçayı Sil` (clone) / `İptal Et` (main) via `cancelOrder`/`removeContent` → `incrementItemQuantity`/`restoreQuantitiesForClone` adds `split_amount` back (ST int / KG/M float), deactivates clone serials+items.
3. **Quantities:** ST = integer, KG = float, M = float. **Serials:** KG/M **required** (default `-`, production date auto-filled from order `created_at`, not required), ST<300 **optional** (checkbox, max 300 rows), ST≥300 **none**.
4. **File versioning FINAL design** — **do NOT change without approval**: every upload = NEW `sys_con_entities` row (duplicate rows = version history); activeness = `document_files.status` (1=current); `replaced_id` points BACKWARD (`new.replaced_id = old`). Reference: `file-versioning-system.md` (repo root) + `panel/docs/04-dosya-yukleme-ve-surumleme.md`.

## 5. Recent Wins (details in `05` §4/§6/§10)

- Excel serial upload (client-side SheetJS) + KG/M serial→Böl input sync
- Malzeme Kabul PDF print/reprint (`files_rejected` reprint, `imalatci_firma_adi` inside signature box)
- **Malzeme Cins-Miktar Kabul Formu** (2026-09-01) — DONE: blade "SEVK EDİLECEK MALZEMENİN CİNSİ VE MİKTARI", header 4-row vertical table, items flattened by serials, controller with `ctitle` company, 2 purple buttons
- **Cins-Miktar serial fix** (2026-09-01) — reads frontend serial state instead of DB-only, works for new partitions
- **Item file uploads** (2026-09-01) — Test Dökümanı (rejectable/acceptable) + Ürün Görselleri (multi, no status) per order item, collapsible section in OrderItemTable, saved via separate item PUT after main order save
- **Item file fixes (2026-09-01):** (1) `fetchItemFiles` read `conn.files` which backend never fills — files live as JSON in `conn.entities` → detail showed nothing; now parses entities + legacy fallback, deduped. (2) Every image shared ONE entity_tag → backend treated each new image as a replacement (old `status=0`); now unique tag `item_images_file**item_images**{rowId}**img-{uploadId}` per image → multi-upload works. (3) **Partial transfer race:** `saveItemFiles` ran AFTER the order PUT → clone created before item files were finalized → files stayed on original items; now item files save FIRST so `moveOrderFilesToDocument` relinks them to the clone. (4) `duplicateOrderItem` no longer copies file-JSON as junk scalar entities. (5) **Test doc single slot:** upload button hidden when existing file present; remove emits `removedFiles` → backend deactivates via `removedData`; new upload replaces existing (file key includes existing ID). (6) **Entity_tag on move:** `moveOrderFilesToDocument` now updates `entity_tag` via `str_replace` when relinking files to clone — old tag with old connId broke replacement detection on the clone. Data repair: moved item 548's files/entities → clone item 553.
- File replacement v2 (entity-rows + backward `replaced_id`), `syncOrderStatusFromFiles` fires from `registerContent`, status `json_agg ORDER BY t.id`
- DList file grouping fix — `group_key` SQL column (COALESCE same-conn `order_no` for order-level files → clone's number, parent `order_no` for item files → clone's number via `d.parent_id`) so all files group under the correct clone order number instead of item names
- DList cleanup — product images excluded from files listing (not actionable), test documents show product name in İlişki column
- **Product image removal fix (2026-09-01 late):** `OrderItemTable.fetchItemFiles` stores `entity_tag`/`connId`, `removeExistingImageFile` tracks `_removedExistingFiles` with key, `OForm.saveItemFiles` generic `removedData` — images now deactivate (`document_files.status=0`) on save
- **File preview for item docs (2026-09-01 late):** `previewExistingFile` iframe modal for both test docs (pdf/xls) and product images (encrypted name broke old `\.pdf$` check) + `previewLocalImage` pdf→iframe/xls→download/image→imageUrl; chips clickable + eye button
- **Thumbnail grid + gallery (2026-09-01 late):** Ürün Görselleri `oic-image-grid` 84×84 `12px` `src="/order-file/{qnid}"` + `URL.createObjectURL` + `onThumbError` fallback + `beforeUnmount` revoke; gallery `teleport to body` `oic-gallery-overlay` `rgba(15,23,42,0.88) blur(6px)` `48px` nav `counter` `stage 70vh` thumb strip `64px` `Esc/←/→`
- **Split overlay icons (2026-09-01 late):** Was pill bottom bar → `oic-split` 50/50 `eye 22px` `ki-eye` left `rgba(79,70,229,0.82)` + `ki-cross-circle 22px` right `rgba(225,29,72,0.84)` `blur(3px)` split line
- **Lock for images (2026-09-01 late):** `OrderItemTable readonly` (`OForm :readonly="isLocked"`), guards on all file triggers/removes, `Görsel Ekle`/`Sil` hidden when locked, `oic-split left.full` preview full-width, lock hint `ki-lock-2`
- **Test doc non-removable (2026-09-01 late):** Existing `accepted/pending` no X (only preview), rejected `Yeni Test Dökümanı Yükle` always visible even when `isLocked` so replace works in `files_rejected`; insert only on save, replace via `existingId`
- **Loader for Kabul/Cins (2026-09-01 late):** `OForm printingKabul/printingCins` + `Swal PDF oluşturuluyor... ki-loading spin` covering clone suffix + export fetch, `Swal.close()` + `disabled` `Oluşturuluyor...` `opacity 0.72` `@keyframes spin`
- **Rejected upload + still-rejected toast (2026-09-02):** `OrderItemTable.isTestRejected()` unlocks `Yeni Test Yükle`/`removeTestFile` when `files_rejected` even if `isLocked`; `OForm.submitForm` now shows `Kaydedildi — hala reddedilen dosyalar var: Malzeme Kabul, Test: ...` when `lastOp==files_rejected` (from `detail.formFormat` + `itemTable.existingTestFiles`), otherwise normal toast. Prevents "stuck" confusion when multiple rejected slots remain.
- **DList grouping visual + header cache (2026-09-02):** `DList.groupFormatter` duplicate `›` + shrink-wrapped flex pill left + `border-collapse:separate` broke head search/order. Fixed to inner `div.dlist-group__inner` `display:flex; width:100%; gradient #eef2ff; border-left:3px #6366f1; radius12` + `ki-folder` + `count` pill `margin-left:auto`; `pickletable/script.js:275` now clears `groupHeaderElements={}` on `isFiltering`/`isOrdering` so header not missing after filter/sort. Full-length header, head search/order intact.
- Partition lock + quantity restore, DList grouping/date fixes, perf pass on OrderItemTable/OForm/OList

## 6. Pending / Next (see `06`)

- **Front panel** (client skin) — backend transfer+serial flow READY, design awaited (`memory/idea.md`)
- **Dashboard** — still coal, needs order metrics
- **Malzeme Cins-Miktar Kabul Formu** — DONE (2026-09-01)
- Cleanup: drop `op-doc-transfer` type, legacy coal pages, `permission_version` file-cache bug

## 7. Resume In 5 Commands

```bash
docker start tedarikNewApp   # or docker run ... -p 5431:5432 postgres:latest
cd panel && php artisan migrate:status   # expect 23 done
cd panel && npm run build                # after any Vue/edit
php artisan serve --host=127.0.0.1 --port=8000
php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh   # reset data
```

## 8. Landmines

- **PHP `??` ≠ `?:`** — `??` doesn't fall through on empty string `''`. Use `?:` / `!empty()` for frontend values.
- **Route ordering** — specific export routes MUST precede `{model}` wildcard in `api.php`.
- **`$key` clobbering** — never reuse `$key` as a merge-loop variable (`DocumentController` PUT); use `$fkey`.
- **Security debt:** `DEV_ADMIN=111111` backdoor, public `resetusercradentals`, CSRF off, `/order-file` IDOR, hardcoded `pickle` key, plaintext 2FA files, files served in webroot.
- **Lock semantics:** `ready_for_shipment` = FULL lock; `files_rejected` keeps files editable + reprint.
- After any `Form.vue`/router/Sidebar/OForm/OList/OrderItemTable edit → `npm run build`.

## 9. Memory File Map

| File | Contents |
|------|----------|
| `00` | architecture brain-dump: stack, entry points, EAV, auth, permissions, gotchas |
| `01` | Form.vue schema-driven engine deep-dive |
| `02` | EAV tables, dictionary, read/write summary |
| `03` | how to build a new doc-type app (recipe) |
| `04` | backend/frontend patterns, request flow, pitfalls |
| `05` | **LIVE order-system state** — read after this file |
| `06` | roadmap: next steps + decision tree |
| `07` | SAP sync command reference |
| `idea` | original spec (front panel, partitioning, serials) |