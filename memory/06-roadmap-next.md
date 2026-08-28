# Roadmap — Plans For Next Sessions

> **Front panel is the crown jewel — you said you'll give design (`memory/idea.md:31`). Until then, here's the ordered attack plan so any LLM can pick up without asking you twice.**

## 1. Immediate Next (Choose 1)

### Option A: Front Panel Design Arrives → Build Client Flow (Recommended)
You drop Figma/HTML for `front panel (new)` — we build:

1. **Route & Layout:** New `FrontPanel.vue` (client skin, no coal), `router/index.js` → `/front/orders` + `/front/orders/:id` + `/front/transfers` (client view). Separate from `/coalpanel` admin shell (`panel/resources/views/coalapp.blade.php` → two blades or host-based like `public/index.php:11` SYS_CODE). Auth reuses `persons/users` + `2FA` (`AuthController.php:873`), but `typeKey=op-pert-reseller` session `canProceed/canResponse` already works (`PersonsServiceProvider.php:566`).
2. **Order Detail Client View** (`idea.md:2-9`): **backend already handles this** — order header (readOnly) + item table + `order_desc` + `imalatci_firma_adi` + `transfer_kabul`/`transfer_cins` files, and SAVE triggers `processOrderTransfer` (`at_once`/`partial` + `selected_items`). The front panel skin just needs to render these and call the same `PUT /v1/document/{id}` endpoint with `transfer_mode` + `selected_items`.
3. **File upload wiring:** Reuse `Form.vue` schemas `op-doc-order-form` but render in front panel style. Ensure `pickle.js:824` `temp-upload` → `finalizeTempFile` → `document_files` → `doc_file_waiting`.
4. **Rejected files:** client sees `files_rejected` status + can re-upload (only `files_rejected` keeps files editable; `ready_for_shipment`/`transfer_sent` are FULL lock incl. files, `OForm.vue:63` `isFilesLocked`) + re-save → `transfer_sent`.

**You need to give:** HTML/CSS or Figma, and whether front panel uses same `coalapp.blade.php` or new blade, and host (e.g. `tedarik.aydemenerji.com.tr` vs `komurtedarik.*`).

### Option B: No Design Yet → Build SAP Dummy Ingest + Cron Now
We can unblock data without UI (STILL PENDING):

1. **Create `panel/app/Console/Commands/SyncOrdersCommand.php` + `panel/routes/api.php` `POST /api/v1/orders/dummy-ingest` (admin only `per-05-02`):**
   ```php
   // payload = your SAP array: [{BUKRS, LIFNR, EBELN, EBELP, MCOD1, MATNR, TXZ01, MENGE, MEINS, BEDAT, SUBMI, NETPR, WEMNG}, ...]
   // group by EBELN → one Documents op-doc-order (order_no=trim EBELN, buying_no=SUBMI object?'-', spec_code=LIFNR keep zeros as string, sys_code=BUKRS, ctitle=MCOD1, created_at=BEDAT)
   // each row → Documents op-doc-order-item parent_id=order.id, prod_code=trim MATNR**trim EBELP, title=TXZ01, quantity=MENGE, unit=MEINS, deal_price=NETPR??'0', received_price=WEMNG??'0', met_code=-1 etc.
   // idempotent: EBELN is natural key → find existing via EAV order_no entity, upsert via registerContent with id.
   ```
   Reuse your exact mapping from `idea:2`. Add `grp_code = BUKRS` via `target_type` entity if you want tenant filtering.
2. **Cron:** `panel/app/Console/Kernel.php` schedule `orders:sync` daily (like `request:autoclose 01:00`), or manual `php artisan orders:sync`. Later replace dummy with real SAP HTTP (you said later `SAP cron will save orders to our db`).
3. **Verify:** `php artisan tinker` created `3510001793` already — dummy endpoint will do same but bulk.

**I can build this now without your design — just say "dummy ingest".**

## 2. After Client Flow, Then Admin Ruling

1. **Dökümanlar file approve flow** (`idea.md:10-11`): **DONE** — admin at `/coalpanel/documents` has `set-file-status` (`Kabul Edildi`/`Reddet`) per file (`DocumentController.php` `per-07-02`). Now wired: rejecting a file auto-flips the parent order to `doc_trans_order_files_rejected` (`syncOrderStatusFromFiles`); accepting all flips it back to `transfer_sent`. Filter tabs per file type (`transfer_kabul/transfer_cins/item_test/item_images`) still could be added.
2. **Transfer approve / reject whole order** (`idea.md:12`): Admin approves via `POST /api/v1/trans/set-status` `doc_trans_order_approved` (green) or rejects via `POST /v1/orders/cancel` (terminal `rejected`, list `İptal Et` or detail button). File-rejected order stays `files_rejected` until client re-uploads + re-sends → `transfer_sent`.
3. **Dashboard rebuild** (`panel/app/Providers/ReportServiceProvider.php:80`): **STILL PENDING** — Replace coal `topstats/monthlyoffers` with order queries: `pending transfers (doc_trans_order_transfer_sent), awaiting file approvals (doc_file_waiting count), files_rejected, approved today, etc.` + `Header.vue` bell `GET /api/v1/notifications` (extend `getAdminNotifications` for `op-doc-order`).
4. **Skin:** Replace `Kömür Tedarik` branding → Order System (logo `SYS_CODE.svg`, `coalapp.blade.php` title, `coaltheme` css). Keep `public/index.php:11` multi-tenant if you still need `YATAGAN/CATES` via host, or hardcode.

## 3. Tech Debt To Fix When You Have Time

- **Parent link:** `DocumentServiceProvider.php:86` now supports `parent_id/parent_qnid` but `Documents::tableList:102` still `where parent_type_id=0` → all items remain visible; if you later want true hierarchy (hide children from main lists), add `filter parent_id` param.
- **File type UX:** `Form.vue` shows `Max. Boyut: 40 MB` hardcoded — keep, but ensure server `uploadFile` 42MB matches.
- **Security:** Same coal debt: `DEV_ADMIN 111111`, `resetusercradentals` public, `CSRF off`, `DocumentHelpers decryptFile IDOR`, `pickle hardcoded` — kill later if this goes public.
- **Old containers:** `B2X` still exists exited on same `5431` host port — keep stopped; starting it will clash with `tedarikNewApp`. Remove if you're done with coal data: `docker rm B2X`.
- **Memory:** This `memory/` is your session brain — keep `00..06` up to date; `panel/docs/` 11 files are stale coal docs, don't trust blindly.
- **File replacement:** Original mechanic is STABLE. Do NOT change `registerContent` file entity matching or `old_versions` SQL without Master's explicit approval.

## 4. How Future LLM Should Resume

1. Read `memory/00-core-overview.md` + `05-order-system-state.md` (current snapshot) + `06-roadmap-next.md` (this file) + `idea.md` + `01-form-engine.md`.
2. Check `docker ps -a | grep tedarikNewApp`, `panel/.env: DB_DATABASE`, `php artisan migrate:status`.
3. The admin transfer flow (at_once/partial clone, files_rejected, cancel) is **already built** — verify `POST /api/v1/orders/cancel` route + `doc_trans_order_files_rejected` status exist. If front design given, build the **client front-panel skin** (`Option A`) calling the same `PUT /v1/document/{id}` with `transfer_mode`/`selected_items`; else offer `Option B` dummy ingest.
4. After any `Form.vue` or `router/index.js` or `Sidebar.vue`/`OForm.vue`/`OList.vue`/`OrderItemTable.vue` edit → `npm run build` in `panel/` → `php artisan serve --host=127.0.0.1 --port=8000` → test `kadir@kontent.com.tr / Kadir412. / 111111`.
5. Always update `memory/05` after major change, so next session doesn't hallucinate.

## 5. Decision Tree For Master

- **"Front design ready" → build the client front-panel skin** (route + layout + order detail calling the already-built transfer/clone backend: at_once/partial radio + item checkboxes + desc/imalatci/2 files → `PUT /v1/document/{id}`)
- **"Dummy first, design later" → build `POST /api/v1/orders/dummy-ingest` + cron that handles your exact SAP payload mapping, bulk upsert, parent linking** (STILL PENDING)
- **"Dashboard first" → rebuild `ReportServiceProvider.php` order stats + Header bell** (STILL PENDING)
- **"Skin it" → rebrand coal → tedarik (logo, title, theme)**
