# Order Management System — Current State (2026-09-02 late)

> **This is the living snapshot. If you are LLM in a future session, read this first after `00-core-overview.md`.**
> **Control panel conversion DONE. Public Tedarik Panel DONE & POLISHED (`/tedarik` → `/tedarikpanel` card 1360px, tabs 64px protruding). Tenants renamed CATES/YATAGAN → GDZ/ADM (2026-09-02). SAP cron group-by-EBELN defined.**
> **⚠️ KEY CORRECTION (Master): Transfers are NOT a separate doc type — they ARE `op-doc-order` cloned with a new number (`EBELN-X`) only when partially split. `op-doc-transfer` + `op-doc-transfer-form` + `doc_trans_transfer_*` (4 keys) + `op-trans-op-doc-transfer` group PURGED 2026-09-02 (6 `sys_options` rows deleted, code cleaned — see Files Changed).**
> **Fresh SAP data (2026-09-02 08:20): 8 orders, 21 items (SAP `/tmp/sap_payload.json` 21 rows → 8 EBELN), 8 clients, 0 files, 0 serials, 37 trans — re-seeded after `migrate:fresh` GDZ (`php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh`). `grp_code=GDZ`×8.**
> **2026-09-02 fixes: `OrderItemTable` rejected unlock + `OForm` still-rejected toast + `DList` grouping header full-length + `pickletable` header-cache + `DList` popup fix + `Document_files` join/old_versions. 2026-09-02 late: Tedarik login `/tedarik` orange 560×840 140px Gdz, unified orange 2FA (`loginSms` single file), `GDZ/ADM` rename + DB re-seed, `public/index.php` `adm ? ADM : GDZ`. 2026-09-02 late polished: TedarikPanel 6 tabs 64×12 protruding -52 (38px outside, 10px gap), logo 82px + label, menu flex1 centered, OList card-rows + PickleTable overrides, `pickletable/script.js` height auto guard. 2026-09-02 late: Module Switcher `Modüller` above Çıkış (Sidebar+TedarikPanel) → modal `Yönetim|Tedarik` `/coalpanel|/tedarikpanel`, Tedarik detailed search 3×3 hover `absolute top:52 z40` (9 fields, Şirket=client list `op-doc-client`/`lifnr`, Tedarikçi=users with client code) + `Documents::tableList` 9 keys (`stok_kodu/siparis_kodu/alim_kodu/seri_no/uretim_tarihi/sirket/tedarikci/onay_durumu/tarih_araligi`), shared pages `OList/OForm` via `isTedarik` keep-shared decision. 2026-09-02 late filter: `Filtreler` dropdown `teleport to body` `fixed` (9 radios + `Şirkete Göre Arama` → client table modal `720px` `8` rows lazy `200` `PickleTable` `flatpickr range` `Y-m-d` → `tarih_araligi` `i.created_at` + `to_date(entity_value)`), `Detaylı` twin inputs → same modal `single`, `OList.vue` `persons` fetch **killed** (was `POST /api/v1/table/persons` on mount), `Documents.php` `alim/siparis` ordering + `sirket/transactions` multi `IN` + `tarih_araligi` entity `to_date`. **FIX 2026-09-02 late: Şirkete Göre Ara empty → `handleDetayliChoice sirkete_gore` was `showClientModal=true` without `buildClientTable` → modal empty `Yükleniyor`; fixed to `openClientModal('multi')` + instant hardFallback in `openClientModal` + `PickleTable data: localData` scope fix → `this.modalClients.slice()`. Detaylı `Filtrele/Sıfırla` now auto-close `showDetailed=false`.***

## 1. What Changed Since Coal

**Coal (KomurTedarik) → Order Management System** on same EAV skeleton. No migration for business fields — only dictionary + Form.vue + Vue pages.

| Layer | Coal | Order System Now |
|-------|------|------------------|
| **DB** | `b2x` on `5431` | **`tedarikNewApp`** on `5431` `61d0571c2f7b` `tedarikNewApp` Up, `127.0.0.1:5431` (`panel/.env:28`) — 23 migrations, GDZ (ex CATES) default `grp_code`, `sys_options` `GDZ` `op-apt-types`, `gdz.jpg/adm.jpg` + `GDZ.svg/ADM.svg` from `gdzLogo.svg/admLogo.svg` |
| **Doc types** `sys_options.group_key=op-doc` | `op-doc-request\|offer\|client\|flat` | **`op-doc-order` (Sipariş header), `op-doc-order-item` (Kalem, `parent_id=order.id`), `op-doc-order-serial` (Seri, `parent_id=item.id`), `op-doc-client` (Cari)**. `op-doc-transfer` + `transfer-form` + `transfer_*` statuses **PURGED 2026-09-02** (clones `EBELN-X` remain `op-doc-order`). `OrderSystemSeeder.php` |
| **Forms** `op-doc-forms` | 5: `request/offer/client/user/flat` | **`op-doc-order-form`** (`order_no/buying_no/spec_code/sys_code/ctitle/created_at` readOnly + `order_desc` textarea rows 3 + `imalatci_firma_adi` + `Malzeme Kabul` `group_key=transfer_kabul` `transfer_kabul_file` single `hideAdd:true` + `Malzeme Cins-Miktar` `group_key=transfer_cins` `transfer_cins_file` single `hideAdd:true`), **`op-doc-order-item-form`** (`prod_code/title/quantity/unit` readOnly, NO deal/received price, + `item_test_docs` + `item_images`), **`op-doc-order-serial-form`** (`serial_no/production_date/quantity/unit`), `op-doc-client-form` **+ `lifnr` (Cari Kodu)** field. |
| **Status** `op-trans-op-doc-order` | — | **`doc_trans_order_created → transfer_sent → ready_for_shipment → approved/rejected`** + **`doc_trans_order_files_rejected`**. `transfer_sent` = "Dosyalar Kontrol Ediliyor". |
| **File types** `op-file-types` | 5 | **+ `op-transfer_kabul_file`, `op-transfer_cins_file`, `op-item_test_file`, `op-item_images_file`** |
| **Permissions** | 19 `per-00..08` | **NOT split — reuse `per-05-01/02` for orders, `per-07-01/02` Dökümanlar, `per-06-01/02` Cari**. |
| **Frontend** `router/index.js:24` | 17 routes | **`/coalpanel/orders` OrderList, `/orders/form/:id` OrderForm** + **`/tedarikpanel`** (`TedarikPanel.vue` header) `/tedarik` login orange 560×840 140px Gdz, unified `loginSms` 560×720 orange |
| **Sidebar** `Sidebar.vue:200` | Talep/Teklifler | **Siparişler only** (Sipariş Listesi — Oluştur removed, SAP-only) + `Dökümanlar` + `Firma` + `Yönetim`. `TedarikPanel` minimal (no sidebar). |
| **Tenants** `public/index.php:11` | `yatagantermik ? YATAGAN : CATES` | **`adm ? ADM : GDZ`** → `GDZ.svg/ADM.svg`, `gdz.jpg/adm.jpg`, bulk `CATES/YATAGAN → GDZ/ADM` 2026-09-02 |

## 2. Data Model — CRITICAL (Master's rules)

1. **Order ↔ Client link = `LIFNR` string** (no FK): `order.spec_code = client.lifnr`. `Cari Kodu` = `LIFNR` from SAP, **keep leading zeros**.
2. **Order Items = own `op-doc-order-item` docs** linked `parent_id = order.id`.
3. **SAP sends ONLY order items** (flat rows). Our cron **groups by `EBELN`** → creates main `op-doc-order` + `op-doc-order-item` rows.
4. **Partial split → clone** as new `op-doc-order` `transfer_no = EBELN-X`. Clone only exists if partially split.
5. **Quantity types:** `ST` = Adet (integer), `KG` = float, `M` = Meter (float). Stored as EAV entities on items.
6. **Serial Numbers:** `op-doc-order-serial` docs parented to items. Entities: `serial_no`, `production_date` (YYYY-MM-01), `quantity`, `unit`.

## 3. Quantity Types & Serial Number System (NEW — 2026-08-28)

### Quantity Types
| Unit | Type | Validation |
|------|------|-----------|
| ST | Integer (Adet) | Must be whole number |
| KG | Float | Up to 2 decimals |
| M | Float (Meter) | Up to 2 decimals |

### Partial Split Flow
1. User selects items + enters split amounts in inline inputs
2. `OrderItemTable.vue` shows split bar with input (step=1 for ST, step=0.01 for KG/M)
3. Split amount is subtracted from original item quantity (`decrementItemQuantity`)
4. Clone item gets split amount as its quantity (`duplicateOrderItem`)
5. Original shows `~~2400~~ → 1900 ST` (strikethrough before, new after)
6. New EAV entities: `original_quantity`, `split_from_qnid`, `split_amount`

### Serial Number Entry Rules
| Unit | Rule |
|------|------|
| KG / M | **Required.** At least one serial row. Default serial_no = `-`. **Production date auto-filled from order `created_at` (not required — user can clear).** User adds rows via "+" button. Quantities must sum to split amount (partial) or full item qty (at_once). |
| ST < 300 | **Optional.** Checkbox "Seri Numarası Girilecek?". When ON, auto-generates N rows (1 per unit, max 300). Each: Seri No + Malzeme Üretim Tarihi + 1 ST. |
| ST >= 300 | **No serial entry.** Checkbox hidden, serial section hidden. |

### Serial Storage
- Serial entries are **separate documents** (`op-doc-order-serial`) parented to the order item
- EAV entities: `serial_no`, `production_date` (YYYY-MM-01 format), `quantity`, `unit`
- Parent item gets `has_serials = 1` flag when serials are created
- Date picker uses flatpickr with monthSelect plugin, stores as `YYYY-MM-01`, displays as `MM.YYYY`

### At-Once vs Partial Serial Handling
| Mode | Serial Data Location | Backend Processing |
|------|---------------------|-------------------|
| **Partial** | `selected_items[].serials` | `processOrderTransfer` creates serials after `duplicateOrderItem` |
| **At-once** | `item_serials[]` (separate payload) | `processOrderTransfer` creates serials for each item's child docs |

### Serial UI (`OrderItemTable.vue`)
- **ST checkbox:** Toggle "Seri Numarası Girilecek?" — auto-generates/clears rows
- **KG/M table:** Parti No (default `-`), Malzeme Üretim Tarihi (flatpickr month picker, **auto-filled from order date**), Miktar, "+" to add rows, "-" to remove (min 1 row kept). **Date not required** — auto-filled but user can clear/change.
- **`orderDate` prop:** OForm passes `orderEntities.created_at` → OrderItemTable parses `d/m/Y` or `Y-m-d` → `YYYY-MM-01` for serial storage
- **`parsedOrderDate` computed:** Converts SAP date format to `YYYY-MM-01` for auto-fill
- **Collapse button:** Header clickable "Genişlet/Daralt" — `v-show` toggles content
- **Scrollable:** `max-height:320px` per serial section — only that item scrolls
- **Summary badge:** Shows on main row when serials filled: `hash icon 2 seri, toplam 500 KG`
- **Per-item scroll:** Serial area has its own scrollbar, doesn't affect main list
- **Existing serial display:** Collapsible read-only section under item row (purple gradient bg, disabled inputs, formatted `MM.YYYY`). **Collapsed by default.** Uses `serialViewCollapsed` state (separate from `serialCollapsed` used for entry UI). `fetchSerialsForItems()` loads serials for items with `has_serials=1` on page load. `formatSerialDate()` converts `YYYY-MM-01` → `MM.YYYY`.
- **Excel upload:** "Excel'den Yükle" button + "Şablon" download button in all 4 serial sections (at-once ST, at-once KG/M, partial ST, partial KG/M). Reads `.xls/.xlsx` client-side via SheetJS (`xlsx` package). Excel format: `SRLCODE` (serial_no), `SRLDATE` (production_date), `QUANTITY` (quantity). ST rows force quantity=1. **Replaces** existing serials for that item. ST mismatch warning: Swal confirm dialog shows row count vs split amount, on confirm syncs `splitAmounts[item.id] = parsed.length`. `_excelUploadSplitAmt` captures split amount at click time to avoid async timing issues. `_excelUploadTime` flag (1000ms) prevents `rebuildSerials` from overwriting Excel data. `serialCollapsed[item.id] = false` ensures serial area stays expanded after upload.

### Clone Order Link (NEW)
- `OList.vue`: Clone orders (`3510001793-1`) show clickable "3510001793'den ayrıldı" link below order number
- `OForm.vue`: Indigo banner "Kaynak Sipariş" with "Orijinal Siparişe Git" button
- Detects clones by `-X` suffix pattern in `order_no` entity
- Navigates to original via search (`POST /v1/table/documents` with `all` filter)

## 4. Files Changed (key — 2026-08-28 + 2026-09-02 sessions)

| File | Change |
|------|--------|
| `OrderSystemSeeder.php` | + `op-doc-order-serial`, `op-doc-order-serial-form` |
| `OrderItemTable.vue` | **Complete rewrite:** inline split inputs, quantity type awareness, serial entry UI (ST checkbox, KG/M table), collapse button, scrollable per-item, summary badge, flatpickr month picker, at_once mode serial support, `fetchSerialsForItems()` loads existing serials, collapsible read-only serial view (collapsed by default, `serialViewCollapsed` state), `formatSerialDate()`, `ensureAtOnceSerials()`. **+Excel serial upload (2026-08-31):** `import * as XLSX from 'xlsx'`, `excelFileInputs` ref map, `triggerExcelUpload(item)` captures `_excelUploadSplitAmt`, `downloadExcelTemplate()` generates template xlsx, `parseSerialExcel(item, file)` reads Excel client-side + replaces serials + ST mismatch Swal confirm + syncs split amount. `_excelUploadTime` flag prevents `rebuildSerials` overwrite (1000ms). `serialCollapsed[item.id] = false` after upload. 4 "Excel'den Yükle" + 4 "Şablon" buttons across all serial sections. **+KG/M serial → Böl input sync (2026-08-31):** `syncSplitFromSerials()` auto-updates splitAmounts from serial quantities sum; `_syncingSplit` flag prevents infinite loop between `splitAmounts` ↔ `serials` watchers. Called from `addSerialRow`, `removeSerialRow`, serial quantity change (debounced `serials` watcher), Excel upload. **+At_once collapse buttons (2026-08-31 late):** Both at_once ST and KG/M serial sections now have Daralt/Genişlet collapse buttons (`toggleCollapse`) + `v-show="!isCollapsed(row)"` on content. **+Partial layout matches at_once (2026-08-31 late):** Partial ST header now shows "Toplam: X ST", partial KG/M header now shows "Toplam: X KG" + "Satır Ekle" moved to header (was at bottom). **+Visual color split:** at_once = light gray `#f8fafc`, partial = warm yellow/amber gradient `#fffbeb → #fef3c7` via `.oic-serial-partial` class. **+Daralt far right:** `margin-left:auto` on Daralt span in all 4 serial sections (at_once + partial × ST + KG/M). **+KG/M auto serial date (2026-08-31 late):** `orderDate` prop + `parsedOrderDate` computed (parses `d/m/Y` or `Y-m-d` → `YYYY-MM-01`). `ensureAtOnceSerials`, `rebuildSerialsForItem`, `addSerialRow` all pre-fill KG/M `production_date` from order date. `production_date` validation removed for KG/M (not required). |
| `package.json` | + `"xlsx": "^0.18.x"` dependency |
| `OForm.vue` | `selectedItems` format `[{qnid, amount, serials}]`, `allItemSerials` for at_once, serial validation, `atOnceMode` prop + `@serials` listener, clone origin banner, transfer info card. **+Malzeme Kabul Formu print (2026-08-31):** "Malzeme Kabul Formu Yazdır" button (green, icon) in transfer card. `printMalzemeKabul()` method: validates İmalatçı firma required, Swal error modals with "Tamam" close, collects items (all/selected based on transferMode), calculates clone order_no suffix via `POST /v1/table/documents` query, submits `FormData` to `POST /v1/export/malzeme-kabul`. `getFieldValue()` helper for dynamicF, `orderFormEntities` computed, `getCurrentFormData()` ref method. **FIX 2026-08-31 late:** `imalatci_firma_adi` now rendered **inside** signature box (`exports/malzeme-kabul.blade.php` second row), not floating. `getFieldValue()` hardened to handle compound names + prefix match. **NEW RULE 2026-08-31 late:** `hasPartitions` check — `checkHasPartitions()` queries `POST /v1/table/documents all=baseNo+'-'`, disables `Tek Seferde` radio (grey, pointer-events none) + banner, forces `transferMode='partial'` if partitioned. `buildTransferPayload()` & `printMalzemeKabul()` block `at_once` when `hasPartitions`. `isAtOnceDisabled` computed. **Parçayı Sil label:** `isCloneOrder` → button shows `Parçayı Sil` vs `İptal Et`, Swal title changes. **+Reprint from files_rejected (2026-08-31 late):** `canPrintKabul` computed = `canSend \|\| files_rejected`. Print button appears in locked card when `files_rejected`. `printMalzemeKabul` uses `effectiveTransferMode` (form state if canSend, else `storedTransferMode` from DB). `getFieldValue()` uses `orderEntities` directly when form is locked (DB-only mode). Items from `itemTable.items` (all items used when selectedItems empty in locked state). **+Reprint order_no fix (2026-08-31 late):** If current order already has suffix (`/\-\d+$/`), uses it as-is for reprint. Only increments suffix for NEW partial transfers from base order. **+storedTransferMode fallback (2026-08-31 late):** Falls back to `'partial'` for clone orders if entity is missing (covers old clones created before backend fix). **+KG/M auto-date prop (2026-08-31 late):** Passes `orderEntities.created_at` as `:orderDate` to `OrderItemTable`. Removed `production_date` required check for KG/M in `validatePartial` and `validateAtOnceSerials`. **+Item file saves (2026-09-01):** `@item-files="onItemFiles"` listener, `itemFiles` data, `saveItemFiles()` — after main order save, iterates items with new test/image files, PUTs each via `/api/v1/document/{itemQnid}` with `dynamicF` + file references. Uses `connIds` from OrderItemTable's `_fileConnId` (existing sys_con_ops.id from SAP sync) to avoid duplicate sys_con_ops rows. **+Cins-Miktar serial fix (2026-09-01):** `printMalzemeCinsMiktar` now reads `itemTable.serials?.[item.id]` (frontend state with newly entered serials) instead of `item.serials` (DB-only, empty for new partitions). Falls back to `item.serials` for saved orders. |
| `ExportController.php` | **+`malzemeKabul()` (2026-08-31):** `POST /v1/export/malzeme-kabul` — accepts `qnid`, `items` JSON, `order_no`, `buying_no`, `created_at`, `order_desc`, `imalatci_firma_adi`. Reads `getFormData()` for DB entities. Uses DB entities as PRIMARY source (not request) — `imalatci_firma_adi` always from saved data, only overrides if frontend sends non-empty. **Fix 2026-08-31 late:** `trim((string)(...??''))` cast for `ConvertEmptyStringsToNull` (trim(null) TypeError). Blade `malzeme-kabul.blade.php` now shows `imalatci_firma_adi` **inside** `signature-grid` second row (`height:80px`), dedicated floating block removed. |
| `DocumentServiceProvider.php` | `processOrderTransfer($qnid, $mode, $selectedItems, $itemSerials)` — handles partial clone + at_once serials. `duplicateOrderItem` accepts `$splitAmount`. New: `decrementItemQuantity`, `storeOriginalQuantity`, `createSerialEntries`, `setHasSerialsFlag`. **NEW 2026-08-31 late:** `hasActivePartitions($baseNo,$excludeId)` counts active `EBELN-X` clones (`d.status=1`); guard blocks `at_once` if partitioned (`Bu sipariş daha önce parçalı gönderildi...`). `incrementItemQuantity()`, `restoreQuantitiesForClone()`, `restoreQuantityForSingleCloneItem()` — on `removeContent()`/`cancelOrder()` for clones (`parent_id!=0` + `EBELN-X`), restores `split_amount` to original `quantity` (ST int / KG/M float), deactivates clone serials/items. `removeContent` UserLog `user_id` fallback `?? 0` fixed. **+saveTransferModeEntity on clone (2026-08-31 late):** Partial mode now calls `saveTransferModeEntity($clone, $mode)` in addition to `$order`, so clones store `transfer_mode` in EAV for frontend display. **+File replacement entity bug FIX (2026-08-31):** `$check` for existing file entity now queries `table_tag='document_files'` (was `'sys_con_ops'` → never matched → dup entity rows → always deactivated first file); `$oldFileEntity` uses `orderByDesc('id')`. |
| `DocumentController.php` | Passes `$itemSerials` to `processOrderTransfer` |
| `OList.vue` | Clone order "den ayrıldı" link with `findAndNavigateToOrder` method. **NEW 2026-08-31 late:** `Parçayı Sil` label for clones (`isCloneRow` check), `cancelOrder(qnid,isPartial)` conditional Swal, `table.deleteRow(qnid)` instead of `window.location.reload()` (was `removeRow` bug → now `deleteRow` per `pickletable/assets/script.js:680`). |
| `Sidebar.vue` | **REMOVED "Sipariş Oluştur" menu item** — orders come from SAP, only "Sipariş Listesi" shown |
| `OForm.vue` | **No-id guard** — `/coalpanel/orders/form` (no id) redirects to `OrderList`. Form requires a SAP-created order id. |
| `DList.vue` | **+Fix grouping (2026-08-31 late):** `groupFormatter` handles null/invalid JSON in `relation_detail`, falls back to `entity_tag` or "Belge" title. `rowFormatter` wrapped in try/catch, handles null `relation_detail`. Date column "Invalid Date" fixed — checks `dayjs.isValid()` before formatting. **+Fix grouping v2 (2026-08-31 late):** `groupFormatter` extracts `order_no` from `relation_detail` entities (not just `title`). `rowFormatter` now also sets raw field name from `entity_tag` (e.g. `order_no`) as top-level key. **+Fix created_at display (2026-08-31 late v2):** `rowFormatter` pre-formats `created_at` → `_created_at_fmt` (DD/MM/YYYY HH:mm). Column uses `key: '_created_at_fmt'` with `type: 'string'` (no custom formatter — PickleTable renders pre-formatted value directly). **FIX DList file grouping (2026-09-01):** `groupBy:'relation_detail'` couldn't reach `parent_order_no` (computed after data load in rowFormatter) → item files grouped by item name. Added `group_key` SQL column in `Document_files::tableList()` — COALESCE: same-conn `order_no` first (order-level files → clone's own number), parent `order_no` fallback (item files → clone's number via `d.parent_id`). `DList.vue` uses `groupBy:'group_key'` — all files (order-level + item) now group under the correct clone order number. Removed dead `parent_order_no` injection from rowFormatter. **+Product name for test docs (2026-09-01):** İlişki column shows `product_name` (from SQL `product_name` column) with `bg-info` badge for test documents, grey `bg-secondary` for others. |
| `CspMiddleware.php` | + `'unsafe-eval'` to `script-src` (sweetalert2 uses `new Function()` to parse form input values in Swal HTML). Local `IS_TEST=true` disables CSP header entirely; change matters for production. |
| `exports/malzeme-kabul.blade.php` | **Fix:** `imalatci_firma_adi` moved from floating `İmalatçı Firma :` block **into** `signature-grid` second row (`<td height:80px>{{ $imalatci_firma_adi }}`), single occurrence. |
| `DocumentHelpers.php` | **+File replacement `replaced_id` direction FIX (2026-08-31):** `finalizeTempFile()` + `addFileToDb()` — changed from `old.replaced_id = new` (forward) to `new.replaced_id = old` (backward = "one version before"). New file's `replaced_id` points to its predecessor. Old file only gets `status=0` (no `replaced_id`). |
| `exports/malzeme-cins-miktar-kabul.blade.php` | **NEW (2026-09-01):** Clone of `malzeme-kabul.blade.php`, title `MALZEME CINS-MIKTAR KABUL FORMU`. Same layout (header, items table, signature grid). Content to be customized later. |
| `ExportController.php` | **+`malzemeCinsMiktarKabul()` (2026-09-01):** `POST /v1/export/malzeme-cins-miktar-kabul` — same logic as `malzemeKabul()`, renders `exports.malzeme-cins-miktar-kabul`, filename `malzeme-cins-miktar-{order_no}.pdf`. |
| `OForm.vue` | **+`printMalzemeCinsMiktar()` (2026-09-01):** Clone of `printMalzemeKabul()`, hits new endpoint, downloads `malzeme-cins-miktar-{order_no}.pdf`. **+2 purple buttons** (`.print-cins-btn`) in transfer card + locked card (next to green Kabul buttons). CSS: purple gradient `#7c3aed → #6d28d9`. |
| `routes/api.php` | **+`POST /v1/export/malzeme-cins-miktar-kabul` (2026-09-01):** Before `{model}` wildcard. |
| `OrderItemTable.vue` | **FIX item file display (2026-09-01):** `fetchItemFiles()` read `conn.files` which backend NEVER populates (`getFormData` puts file JSON strings in `conn.entities`). Now parses BOTH `conn.files` (legacy) and `conn.entities` (current: JSON-parse string values whose tag includes `item_test_file`/`item_images_file`), dedupes by file id → test doc + product images show again on order detail. **FIX test doc single slot (2026-09-01):** `existingTestFiles` was an array accumulating multiple files — upload button always showed → multiple test docs per item. Now: shows only ONE existing file (first), upload button hidden when file exists, remove emits `removedFiles` so backend deactivates via `removedData`. New upload to replace existing: file key includes existing file ID → `registerContent` detects replacement. |
| `OForm.vue` | **FIX product images overwrite each other (2026-09-01):** `saveItemFiles()` gave EVERY image the SAME entity_tag `item_images_file**item_images**{rowId}` → backend `registerContent` treated image 2 as a REPLACEMENT of image 1 (deactivated old `document_files.status=0`) → only last image active → detail showed 1 image. Now each image gets a UNIQUE tag `item_images_file**item_images**{rowId}**img-{uploadId}` → all append, none replaced. Test doc tag unchanged (single slot, replacement desired). Legacy broken rows (2 files, same tag, 1 active) stay in DB; re-add images to fix. |
| `OForm.vue` | **FIX item files lost on partial transfer (2026-09-01):** `saveItemFiles()` ran AFTER the order PUT. On a partial transfer the order PUT → `processOrderTransfer` created the clone and ran `moveOrderFilesToDocument(item→cloneItem)` while the item files were still `relation='temp'` (no entities yet) → nothing moved → item files finalized onto the ORIGINAL items, clone shipped without test docs/images (order-level kabul/cins files DID move — they finalize inside the order's own `registerContent`). FIX: `saveItemFiles()` now runs BEFORE the order PUT — files are finalized+linked to original items first, so `moveOrderFilesToDocument` finds them and relinks to the clone. Returns bool; aborts the order save on item-file failure. Clears `itemFiles` after success so a retry never re-sends used temp references. **+Test doc single-slot (2026-09-01):** `onItemFiles` tracks `itemRemovedFiles`; `saveItemFiles` processes removals (PUT with `removedData`) before uploads; test file key includes existing file ID for replacement detection. |
| `DocumentServiceProvider.php` | **FIX clone junk entities (2026-09-01):** `duplicateOrderItem()` copies ALL `getFormData` entities incl. file slots (item_test_file / item_images_file JSON payloads) as scalar `sys_con_ops` rows on the clone — junk duplicates of the real files (which `moveOrderFilesToDocument` moves separately). Now skips tags containing `item_test_file` / `item_images_file`. **FIX entity_tag on move (2026-09-01):** `moveOrderFilesToDocument` relinked `conn_id` but left `entity_tag` with old connId → replacement detection in `registerContent` failed (lookup uses new connId, tag has old → no match → duplicate instead of replace). Now also updates `entity_tag` via `str_replace(oldConnId, newConnId, tag)`. |
| **DATA REPAIR (2026-09-01)** | Order 552 (clone) item 553: moved files 150/151/152 + entities from conn 564 (original item 548) → conn 569 (clone item 553) via SQL (mirrors `moveOrderFilesToDocument`). Clone detail now shows test doc + active image; file 151 stays `status=0` (lost to the earlier shared-tag bug). |
| `Document_files.php` (Model) | **+`group_key` column (2026-09-01):** SQL in `tableList()` adds `group_key` — COALESCE of two subqueries: first tries `order_no` from same conn (order-level files → clone's own number), fallback `order_no` on `d.parent_id` (item files → parent clone's number). Used by DList's `groupBy:'group_key'` so all files (order-level + item) group under the clone's order number. **+`product_name` column (2026-09-01):** Subquery fetches `title` entity from the file's parent document (the order item) — shown in DList İlişki column for test documents as product name. **+Exclude product images (2026-09-01):** WHERE filter `se.entity_tag not like '%item_images_file%'` removes per-item product images from files listing (not actionable for admin review). |
| `OrderItemTable.vue` | **FIX product image removal (2026-09-01 late):** `fetchItemFiles()` now stores `entity_tag`+`connId` per file (was dropping tag → removal couldn't find entity); `removeExistingImageFile()` pushes `{id:connId,key:entity_tag,fileId}` to `_removedExistingFiles` (was just splice local array → backend never deactivated); `removeExistingTestFile()` same generic push. **+Preview for files (2026-09-01 late):** `previewExistingFile(file)` iframe modal for both test docs (pdf/xls) and product images (encrypted name `salt:iv:ct` never ends with `.pdf` → old `\.pdf$` check always false → pdf showed as broken `<img>`), uses `entity_tag` to label `Test Dökümanı` vs `Ürün Görseli`; `previewLocalImage(img)` pdf→iframe, xls→window.open, image→Swal `imageUrl` with `URL.createObjectURL`; chips now `oic-previewable` clickable + eye button `oic-item-file-preview` (test doc chips + image chips). CSS `oic-item-file-preview` (purple) + `oic-previewable:hover`. **+Thumbnail grid (2026-09-01 late):** Ürün Görselleri now `oic-image-grid` 84×84 `12px` `object-fit:cover` `src="/order-file/{qnid}"` + `URL.createObjectURL` for uploaded, `onThumbError` PDF fallback, `previewUrl` lifecycle (`beforeUnmount` revoke, `removeImageFile` revoke). **+Gallery (2026-09-01 late):** `gallery:{visible,items,index}` + `currentGalleryItem`, `getGalleryItems(itemId)` merges existing+uploaded, `openGallery(rowId,globalIdx)` `globalIdx=existing.length+imgIdx`, `teleport to body` `oic-gallery-overlay` `rgba(15,23,42,0.88) blur(6px)` with `prev/next 48px` `counter` `stage 70vh` `<img>`/`iframe` + thumb strip `64px` `active #818cf8`, keyboard `Esc/←/→`. **+Split overlay icons (2026-09-01 late):** Was pill `Önizle/Sil` bottom bar → now `oic-split` **50/50** `flex row` full thumb, `left: .eye 22px rgba(15,23,42,0.48)→hover #4f46e5` + `right: .cross-circle 22px →hover #e11d48`, `border-right rgba(255,255,255,0.14)` split line, `22px` big. **+Lock (2026-09-01 late):** `props readonly:Boolean` (`OForm :readonly="isLocked"`), guards `trigger* / on*FileSelected / remove*` early return if `readonly`, template `v-if="!readonly"` for `Görsel Ekle` + `oic-split-half right` + test doc `Kaldır` + upload buttons, `oic-split-half left.full` when readonly (preview full-width), lock hint `ki-lock-2 Sipariş kilitli — görsel/dosya eklenemez`. **+Test doc non-removable (2026-09-01 late):** Existing `accepted/pending` chip **no X** (only `eye` preview) — `removeExistingTestFile` button removed; `Yeni Test Dökümanı Yükle` in `rejected` branch **always visible** (removed `v-if="!readonly"`) so replace works even when `isLocked` (`files_rejected` keeps files editable). Insert only on `saveItemFiles` PUT, replace via `existingId` → `finalizeTempFile(existingFileId)` → `doc_file_refreshed`. |
| `OForm.vue` | **FIX product image removal (2026-09-01 late):** `saveItemFiles()` generic `connId=rf.connId||rf.id` + `key=rf.key` for `removedData` (was hardcoded `item_test_file**item_test_docs**` → images never deleted); clears `itemTable._removedExistingFiles=[]` after success so retry doesn't re-send. **+Lock (2026-09-01 late):** `:readonly="isLocked"` passed to `OrderItemTable` (`isLocked` from `lockedStatuses`, includes `files_rejected` → strict; for test doc replace in `files_rejected` the child overrides and always shows replace button). **+Test doc non-removable (2026-09-01 late):** Existing `accepted/pending` chip no X (only `eye` preview) — `removeExistingTestFile` button removed; rejected `Yeni Test Dökümanı Yükle` always visible even when locked. **+Loader for Kabul/Cins (2026-09-01 late):** `data printingKabul/printingCins`, `Swal.fire` `PDF oluşturuluyor...` `ki-loading spin #059669/#7c3aed` covering clone suffix query + `fetch /export/malzeme-kabul|malzeme-cins-miktar-kabul` + blob download, `Swal.close()` + flag reset on success/error, buttons `:disabled` + label `Oluşturuluyor...` `opacity 0.72` `@keyframes spin`. **+Still-rejected toast after Kaydet (2026-09-02):** After `PUT /v1/document` (`registerContent` → `syncOrderStatusFromFiles`), if `lastOp==files_rejected` build `still[]` from `detail.formFormat` (transfer_kabul/cins `doc_file_rejected`) + `itemTable.existingTestFiles` where rejected && no `itemTestFiles` replacement → `Swal` info `Kaydedildi — hala reddedilen dosyalar var: …` else normal toast. |
| `OrderItemTable.vue` | **FIX Yeni Test blocked when rejected+locked (2026-09-02):** `isTestRejected(id)` helper, `triggerTestUpload`/`onTestFileSelected`/`removeTestFile` now `if(readonly && !isTestRejected) return` → rejected replacement works even when `isLocked` (files_rejected). Uploaded chip remove `v-if="!readonly || isTestRejected"`. |
| `DList.vue` | **FIX grouping header visual + full-length (2026-09-02):** Duplicate `›` (`td.textContent` included icon → `›351000...`) + shrink-wrapped flex pill left. Fixed to inner `div.dlist-group__inner` `display:flex; width:100%; gradient #eef2ff→#f8fafc; border-left:3px #6366f1; radius12` + folder `ki-folder` icon + `count` pill `margin-left:auto` dot. Head search/order broke due to `border-collapse:separate` + `tbody background` — reverted to `collapse` and inner card handles spacing. |
| `pickletable/assets/script.js` | **FIX group header missing after filter/order (2026-09-02):** `getData` cleared `body.innerHTML` on `isFiltering`/`isOrdering` but kept `groupHeaderElements` cache → `renderGroups` reused detached header, `indexOf -1` → no header inserted. Now clears `this.groupHeaderElements={}` there. |
| `Document_files.php` (Model) | **FIX file list relation & old_versions (2026-09-02):** `tableList()` join `se.entity_value=i.id::text` had no `table_tag='document_files'` filter → garbage `sys_con_ops/quantity=180` row linked to `document_files id 180` → duplicate rows + `old_versions` `df2.id=se2.entity_value::int` crashed on `340.60` (`invalid input syntax for integer`). Fixed: join `+ and se.table_tag='document_files'`, `old_versions` → `df2.id::text=se2.entity_value and se2.table_tag='document_files' and se2.entity_value ~ '^[0-9]+$'`. Now only real file links, no cast crash. |
| `DList.vue` | **FIX detail popup İlişki & Eklenme Tarihi for Malzeme Kabul/Cins (2026-09-02):** `rowFormatter` overwrote `data.created_at` with EAV `created_at="20/08/2026"` → `Invalid Date`. Fix: preserve `_file_created_at`, compute `_created_at_fmt`, skip overwriting `created_at/id/file/qnid`, store as `eav_*`. `createDetailModalContent` uses `product_name||order_no`. `columnFormatter` fallback `product_name → order_no → '—'`. |
| `public/index.php` | **Tenants GDZ/ADM (2026-09-02):** `CATES/YATAGAN → GDZ/ADM`, `yatagantermik → adm`, `GDZ.svg/ADM.svg`, `gdz.jpg/adm.jpg` |
| `SysSeeder.php` / `Documents` migration | `CATES → GDZ`, `GDZ Sistem`, bulk `CATES/YATAGAN→GDZ/ADM` 16 files (`Header.vue`, `RSummary.vue`, `emails/layout`, Models, Providers) |
| `resources/views/auth/tedariklogin.blade.php` | **NEW Tedarik login `/tedarik` → `/tedarikpanel` orange Gdz card `560×840 140px` logo `Sipariş Platformu`, `E-Posta/Şifreniz` 56px inputs, `Giriş Yap` 58px #FF4713, `Beni Hatırla/Şifremi unuttum`, video icon** — `AuthController tedariklogin()` + origin-aware `loginUser` (`auth_panel=tedarik`) + `checkCode` `tedarik-login` redirect + unified orange `loginSms` (single file for both panels) |
| `resources/views/auth/loginSms.blade.php` | **Unified 2FA** orange Gdz `560×720 110px` logo `Doğrulama Kodu` 6×52px inputs, `44px` countdown #FF4713, `Tekrar Gönder` |
| `resources/views/tedarikapp.blade.php` + `layouts/TedarikPanel.vue` + `router/index.js:110` | **NEW `/tedarikpanel`** (`TedarikPanel` orange header) + `pages/tedarik/Dashboard.vue` placeholder, reuses `OrderList/OrderForm` for tedarik view |
| `public/front/pages/tedariklogin/page.js` | Tedarik login JS `→ /tedarikpanel`, remember-me cookies |
| `layouts/TedarikPanel.vue` | **POLISH 2026-09-02 late:** Card `f2f2f3` bg `22px 18px 18px 48px` root, frame `1360px` `12px` `overflow:visible` (protruding tabs), sidebar `210px` `overflow:visible z10`, logo `82px` + `Malzeme Tedarik İş Süreci` 11.5px label, menu `flex1 center` 6 tabs `64px 12px -52` (38px outside, 10px gap) `17.5/16.5px` `10→12px` → `16px` arrows / `20px` badge/logout icons unified, `OList` card-rows, `pickletable` overrides unscoped `.tedarik-main` + **Modüller btn** `tedarik-modules-btn` `eef2ff` above logout + modal |
| `pages/coalsystem/Order/OList.vue` | **Tedarik mode `isTedarik` (`/tedarikpanel`):** `tedarik-card` `transparent` + header `Sipariş Listesi` + filters + card-rows `border-collapse:separate 0 7px` `auto` `13/13.5px` + solid pills + bottom note + hidden `mainSearch`. `pickletable` `height:'auto'` + scoped+unscoped overrides. **+Clone link** `Parçayı Sil` `deleteRow` + **Detailed search** `tedarik-detailed-panel` 3×3 grid hover `absolute top:52 z40` (Stok/Sipariş/Alım/Seri/Üretim/Tedarikçi=users lifnr/Şirket=client lifnr/Onay/Tarih) `Filtrele/Sıfırla/Excel` + `Documents::tableList` 9 keys |
| `pickletable/assets/script.js` + `style.css` | **Guard `height:'auto'`:** Skip `setAttribute(style height)` + `table.style.width` when `auto`; add `pt-auto-height` class + card-row CSS `.tedarik-panel .pickletable table` `separate 0 7px` `auto`; also clone fix `Parçayı Sil` `deleteRow` |
| `components/coalparts/Sidebar.vue` | **+Module Switcher (2026-09-02 late):** `Modüller` btn `eef2ff/c7d2fe/#3730a3` above Çıkış (`aside-footer` `gap-2`), `showModuleModal` + `modules[]` (`Yönetim→/coalpanel` `#154b91`/`Tedarik→/tedarikpanel` `#FF5A1F`) → `goModule()` `$router.push` fallback `href`, `isModuleActive` badge, modal `module-modal-overlay` `rgba(15,23,42,0.48) blur6` + `module-modal-card` 560px 18px |
| `Documents.php` | **+Detailed search backend (2026-09-02 late):** `tableList()` 9 keys `stok_kodu` (EXISTS item `prod_code`), `siparis_kodu` (`order_no`), `alim_kodu` (`buying_no`), `seri_no`/`uretim_tarihi` (EXISTS serial via item), `sirket/tedarikci` (`spec_code`/`ctitle`), `onay_durumu`→`transactions`, `tarih_araligi` (`i.created_at` or `created_at` entity) |
| `OList.vue` | **+Filtreler dropdown `teleport to body` `fixed` (2026-09-02 late):** `Filtreler` `280px` `9` radios (`seri_no/tarihe_gore/tarih_araligi/alim/siparis/beklemede/dosya/tamamlanan/hepsi`) + `Şirkete Göre Arama` `FF4713` → `client-modal` `720px` `PickleTable` `localData` `200` lazy (was `persons` `POST` on mount **killed**), `flatpickr range` `Y-m-d` → `tarih_araligi` `|`, `Detaylı` twin `Şirket/Tedarikçi` `readonly` → same modal `single` `detay.sirketLabel/tedarikciLabel`, `dropdownPos` `fixed` `z9999` escape `tedarik-main overflow:hidden` |
| `Documents.php` | **+Filter ordering + multi (2026-09-02 late):** `order` `alim_kodu→buying_no` `siparis_kodu→order_no` `stok_kodu→prod_code` via lateral, `transactions` `IN` for `beklemede/tamamlanan` `doc_trans_order_*` comma, `sirket` multi `OR` `lifnr/title`, `tarih_araligi` `to_date(entity_value,'DD/MM/YYYY')` for `BEDAT` `DD/MM/YYYY` vs `i.created_at` `Y-m-d` |
| `OList.vue` | **+Client modal never-empty hardFallback (2026-09-02 late):** `hardFallback` `8` `AKSA…PANORAMA` seeded `tinker`, `modalClients` `modalFilteredClients` `computed`, `onClientModalSearch` local `ilike` |
| `OList.vue` | **FIX Şirkete Göre Ara empty + Detaylı auto-close (2026-09-02 late):** `OList.vue:376 sirkete_gore` was `showClientModal=true` without `buildClientTable` → empty `Yükleniyor`; fix `openClientModal('multi')` + instant `hf` fallback in `openClientModal:111` + `PickleTable data: localData` → `this.modalClients.slice()` scope fix `OList.vue:214`. `applyDetailedFilter:519`/`resetDetailedFilter:533` now `showDetailed=false` auto-close after `Filtrele/Sıfırla`. |

## 5. How Data Flows Now

```
SAP sends flat items → cron groups by EBELN → order + items
→ /coalpanel/orders/form/:id → OrderItemTable (items row list)
  → fetchItems: load items with parent_id filter
  → fetchSerialsForItems: for items with has_serials=1, fetch op-doc-order-serial children
  → Existing serials shown as collapsible read-only section (collapsed by default)
→ Transfer card: Tek Seferde (at_once) | Parçalı (partial)
  → NEW RULE 2026-08-31 late: if `hasActivePartitions(baseNo)` (active EBELN-X clones exist, d.status=1) → Tek Seferde disabled (grey, banner), forced Parçalı. Backend `hasActivePartitions()` guard blocks at_once with error. All clones removed (status=0) → Tek Seferde unlocks again.

AT-ONCE:
→ All items show serial entry (KG/M: auto first row via ensureAtOnceSerials, ST<300: checkbox)
→ SAVE → PUT /v1/document/{id} + transfer_mode='at_once' + item_serials
→ processOrderTransfer: guard hasActivePartitions → if partitioned → error; else setStatus transfer_sent + createSerialEntries per item

PARTIAL:
→ Select items → split amount inputs → serial entry per selected item
→ SAVE → PUT /v1/document/{id} + transfer_mode='partial' + selected_items
→ processOrderTransfer: clone EBELN-X + duplicateOrderItem (with splitAmount)
  + decrementItemQuantity + createSerialEntries + moveOrderFilesToDocument

SERIAL ENTRIES:
→ Each serial = Documents(type=op-doc-order-serial, parent_id=item.id)
→ EAV: serial_no, production_date (YYYY-MM-01), quantity, unit
→ Parent item gets has_serials=1 flag
→ Viewing: fetchSerialsForItems loads serials → shown in collapsible disabled fields
→ **KG/M auto-date:** `orderDate` prop from OForm (`orderEntities.created_at`) → `parsedOrderDate` computed (parses `d/m/Y` → `YYYY-MM-01`) → auto-fills `production_date` in `ensureAtOnceSerials`, `rebuildSerialsForItem`, `addSerialRow`. Date not required for KG/M.

EXCEL SERIAL UPLOAD:
→ User clicks "Excel'den Yükle" → triggerExcelUpload captures splitAmounts[item.id]
→ File picker (.xls/.xlsx) → SheetJS reads client-side (no backend)
→ Maps: SRLCODE→serial_no, SRLDATE→production_date (YYYY-MM-01), QUANTITY→quantity
→ ST: quantity forced to1, serial checkbox auto-enabled
→ KG/M: quantity from Excel
→ REPLACES existing serials for that item
→ ST mismatch (e.g. 8 Excel rows vs 2 split): Swal confirm dialog → on confirm syncs splitAmounts = parsed.length
→ _excelUploadTime flag (1000ms) prevents rebuildSerials from overwriting
→ serialCollapsed[item.id] = false ensures area stays expanded

KG/M SERIAL → BÖL INPUT SYNC (2026-08-31):
→ syncSplitFromSerials() auto-updates splitAmounts[item.id] from serial quantity sum
→ Called from: addSerialRow, removeSerialRow, serial quantity change (debounced 500ms via serials watcher), Excel upload
→ _syncingSplit flag prevents infinite loop between splitAmounts ↔ serials watchers
→ Only fires when sum > 0 (prevents clearing input when all serials removed)

MALZEME KABUL FORMU PDF (2026-08-31):
→ User clicks "Malzeme Kabul Formu Yazdır" button in transfer card (green, icon)
→ Validates: imalatci_firma_adi required (Swal error if empty) + hasPartitions guard (Tek Seferde blocked if partitioned)
→ Collects items (all for at_once, only selected for partial)
→ Calculates clone order_no suffix by querying existing clones via POST /v1/table/documents
→ buying_no (SUBMI) stays original — NO clone suffix
→ POST /v1/export/malzeme-kabul with FormData (qnid, items JSON, order_no, buying_no, created_at, order_desc, imalatci_firma_adi)
→ ExportController::malzemeKabul() — DB entities as PRIMARY source for imalatci_firma_adi (not request) — trim((string)...) fix for ConvertEmptyStringsToNull
→ Blade template: exports/malzeme-kabul.blade.php — A4 portrait, title underlined, Alım No/Sipariş No stacked left, Tarihi right, 2px table borders, signature grid **with imalatci_firma_adi inside second row (height:80px)** (not floating)
→ dompdf renders PDF → download as malzeme-kabul-{order_no}.pdf
→ Flow: fills form → clicks Print → PDF downloads → prints on paper → signs with pen → scans → uploads signed PDF to transfer_kabul file field → saves order
  → After fix 2026-08-31 late: imalatci_firma_adi appears once inside signature box, not duplicated.

CINS-MIKTAR SERIAL FIX (2026-09-01):
→ `printMalzemeCinsMiktar` was reading `item.serials` (DB-fetched) — empty for NEW partitions (serials not saved yet)
→ Fixed: reads `itemTable.serials?.[item.id]` (frontend state from OrderItemTable's `serials` data property)
→ Falls back to `item.serials` for saved/reprinted orders
→ Affects both partial and at_once branches of item collection

ITEM FILE UPLOADS (2026-09-01):
→ Each order item gets collapsible "Dosyalar" section in OrderItemTable
→ Two sub-sections:
  → **Test Dökümanı** (`item_test_docs` group, `item_test_file`): single file, rejectable/acceptable, shows status badge (Onaylandı/Reddedildi/Beklemede)
  → **Ürün Görselleri** (`item_images` group, `item_images_file`): multiple files, no accept/reject
→ Existing files fetched via GET /v1/document/{itemQnid} → formFormat['op-doc-order-item-form'][connId].files
→ `_fileConnId` stored on item data from existing sys_con_ops.id (SAP sync created these)
→ New uploads via temp-upload → emit `item-files` event to OForm
→ OForm `saveItemFiles()`: for each item with files, PUT /v1/document/{itemQnid} with dynamicF + files
→ Uses connId from SAP sync as rowId (avoids creating duplicate sys_con_ops rows)
→ `registerContent` processes files on item document: creates Document_files + Sys_con_entities + Transactions
→ `syncOrderStatusFromFiles` already checks item files (via parent_id join) — rejected item test file → order `files_rejected`

RESTORE ON REMOVE (2026-08-31 late):
→ User clicks Parçayı Sil on clone (EBELN-X) in OForm locked-card or OList row (was İptal Et, now conditional)
→ POST /v1/orders/cancel or DELETE /v1/document/{cloneQnid} → Documents.status=0 + Transactions rejected
→ DocumentServiceProvider::restoreQuantitiesForClone() — for each clone item (parent_id=clone.id, type op-doc-order-item, split_from_qnid + split_amount) → incrementItemQuantity(originalQnid, split_amount) (ST int / KG/M float), deactivate clone serials (status 0) + clone items (status 0)
→ Single item delete (op-doc-order-item with split_from_qnid) → restoreQuantityForSingleCloneItem()
→ OList: table.deleteRow(qnid) (not reload) — row vanishes via pickletable/assets/script.js:680

MALZEME CINS-MIKTAR KABUL FORMU PDF (2026-09-01):
→ Mechanics identical to Malzeme Kabul — same data flow, same validations, same items/order_no/buying_no/imalatci
→ User clicks purple "Malzeme Cins-Miktar Kabul Formu Yazdır" button (transfer card or locked card)
→ Same validations: imalatci_firma_adi required, hasPartitions guard
→ Items FLATTENED by serial numbers: each serial = one row (serial_no + serial quantity). Items without serials = one row with item qty + serial_no '-'
→ Same clone order_no suffix calculation
→ POST /v1/export/malzeme-cins-miktar-kabul with flattened items (prod_code, title, unit, quantity, serial_no)
→ ExportController::malzemeCinsMiktarKabul() — adds `company` from `ctitle` entity, renders exports/malzeme-cins-miktar-kabul.blade.php
→ Blade: title "SEVK EDİLECEK MALZEMENİN CİNSİ VE MİKTARI", header table 4 rows vertical (Şirket/İmalatçı Firma/İhale NO/Sipariş NO with label | ":" | value), items table (Malzeme Kodu/Cinsi/Birim/Miktar/Seri-Parti No), signature grid
→ dompdf renders PDF → download as malzeme-cins-miktar-{order_no}.pdf
→ Result: main order quantity restores (e.g. 2398 → 2400), hasActivePartitions re-checked → Tek Seferde unlocks if no active clones remain.
```

## 6. Perf / Code Quality Refactors (2026-08-29)

Applied WITHOUT changing mechanics or validations. Build clean.

**OrderItemTable.vue:**
- `initFlatpickr` — only inits new `.oic-fp-month:not(.fp-initialized)` inputs; adds `fp-initialized` class. No more destroy/recreate on every `updated()`.
- `serials` deep watcher — 150ms debounce on `notifySelect` (parent no longer re-renders per keystroke).
- `highlightQnid` watcher — tracks `_lastHighlightEl`, clears only that one (no queryAll every time).
- `itemsMap` computed — O(1) lookup replaces `Array.find` in hot paths (`allValid`, `rebuildSerials`, `getSelected`).
- Serial qty input `min="1"` (matches validation, was `min="0"`).
- `quantityChanged(row)` helper — template uses it instead of inline parseFloat comparison.
- `rebuildSerials` cleanup — 3 loops merged into 1 pass.

**OForm.vue:**
- `parsedStatus` data — `orderStatus` computed reads cached parse (set once in mounted), no more `JSON.parse` on every computed access.
- `orderFormEntities` computed — shared by `storedTransferMode` + `orderEntities` (one iteration, not two).
- Removed dead `wTrans` import.
- `mounted` converted to async/await.
- Files loop uses `Object.entries`.

**OList.vue:**
- `rowFormatter` — `main_attr` parse guarded by `_attrsParsed` flag (no re-parse).
- Removed dead `wTrans`/`PickleTable`/`Plib` from `setup()`.
- Tedarikçi truncation — manual substring → CSS ellipsis.

## 7. What Still PENDING

- **Front panel** design PENDING — backend transfer flow + serial entry already BUILT
- **Dashboard** still coal — needs order metrics
- **Branding** done `Tedarik Yönetim Sistemi`
- **Legacy coal** pages kept hidden, remove later
- **`op-doc-transfer` type** — **PURGED 2026-09-02** (deleted `op-doc-transfer` + `op-doc-transfer-form` + 4× `doc_trans_transfer_*`, `OrderSystemSeeder` + `DocumentServiceProvider` + `Form.vue` + `PermissionHelpers` + `OList/OForm` + DB 6 rows)
- **Malzeme Cins-Miktar Kabul Formu** — DONE (2026-09-01): blade (SEVK EDİLECEK MALZEMENİN CİNSİ VE MİKTARI), controller, route, Vue method + buttons, serials flattened
- **Route ordering** — `POST /v1/export/malzeme-kabul` + `POST /v1/export/malzeme-cins-miktar-kabul` MUST be before `POST /v1/export/{model}/{type?}` wildcard in `api.php` (line 41-42, before line 43)
- **Bug 2026-09-02:** `OList.vue:248` sent `doc_trans_transfer_approved/sent/rejected` for `op-doc-order` docs → `ready_for_shipment → transfer_approved` blocked by `setStatus` guard → fixed to `doc_trans_order_*` + DB purge
- **Shared pages decision 2026-09-02 late:** `OList/OForm` stay **SHARED** via `isTedarik` (keep shared, do not fork) — Tedarik detailed search + hover + module switcher live inside same files, `OrderItemTable` remains shared brain; fork only if supplier flow diverges further (extract `composables/useOrderTable` then)
- **Tedarik detailed search + Module Switcher 2026-09-02 late:** `OList.vue` 3×3 hover `absolute` (hidden by default, `Detaylı Filtre` toggle) + `Documents::tableList` 9 keys, `Sidebar`/`TedarikPanel` Modüller modal `modules[]`

## 8. Credentials & Infra

- **Docker:** `tedarikNewApp` postgres `61d0571c2f7b` on `127.0.0.1:5431`
- **Panel .env:** `DB_DATABASE=tedarikNewApp` `:5431` (GDZ default `grp_code`)
- **Artisan:** `php artisan serve --host=127.0.0.1 --port=8000`
- **Logins:** Admin `http://127.0.0.1:8000/` → `/coalpanel` + Tedarik `http://127.0.0.1:8000/tedarik` → `/tedarikpanel` (same 2FA orange, same creds) — `kadir@kontent.com.tr / Kadir412.` → 2FA `111111`
- **Seeds:** 23 migrations + seeders. `SysSeeder` `GDZ` / `OrderSystemSeeder` 20 rows (+serial) / `UserSeeder` 9 users
- **Fresh data (2026-09-02 08:20, re-seeded GDZ):** `php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh` → 8 orders / 21 items / 8 clients / 0 files / 0 serials / 37 trans. `grp_code=GDZ`×8. Clean slate.
- **Live state (2026-09-02):** 8 orders (all base) / 21 items / 8 clients / 0 files / 0 serials / 37 trans. SAP `/tmp/sap_fresh_payload.json` 21 rows → 8 EBELN, mixed ST/KG/M, ST<300.

## 9. How To Resume

```bash
docker start tedarikNewApp || docker run --name tedarikNewApp -e POSTGRES_USER=tedarikNewApp -e POSTGRES_DB=tedarikNewApp -e POSTGRES_PASSWORD=tedarikNewApp -p 5431:5432 -d postgres:latest
cd panel && php artisan migrate:status # 23 done
cd panel && npm run build # after any Vue/edit
php artisan serve --host=127.0.0.1 --port=8000
# login kadir / Kadir412. / 111111 → /coalpanel/orders
# fresh data: cp /tmp/sap_payload.json /tmp/sap_fresh_payload.json && php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh
# + manual wipe serials/files/storage if needed (see §8 fresh data note)
```

## 10. Known Bugs / Debt

- **File replacement** — FIXED 2026-08-28 + 2026-08-31 (see below). Do NOT change without Master's approval.
- **File replacement v2 (2026-08-31)** — FINAL DESIGN per Master: **every upload creates a NEW `sys_con_entities` row** (duplicate rows per version = version history, like the original design). **Activeness is `document_files.status`** (status=1 = current version) — NOT a column on entities. Lookups: (1) `$oldFileEntity` in `registerContent` finds the ACTIVE entity via `whereIn(entity_value, selectRaw('id::text') from document_files where status=1)` + `orderByDesc('id')` → `$existingFileId` = current file (fixes "always replaces first"); (2) `getFormData` join filters file entities with `EXISTS(document_files where id=entity_value::int and status=1)` → form shows only latest; (3) `removedData` ignores entity rows whose file is inactive; (4) `old_versions` stays the ORIGINAL entity-tag-based query (works again because duplicate rows exist). **+`replaced_id` direction:** `new.replaced_id = old` (one version back), set in `finalizeTempFile`/`addFileToDb`. `finalizeTempFile`/`addFileToDb` "copy entities" blocks (query `conn_id=fileId`) create garbage rows — inert, leave alone. **Porting doc:** `file-versioning-system.md` at repo root (`file-replacement-fix.md` deleted).
- **`syncOrderStatusFromFiles`** — FIXED 2026-08-31: now also fires from `registerContent` after file replacement (was only `documentFileStatus`). Re-uploading a rejected file flips order `files_rejected → transfer_sent` ("Dosyalar Kontrol Ediliyor").
- **Lock** — `ready_for_shipment` FULL lock. `files_rejected` keeps files editable.
- **Status array order (FIXED 2026-08-31)** — `getFormData` document status `json_agg` had NO `ORDER BY` → transaction array order non-deterministic → frontend `parsedStatus[last]` picked WRONG current status (e.g. `transfer_sent` instead of `files_rejected`) → `isFilesLocked` true → rejected file inputs DISABLED. Fixed: `ORDER BY t.id` inside `json_agg` (oldest→newest, last = current). List query (`Documents::tableList:83`) already orders correctly.
- **ST serial max** — capped at 300 rows (matches qty cap). ST >= 300 = no serial entry.
- **PHP `??` vs `?:`** — `??` (null coalescing) doesn't fall through on empty string `''`. Always use `?:` or `!empty()` when checking frontend values that could be `''` (2026-08-31 lesson learned: imalatci_firma_adi bug).
- **Route ordering** — Export routes MUST be before `{model}` wildcard in `api.php` or they'll never match (Laravel matches first route).

## 11. Memory File Map

| File | Contents |
|------|----------|
| `00` | architecture brain-dump: stack, entry points, EAV, auth, permissions, gotchas |
| `01` | Form.vue schema-driven engine deep-dive |
| `02` | EAV tables, dictionary, read/write summary |
| `03` | how to build a new doc-type app (recipe) |
| `04` | backend/frontend patterns, request flow, pitfalls |
| `05` | **LIVE order-system state** — read after `00` |
| `06` | roadmap: next steps + decision tree |
| `07` | SAP sync command reference |
| `idea` | original spec (front panel, partitioning, serials) |
