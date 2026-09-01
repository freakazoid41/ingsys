# Roadmap — Plans For Next Sessions

> **Front panel is the crown jewel — you said you'll give design (`memory/idea.md`). Until then, here's the ordered attack plan so any LLM can pick up without asking you twice.**
> **Completed-work log moved to `memory/05-order-system-state.md` §4/§6/§10 — do NOT re-log it here.**

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

## 2. Completed (recent)

All logged in `memory/05` — summary in `memory/08 §5`. Highlights:
- Serial system: KG/M required, ST<300 optional, Excel upload, collapse UI, auto date
- Split/partition: quantity types, clone `EBELN-X`, partition lock, quantity restore
- Malzeme Kabul PDF + reprint; file replacement v2 + `syncOrderStatusFromFiles`
- SAP sync fresh data (8 orders / 23 items)

## 3. Pending — Malzeme Cins-Miktar Kabul Formu Content

- ✅ **Mechanics DONE (2026-09-01):** blade template, controller method, route, Vue method, 2 purple buttons, CSS — all cloned from Malzeme Kabul
- ✅ **Content DONE (2026-09-01):** Blade rewritten — title "SEVK EDİLECEK MALZEMENİN CİNSİ VE MİKTARI", header 4-row vertical table (label | ":" | value), items flattened by serials (Seri/Parti column), signature grid. Controller adds `ctitle` as `company`. Frontend flattens items by serials.

## 4. Tech Debt (kept out of `05 §10`)

- **Parent link:** `Documents::tableList` still `where parent_type_id=0` — items visible in main lists
- **Security:** `DEV_ADMIN 111111`, `resetusercradentals` public, `CSRF off`, `decryptFile IDOR`, `pickle hardcoded`, files served in webroot
- **Old containers:** `B2X` still exists stopped on `5431`
- **`permission_version` cache store `file`** — `refreshAllUserPermissions` buggy; users must relogin if heartbeat fails

## 5. How Future LLM Should Resume

1. Read `memory/08-session-summary.md` + `05-order-system-state.md` (current snapshot) + this file + `01-form-engine.md`.
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