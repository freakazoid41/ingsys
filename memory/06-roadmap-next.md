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

## 3. Pending — Malzeme Cins-Miktar Kabul Formu
- Second PDF form for order transfer (PENDING)
- Similar flow to Malzeme Kabul Formu but with different layout/data
- Will need its own blade template + backend endpoint + print button

## 4. Tech Debt

- **Parent link:** `Documents::tableList:102` still `where parent_type_id=0` — items visible in main lists
- **Security:** `DEV_ADMIN 111111`, `resetusercradentals` public, `CSRF off`, `decryptFile IDOR`, `pickle hardcoded`
- **Old containers:** `B2X` still exists stopped on `5431`
- **File replacement:** FIXED, do NOT change without approval
- **`syncOrderStatusFromFiles`** — only fires from `documentFileStatus`, not from `registerContent`
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
