# Order Management System — Current State (2026-08-31)

> **This is the living snapshot. If you are LLM in a future session, read this first after `00-core-overview.md`.**
> **Control panel conversion is DONE. Front panel PENDING (design awaited). SAP cron PENDING (grouping logic defined, not formalized).**
> **⚠️ KEY CORRECTION (Master): Transfers are NOT a separate doc type — they ARE `op-doc-order` cloned with a new number (`EBELN-X`) only when an order is partially split. `op-doc-transfer` type still exists in dict but is NOT used for the UI anymore.**

## 1. What Changed Since Coal

**Coal (KomurTedarik) → Order Management System** on same EAV skeleton. No migration for business fields — only dictionary + Form.vue + Vue pages.

| Layer | Coal | Order System Now |
|-------|------|------------------|
| **DB** | `b2x` on `5431` | **`tedarikNewApp`** on `5431` same image `61d0571c2f7b` docker `tedarikNewApp` Up, `127.0.0.1:5431 tedarikNewApp/tedarikNewApp` (`panel/.env:28`) — 23 migrations, 77+18+2 sys_options |
| **Doc types** `sys_options.group_key=op-doc` | `op-doc-request\|offer\|client\|flat` | **`op-doc-order` (Sipariş header), `op-doc-order-item` (Kalem, `parent_id=order.id`), `op-doc-order-serial` (Seri Numarası, `parent_id=item.id`), `op-doc-client` (Cari)**. `op-doc-transfer` still seeded but **NOT used** (clones are `op-doc-order` with `transfer_no` entity `EBELN-X`). `OrderSystemSeeder.php` |
| **Forms** `op-doc-forms` | 5: `request/offer/client/user/flat` | **`op-doc-order-form`** (`order_no/buying_no/spec_code/sys_code/ctitle/created_at` readOnly + `order_desc` textarea rows 3 + `imalatci_firma_adi` + `Malzeme Kabul` `group_key=transfer_kabul` `transfer_kabul_file` single `hideAdd:true` + `Malzeme Cins-Miktar` `group_key=transfer_cins` `transfer_cins_file` single `hideAdd:true`), **`op-doc-order-item-form`** (`prod_code/title/quantity/unit` readOnly, NO deal/received price, + `item_test_docs` + `item_images`), **`op-doc-order-serial-form`** (`serial_no/production_date/quantity/unit`), `op-doc-client-form` **+ `lifnr` (Cari Kodu)** field. |
| **Status** `op-trans-op-doc-order` | — | **`doc_trans_order_created → transfer_sent → ready_for_shipment → approved/rejected`** + **`doc_trans_order_files_rejected`**. |
| **File types** `op-file-types` | 5 | **+ `op-transfer_kabul_file`, `op-transfer_cins_file`, `op-item_test_file`, `op-item_images_file`** |
| **Permissions** | 19 `per-00..08` | **NOT split — reuse `per-05-01/02` for orders, `per-07-01/02` Dökümanlar, `per-06-01/02` Cari**. |
| **Frontend** `router/index.js:24` | 17 routes | **`/coalpanel/orders` OrderList, `/orders/form/:id` OrderForm** |
| **Sidebar** `Sidebar.vue:200` | Talep/Teklifler | **Siparişler only** (Sipariş Listesi — Oluştur removed, SAP-only) + `Dökümanlar` + `Firma` + `Yönetim`. |

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
| KG / M | **Required.** At least one serial row. Default serial_no = `-`. User adds rows via "+" button. Quantities must sum to split amount (partial) or full item qty (at_once). |
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
- **KG/M table:** Parti No (default `-`), Malzeme Üretim Tarihi (flatpickr month picker), Miktar, "+" to add rows, "-" to remove (min 1 row kept)
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

## 4. Files Changed (key — 2026-08-28 + 2026-08-31 sessions)

| File | Change |
|------|--------|
| `OrderSystemSeeder.php` | + `op-doc-order-serial`, `op-doc-order-serial-form` |
| `OrderItemTable.vue` | **Complete rewrite:** inline split inputs, quantity type awareness, serial entry UI (ST checkbox, KG/M table), collapse button, scrollable per-item, summary badge, flatpickr month picker, at_once mode serial support, `fetchSerialsForItems()` loads existing serials, collapsible read-only serial view (collapsed by default, `serialViewCollapsed` state), `formatSerialDate()`, `ensureAtOnceSerials()`. **+Excel serial upload (2026-08-31):** `import * as XLSX from 'xlsx'`, `excelFileInputs` ref map, `triggerExcelUpload(item)` captures `_excelUploadSplitAmt`, `downloadExcelTemplate()` generates template xlsx, `parseSerialExcel(item, file)` reads Excel client-side + replaces serials + ST mismatch Swal confirm + syncs split amount. `_excelUploadTime` flag prevents `rebuildSerials` overwrite (1000ms). `serialCollapsed[item.id] = false` after upload. 4 "Excel'den Yükle" + 4 "Şablon" buttons across all serial sections. **+KG/M serial → Böl input sync (2026-08-31):** `syncSplitFromSerials()` auto-updates splitAmounts from serial quantities sum; `_syncingSplit` flag prevents infinite loop between `splitAmounts` ↔ `serials` watchers. Called from `addSerialRow`, `removeSerialRow`, serial quantity change (debounced `serials` watcher), Excel upload. |
| `package.json` | + `"xlsx": "^0.18.x"` dependency |
| `OForm.vue` | `selectedItems` format `[{qnid, amount, serials}]`, `allItemSerials` for at_once, serial validation, `atOnceMode` prop + `@serials` listener, clone origin banner, transfer info card. **+Malzeme Kabul Formu print (2026-08-31):** "Malzeme Kabul Formu Yazdır" button (green, icon) in transfer card. `printMalzemeKabul()` method: validates İmalatçı firma required, Swal error modals with "Tamam" close, collects items (all/selected based on transferMode), calculates clone order_no suffix via `POST /v1/table/documents` query, submits `FormData` to `POST /v1/export/malzeme-kabul`. `getFieldValue()` helper for dynamicF, `orderFormEntities` computed, `getCurrentFormData()` ref method. |
| `ExportController.php` | **+`malzemeKabul()` (2026-08-31):** `POST /v1/export/malzeme-kabul` — accepts `qnid`, `items` JSON, `order_no`, `buying_no`, `created_at`, `order_desc`, `imalatci_firma_adi`. Reads `getFormData()` for DB entities. Uses DB entities as PRIMARY source (not request) — `imalatci_firma_adi` always from saved data, only overrides if frontend sends non-empty. |
| `DocumentServiceProvider.php` | `processOrderTransfer($qnid, $mode, $selectedItems, $itemSerials)` — handles partial clone + at_once serials. `duplicateOrderItem` accepts `$splitAmount`. New: `decrementItemQuantity`, `storeOriginalQuantity`, `createSerialEntries`, `setHasSerialsFlag`. |
| `DocumentController.php` | Passes `$itemSerials` to `processOrderTransfer` |
| `OList.vue` | Clone order "den ayrıldı" link with `findAndNavigateToOrder` method |
| `Sidebar.vue` | **REMOVED "Sipariş Oluştur" menu item** — orders come from SAP, only "Sipariş Listesi" shown |
| `OForm.vue` | **No-id guard** — `/coalpanel/orders/form` (no id) redirects to `OrderList`. Form requires a SAP-created order id. |
| `CspMiddleware.php` | + `'unsafe-eval'` to `script-src` (sweetalert2 uses `new Function()` to parse form input values in Swal HTML). Local `IS_TEST=true` disables CSP header entirely; change matters for production. |

## 5. How Data Flows Now

```
SAP sends flat items → cron groups by EBELN → order + items
→ /coalpanel/orders/form/:id → OrderItemTable (items row list)
  → fetchItems: load items with parent_id filter
  → fetchSerialsForItems: for items with has_serials=1, fetch op-doc-order-serial children
  → Existing serials shown as collapsible read-only section (collapsed by default)
→ Transfer card: Tek Seferde (at_once) | Parçalı (partial)

AT-ONCE:
→ All items show serial entry (KG/M: auto first row via ensureAtOnceSerials, ST<300: checkbox)
→ SAVE → PUT /v1/document/{id} + transfer_mode='at_once' + item_serials
→ processOrderTransfer: setStatus transfer_sent + createSerialEntries per item

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
→ Validates: imalatci_firma_adi required (Swal error if empty)
→ Collects items (all for at_once, only selected for partial)
→ Calculates clone order_no suffix by querying existing clones via POST /v1/table/documents
→ buying_no (SUBMI) stays original — NO clone suffix
→ POST /v1/export/malzeme-kabul with FormData (qnid, items JSON, order_no, buying_no, created_at, order_desc, imalatci_firma_adi)
→ ExportController::malzemeKabul() — DB entities as PRIMARY source for imalatci_firma_adi (not request)
→ Blade template: exports/malzeme-kabul.blade.php — A4 portrait, title underlined, Alım No/Sipariş No stacked left, Tarihi right, 2px table borders, signature grid
→ dompdf renders PDF → download as malzeme-kabul-{order_no}.pdf
→ Flow: fills form → clicks Print → PDF downloads → prints on paper → signs with pen → scans → uploads signed PDF to transfer_kabul file field → saves order
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
- **`op-doc-transfer` type** still in dict — could delete
- **Malzeme Cins-Miktar Kabul Formu** — second PDF form (PENDING)
- **Route ordering** — `POST /v1/export/malzeme-kabul` MUST be before `POST /v1/export/{model}/{type?}` wildcard in `api.php` (line 42, before line 45)

## 8. Credentials & Infra

- **Docker:** `tedarikNewApp` postgres `61d0571c2f7b` on `127.0.0.1:5431`
- **Panel .env:** `DB_DATABASE=tedarikNewApp` `:5431`
- **Artisan:** `php artisan serve --host=127.0.0.1 --port=8000`
- **Login:** `kadir@kontent.com.tr / Kadir412.` → 2FA `111111`
- **Seeds:** 23 migrations + seeders. `OrderSystemSeeder` now has 20 dict rows (+serial type/form)
- **Fresh data:** `php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh` (8 orders, 23 items, mixed ST/KG/M)

## 9. How To Resume

```bash
docker start tedarikNewApp || docker run --name tedarikNewApp -e POSTGRES_USER=tedarikNewApp -e POSTGRES_DB=tedarikNewApp -e POSTGRES_PASSWORD=tedarikNewApp -p 5431:5432 -d postgres:latest
cd panel && php artisan migrate:status # 23 done
cd panel && npm run build # after any Vue/edit
php artisan serve --host=127.0.0.1 --port=8000
# login kadir / Kadir412. / 111111 → /coalpanel/orders
# fresh data: php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh
```

## 10. Known Bugs / Debt

- **File replacement** — FIXED 2026-08-28. Do NOT change without Master's approval.
- **`syncOrderStatusFromFiles`** — only fires from `documentFileStatus`, NOT from `registerContent`.
- **Lock** — `ready_for_shipment` FULL lock. `files_rejected` keeps files editable.
- **ST serial max** — capped at 300 rows (matches qty cap). ST >= 300 = no serial entry.
- **PHP `??` vs `?:`** — `??` (null coalescing) doesn't fall through on empty string `''`. Always use `?:` or `!empty()` when checking frontend values that could be `''` (2026-08-31 lesson learned: imalatci_firma_adi bug).
- **Route ordering** — Export routes MUST be before `{model}` wildcard in `api.php` or they'll never match (Laravel matches first route).
