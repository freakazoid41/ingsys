# Roadmap — Plans For Next Sessions

> **Front panel is the crown jewel — you said you'll give design (`memory/idea.md:31`). Until then, here's the ordered attack plan so any LLM can pick up without asking you twice.**

## 1. Immediate Next (Choose 1)

### Option A: Front Panel Design Arrives → Build Client Flow (Recommended)
You drop Figma/HTML for `front panel (new)` — we build:

1. **Route & Layout:** New `FrontPanel.vue` (client skin, no coal), `router/index.js` → `/front/orders` + `/front/orders/:id` + `/front/transfers` (client view). Separate from `/coalpanel` admin shell.
2. **Order Detail Client View:** **backend already handles this** — order header (readOnly) + item table + `order_desc` + `imalatci_firma_adi` + `transfer_kabul`/`transfer_cins` files, and SAVE triggers `processOrderTransfer` (`at_once`/`partial` + `selected_items` + `item_serials`).
3. **Serial entry in front panel:** Must support same serial flow — KG/M required, ST optional (< 300), flatpickr month picker.
4. **File upload wiring:** Reuse `Form.vue` schemas + `pickle.js` temp-upload.
5. **Rejected files:** client sees `files_rejected` + can re-upload + re-save → `transfer_sent`.

**You need to give:** HTML/CSS or Figma, blade choice, host.

### Option B: No Design Yet → Dashboard Rebuild
1. Replace coal `topstats/monthlyoffers` with order queries
2. Header bell for order notifications
3. Build report cards (pending transfers, awaiting files, approved today)

### Option C: Skin It
Replace `Kömür Tedarik` branding → `Tedarik Yönetim Sistemi` (already done, but SVGs still CATES/YATAGAN).

## 2. Recently Completed (2026-08-28)

### ✅ Quantity Types & Split System
- `OrderItemTable.vue`: inline split inputs, quantity type awareness (ST=int, KG/M=float)
- `DocumentServiceProvider`: `decrementItemQuantity`, `storeOriginalQuantity`, `duplicateOrderItem` with `$splitAmount`
- EAV entities: `original_quantity`, `split_from_qnid`, `split_amount`
- Row display: `~~2400~~ → 1900 ST` strikethrough before/after

### ✅ Serial Number & Production Date System
- New doc type: `op-doc-order-serial` (parent_id=item.id)
- Entities: `serial_no`, `production_date` (YYYY-MM-01), `quantity`, `unit`
- Rules: KG/M required (at least 1, default `-`), ST < 300 optional (checkbox), ST >= 300 no serial
- Flatpickr month picker (Turkish locale, monthSelect plugin)
- Both at_once and partial modes supported
- Serial UI: collapse button, scrollable per-item, summary badge on main row
- Existing serial display: collapsible read-only section (purple bg, disabled inputs), **collapsed by default**
- `fetchSerialsForItems()` loads serials for items with `has_serials=1`
- `serialViewCollapsed` state (separate from `serialCollapsed` for entry UI)
- `formatSerialDate()`: `YYYY-MM-01` → `MM.YYYY` display

### ✅ Clone Order Link
- `OList.vue`: "den ayrıldı" link on clone orders
- `OForm.vue`: "Kaynak Sipariş" banner with navigation

### ✅ UI Improvements
- Font sizes bumped across OrderItemTable + OForm (row padding 14px 18px, min-height 64px, code 0.92rem, title 0.95rem, qty 0.88rem)
- Transfer card, locked card, clone origin card all enlarged

## 2.5 Recently Completed (2026-08-29)

### ✅ Orders are SAP-only
- Removed "Sipariş Oluştur" from Sidebar — orders come from SAP only
- `OForm` no-id redirects to `OrderList` (no blank create form)

### ✅ CSP fix
- `CspMiddleware` + `'unsafe-eval'` in `script-src` (sweetalert2 needs `new Function()` to parse Swal HTML input values)
- Local `IS_TEST=true` disables CSP header; matters for production

### ✅ Perf / code-quality pass (no mechanic/validation changes)
- OrderItemTable: flatpickr init only new inputs, 150ms serial debounce, `itemsMap` O(1) lookup, `quantityChanged` helper, single-pass cleanup, `min="1"`
- OForm: cached `parsedStatus`, shared `orderFormEntities`, async mounted, `Object.entries`, removed dead `wTrans`
- OList: `_attrsParsed` guard, CSS ellipsis, cleaned dead setup returns
- Full details in `memory/05 §6`

## 2.6 Recently Completed (2026-08-31)

### ✅ Excel Serial Upload
- `xlsx` (SheetJS) package added to `package.json`
- `OrderItemTable.vue`: client-side Excel read for serial number entry
- 4 "Excel'den Yükle" buttons + 4 "Şablon" download buttons across all serial sections
- Excel format: SRLCODE, SRLDATE, QUANTITY columns
- `downloadExcelTemplate()` generates template xlsx with headers + 3 example rows
- `parseSerialExcel(item, file)`: SheetJS read → map → replace serials for item
- ST: quantity forced to1, serial checkbox auto-enabled
- ST mismatch: Swal confirm dialog, on confirm syncs `splitAmounts[item.id] = parsed.length`
- `_excelUploadSplitAmt` captures split at click time (async timing fix)
- `_excelUploadTime` flag (1000ms) prevents `rebuildSerials` overwrite
- `serialCollapsed[item.id] = false` ensures area stays expanded after upload
- `normalizeExcelDate()`: handles string `YYYY-MM-DD` and Excel serial number dates → `YYYY-MM-01`

### ✅ Malzeme Kabul Formu PDF
- Print button "Malzeme Kabul Formu Yazdır" (green, icon) in OForm.vue transfer card
- `ExportController::malzemeKabul()` at `POST /v1/export/malzeme-kabul`
- Blade template: `exports/malzeme-kabul.blade.php` — A4 portrait, title underlined, table with 2px borders, signature grid
- Turkish HTML entities in blade (`&#304;` for İ, `&#305;` for ı, etc.) — dompdf renders correctly
- Route MUST be before `{model}` wildcard in `api.php` (line 42, before line 45)
- DB entities as PRIMARY source for `imalatci_firma_adi` (not request) — PHP `??` doesn't fall through on empty string
- Validates: `imalatci_firma_adi` required (Swal error modal with "Tamam" close)
- Items: all for at_once, only selected for partial
- Clone order_no suffix calculated by querying existing clones via `POST /v1/table/documents`
- buying_no (SUBMI) stays original — NO clone suffix
- User flow: fills form → clicks Print → PDF downloads → prints on paper → signs with pen → scans → uploads signed PDF to transfer_kabul file field → saves order

### ✅ KG/M Serial → Böl Input Sync
- `syncSplitFromSerials()` in OrderItemTable.vue auto-updates `splitAmounts[item.id]` from serial quantity sum
- `_syncingSplit` flag prevents infinite loop between `splitAmounts` ↔ `serials` watchers
- Called from: `addSerialRow`, `removeSerialRow`, serial quantity change (debounced 500ms), Excel upload
- Only fires when sum > 0 (prevents clearing input when all serials removed)

### ✅ Malzeme Kabul Formu Fix + Partition Rules (2026-08-31 late)
- **PDF fix:** `imalatci_firma_adi` moved from floating block **into** `signature-grid` second row (`height:80px`), single occurrence. `ExportController::malzemeKabul` `trim((string)...)` fix, `OForm::getFieldValue()` handles compound names. `view:clear` needed.
- **Partition lock:** `hasActivePartitions(baseNo,excludeId)` counts active `EBELN-X` (`d.status=1`). `processOrderTransfer` blocks `at_once` if partitioned. `OForm::checkHasPartitions()` queries `POST /v1/table/documents all=baseNo+'-'`, disables `Tek Seferde` radio (grey + banner), forces `partial`. All clones `status=0` → unlocks.
- **Restore on delete:** `incrementItemQuantity()`, `restoreQuantitiesForClone()`, `restoreQuantityForSingleCloneItem()` in `DocumentServiceProvider`. Hooks in `removeContent()` + `cancelOrder()` — clone `EBELN-X` delete → `split_amount` added back to main (`ST` int, `KG/M` float), serials/items deactivated. `removeContent` UserLog `user_id ?? 0` fix.
- **Parçayı Sil label:** `OForm.vue` locked-card + `OList.vue` list button now show `Parçayı Sil` for clones (`isCloneOrder` / `/-\d+$/`), `İptal Et` for mains. Swal titles/notes conditional.
- **No reload on delete:** `OList.vue` `cancelOrder` now `this.table.deleteRow(qnid)` (was `window.location.reload()`, `removeRow` is wrong → `pickletable/assets/script.js:680 deleteRow`).

### ✅ Serial UI Improvements (2026-08-31 late)
- **At_once collapse buttons:** Both ST and KG/M at_once serial sections now have Daralt/Genişlet toggle (was missing, caused screen flooding with many items)
- **Partial layout matches at_once:** Header shows "Toplam: X unit" + "Satır Ekle" moved to header row for KG/M (was at bottom)
- **Visual color split:** at_once serial bg = light gray `#f8fafc`, partial serial bg = warm yellow/amber gradient `#fffbeb → #fef3c7` via `.oic-serial-partial` CSS class
- **Daralt far right:** `margin-left:auto` on Daralt span in all 4 serial sections so button sits at far right, not crowded next to Satır Ekle

### ✅ Fresh SAP Sync Data (2026-08-31 late)
- New `/tmp/sap_fresh_payload.json` — 23 items across 8 orders, **all ST < 300** (serial checkbox available), mixed ST/KG/M in every order
- `php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh` wipes and recreates

### ✅ Malzeme Kabul Reprint from files_rejected (2026-08-31 late)
- `canPrintKabul` computed = `canSend || files_rejected` — button shows in locked card when files rejected
- `printMalzemeKabul` uses `effectiveTransferMode` (form state if canSend, else `storedTransferMode` from DB)
- `getFieldValue()` uses `orderEntities` directly when form is locked (DB-only mode)
- Items from `itemTable.items` (all items used when selectedItems empty in locked state)
- **Reprint order_no fix:** If current order already has suffix (`/\-\d+$/`), uses it as-is. Only increments for NEW partial transfers from base order

### ✅ Transfer Mode on Clone + DList Fixes (2026-08-31 late)
- **Backend:** `saveTransferModeEntity($clone, $mode)` added for partial mode — clones now store `transfer_mode` in EAV
- **Frontend fallback:** `storedTransferMode` falls back to `'partial' for clone orders if entity missing (covers old clones)
- **DList.vue grouping:** `groupFormatter` extracts `order_no` from `relation_detail` entities (not just `title`). `rowFormatter` sets raw field name from `entity_tag` as top-level key
- **DList.vue dates:** `rowFormatter` pre-formats `created_at` → `_created_at_fmt` (DD/MM/YYYY HH:mm). Column uses `key: '_created_at_fmt'` with `type: 'string'` — no custom formatter, PickleTable renders pre-formatted value directly

### ✅ KG/M Auto Serial Date (2026-08-31 late)
- `OrderItemTable.vue`: new `orderDate` prop + `parsedOrderDate` computed (parses SAP `d/m/Y` or `Y-m-d` → `YYYY-MM-01`)
- `ensureAtOnceSerials`, `rebuildSerialsForItem`, `addSerialRow` all pre-fill KG/M `production_date` from order date
- `production_date` validation removed for KG/M (not required — auto-filled but user can clear/change)
- `OForm.vue`: passes `orderEntities.created_at` as `:orderDate` to `OrderItemTable`
- `validatePartial` and `validateAtOnceSerials` removed `production_date` check for KG/M

### ✅ Order Status: files_rejected → transfer_sent (2026-08-31)
- **Root cause:** Re-uploading a rejected file went through `registerContent` (form save) → file replacement, but `syncOrderStatusFromFiles` only fired from `documentFileStatus` (admin accept/reject), never from registerContent. Replaced file's lastOp = `doc_file_refreshed` (neither rejected nor accepted) → no branch matched → status stuck at `files_rejected`.
- **Fix 1:** `registerContent` now calls `syncOrderStatusFromFiles()` after file replacement (tracked via `$lastFileEntity`).
- **Fix 2:** `syncOrderStatusFromFiles` gained a branch: no rejected + not all accepted (waiting/refreshed) → `doc_trans_order_transfer_sent` ("Dosyalar Kontrol Ediliyor").
- **Files:** `DocumentServiceProvider.php` — `registerContent` (lines 144-150, 233, 273, 282-294) + `syncOrderStatusFromFiles` (lines 1104-1112).
- **Verified live:** order 343 stuck on `files_rejected` → ran sync → "Dosyalar Kontrol Ediliyor".

### ✅ File Replacement v2: Entity + replaced_id (2026-08-31)
- **Entity bug:** `$check` in `registerContent` queried `table_tag='sys_con_ops'` but file entities live under `table_tag='document_files'` → never matched → duplicate entity rows on every upload → `$oldFileEntity->first()` (no order) returned OLDEST row → `$existingFileId` always the FIRST file → every replacement deactivated first file, not latest.
- **Entity fix (FINAL per Master):** every upload still creates a NEW entity row (duplicate rows = version history, original design kept). Activeness = **`document_files.status`** (NOT an entity column — Master's convention). `$oldFileEntity` finds the active entity via `whereIn(entity_value, id::text from document_files where status=1)` + `orderByDesc('id')`. `getFormData` join filters file entities via `EXISTS(document_files status=1)`. `removedData` skips entities whose file is inactive. `old_versions` stays the ORIGINAL entity-tag query (works again with duplicate rows).
- **replaced_id direction:** Changed from `old.replaced_id = new` (forward, wrong) to `new.replaced_id = old` (backward = "one version before"). Applied in both `finalizeTempFile` and `addFileToDb` in `DocumentHelpers.php`.
- **Files:** `DocumentServiceProvider.php` (lines 233, 273) + `DocumentHelpers.php` (lines 554-567 finalizeTempFile, 798-805 addFileToDb).
- **Porting doc:** `file-versioning-system.md` at repo root (Turkish, sections 1-6) — taşınabilir referans: mimari, schema, doğru kod desenleri, geçmiş hatalar, port checklist, doğrulama SQL. (`file-replacement-fix.md` yerine geçti.)
- **CoalSYS branch:** Same entity + replaced_id fixes applied to CoalSYS branch. Committed there too.
- **Data repair:** Order 343 chain fixed: `86→88→89→90→91` (was broken: 86→91, 88/89/90 orphans).
- **Ghost cleanup:** orphan file 92 (`relation='-'`, stale pre-fix artifact) removed during fresh sync.

### ✅ Fresh SAP Sync (2026-08-31)
- Wiped 31 old docs, 50 document_files (+ temp on disk), 2 orphan file entities.
- `php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh`
- Result: 8 orders, 23 items, 8 clients, 0 files, 163 transactions. Clean slate.

### ✅ Transfer-send pre-emption fix (2026-08-31)
- **Bug:** saving an at_once send WITH files caused `registerContent`'s new sync call to flip the order to `transfer_sent` BEFORE `processOrderTransfer` ran → its "created only" guard rejected the send silently → order never actually sent (no serials), not locked.
- **Fix:** (1) `registerContent` skips the sync when `transfer_mode` is in the payload; (2) `syncOrderStatusFromFiles`'s `transfer_sent` recovery branch only fires when current status is `files_rejected` (never pre-empts a fresh send).

### ✅ old_versions kept ORIGINAL entity-tag query (2026-08-31)
- **Bug:** `Document_files::tableList` `old_versions` subquery matched files by shared `entity_tag` on entity rows — worked only because of the duplicate-entity bug. An in-place entity update (single row) made old versions vanish.
- **Final decision (Master):** duplicate entity rows ARE the version history → `old_versions` stays the ORIGINAL entity-tag-based query (no CTE). Activeness = `document_files.status`. Verified live: 3 uploads → 3 entity rows → old_versions returns 3.

### ✅ Status array ORDER BY fix (2026-08-31)
- **Bug:** `getFormData` document status `json_agg` had NO `ORDER BY` → array order non-deterministic → frontend `parsedStatus[last]` picked the WRONG current status (e.g. `transfer_sent` instead of `files_rejected`) → `isFilesLocked` true → rejected file inputs DISABLED.
- **Fix:** `ORDER BY t.id` inside `json_agg` (oldest→newest, last = current). List query (`Documents::tableList:83`) already orders correctly. See `memory/05 §10`.

## 3. Pending — Malzeme Cins-Miktar Kabul Formu
- Second PDF form for order transfer (PENDING)
- Similar flow to Malzeme Kabul Formu but with different layout/data
- Will need its own blade template + backend endpoint + print button

## 4. Tech Debt

- **Parent link:** `Documents::tableList:102` still `where parent_type_id=0` — items visible in main lists
- **Security:** `DEV_ADMIN 111111`, `resetusercradentals` public, `CSRF off`, `decryptFile IDOR`, `pickle hardcoded`
- **Old containers:** `B2X` still exists stopped on `5431`
- **File replacement:** FIXED, do NOT change without approval
- **`syncOrderStatusFromFiles`** — FIXED 2026-08-31: fires from `registerContent` (file replacement) too; waiting/refreshed files → `transfer_sent` "Dosyalar Kontrol Ediliyor". See `memory/05 §10`.
- **Route ordering** — Export routes MUST be before `{model}` wildcard or they never match (Laravel matches first)
- **PHP `??` vs `?:`** — `??` doesn't fall through on empty string `''`. Always use `?:` or `!empty()` for frontend values

## 5. How Future LLM Should Resume

1. Read `memory/00-core-overview.md` + `05-order-system-state.md` (current snapshot) + `06-roadmap-next.md` (this file) + `01-form-engine.md`.
2. Check `docker ps -a | grep tedarikNewApp`, `panel/.env: DB_DATABASE`, `php artisan migrate:status`.
3. Key methods in `DocumentServiceProvider.php`:
   - `processOrderTransfer($qnid, $mode, $selectedItems, $itemSerials)` — handles both partial clone + at_once serials
   - `duplicateOrderItem($itemQnid, $newParentId, $splitAmount)` — clone with qty override
   - `decrementItemQuantity($itemQnid, $splitAmount)` — reduce original qty
   - `createSerialEntries($itemId, $serials)` — create serial docs
   - `setHasSerialsFlag($itemId)` — mark item has serials
4. After any `Form.vue`/`router`/`Sidebar`/`OForm`/`OList`/`OrderItemTable.vue` edit → `npm run build` in `panel/` → test.
5. Always update `memory/05` after major change.

## 6. Decision Tree For Master

- **"Front design ready" → build client front-panel skin**
- **"Dashboard" → rebuild ReportServiceProvider order stats**
- **"Skin it" → rebrand coal → tedarik**
- **"New doc type" → follow `memory/03-app-factory-guide.md` pattern (sys_options + Form.vue + pages + permissions)**
