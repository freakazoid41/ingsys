# Roadmap — Plans For Next Sessions

> **Front panel is the crown jewel — you said you'll give design (`memory/idea.md`). Until then, here's the ordered attack plan so any LLM can pick up without asking you twice.**
> **Completed-work log moved to `memory/05-order-system-state.md` §4/§6/§10 — do NOT re-log it here.**

## 1. Immediate Next (Choose 1)

### Option A: Tedarik Panel Orders → Build Client Order Flow (DONE 2026-09-02 late — polish)
Login shell DONE (`/tedarik` 560×840 140px Gdz → unified orange 2FA → `/tedarikpanel` `TedarikPanel.vue:386` card 1360×12 sidebar 210 6 tabs 64×12 -52 protruding, logo 82 + label, menu flex1 centered, `OList isTedarik` card-rows `pickletable height auto`, `Dashboard.vue` placeholder, route `router/index.js:110` reuses `OList/OForm`). Next:
1. **Order List tedarik view:** filter by client LIFNR (`currentStatus.clientQnidList` if reseller), hide admin actions, show `transfer_sent/files_rejected` badges — **UI DONE**, filter wiring PENDING.
2. **Order Detail tedarik:** order header readOnly + `OrderItemTable` with `readonly` + file upload (test+images) + transfer card at_once/partial + serials (KG/M required, ST optional), same `processOrderTransfer` payload — **reuses OForm**.
3. **File + serial wiring:** reuse `Form.vue` + `pickle.js` temp-upload (already done in admin OForm — clone for tedarik).
4. **Rejected:** `files_rejected` orange + re-upload unlock + `still-rejected` toast — done.

### Option B: Dashboard Rebuild
1. Replace coal `topstats/monthlyoffers` with order queries (pending transfers, awaiting files, approved today)
2. Header bell for order notifications
3. Report cards per tenant GDZ/ADM

### Option C: Branding — DONE 2026-09-02
`CATES/YATAGAN → GDZ/ADM` (`public/index.php` `adm ? ADM : GDZ`, `GDZ.svg/ADM.svg`, `gdz/adm.jpg`, `SysSeeder` `GDZ Sistem`, 16 files bulk, `emails/layout` logo, DB re-seed `grp_code=GDZ`). Admin `/` still blue CATES? Now GDZ. Tedarik `/tedarik` orange Gdz.

## 2. Completed (recent)

> **Full change log lives in `memory/05 §4` — only highlights kept here.**

- **Serial system:** KG/M required, ST<300 optional, Excel upload, collapse UI, auto date
- **Split/partition:** quantity types, clone `EBELN-X`, partition lock, quantity restore
- **Malzeme Kabul/Cins-Miktar PDF** + reprint; file replacement v2 + `syncOrderStatusFromFiles`
- **Tedarik public panel** `/tedarik` → `/tedarikpanel` + `GDZ/ADM` rename, typewriter paper-feed, Module Switcher, detailed search, OList/OForm shared via `isTedarik`
- **Tedarik Doküman** `/tedarikpanel/documents` live — `DList isTedarik` flat list, LIFNR-scoped, row = OList CardRow (tr white/radius14/shadow, td borderless), `75vh`, `Kullanıcılar` tab removed
- **File-Detail OLD-APP replica** `GET /api/v1/file-detail` + `DForm` with old-version/decidable locks

## 3. Pending — Malzeme Cins-Miktar Kabul Formu Content

- ✅ **Mechanics DONE (2026-09-01):** blade template, controller method, route, Vue method, 2 purple buttons, CSS — all cloned from Malzeme Kabul
- ✅ **Content DONE (2026-09-01):** Blade rewritten — title "SEVK EDİLECEK MALZEMENİN CİNSİ VE MİKTARI", header 4-row vertical table (label | ":" | value), items flattened by serials (Seri/Parti column), signature grid. Controller adds `ctitle` as `company`. Frontend flattens items by serials.
- ✅ **Serial fix DONE (2026-09-01):** `printMalzemeCinsMiktar` reads frontend serial state (`itemTable.serials`) instead of DB-only (`item.serials`) — works for new partitions where serials aren't saved yet.

## 3b. Item File Uploads — DONE (2026-09-01)

- ✅ Collapsible "Dosyalar" section per item in OrderItemTable
- ✅ Test Dökümanı: single file, rejectable/acceptable (`item_test_docs` group)
- ✅ Ürün Görselleri: multi-file, no accept/reject (`item_images` group)
- ✅ Existing files fetched from DB on load, shown with status badges
- ✅ New uploads via temp-upload, saved via separate item PUT after main order save
- ✅ Uses existing connId from SAP sync (avoids duplicate sys_con_ops rows)
- ✅ **FIX product image removal (2026-09-01 late):** `fetchItemFiles` stores `entity_tag`/`connId`, `removeExistingImageFile` tracks `_removedExistingFiles` with key, `OForm.saveItemFiles` generic `removedData` — images now deactivate correctly
- ✅ **Preview for item docs (2026-09-01 late):** `previewExistingFile` iframe modal (encrypted name `salt:iv:ct` broke old `\.pdf$` check) + `previewLocalImage` pdf→iframe/xls→download; chips clickable + eye button `oic-item-file-preview`
- ✅ **Thumbnail grid + gallery (2026-09-01 late):** `oic-image-grid` 84×84 `src="/order-file/{qnid}"` + `URL.createObjectURL` + `onThumbError` + `gallery:{visible,items,index}` `getGalleryItems` `openGallery` `teleport` `oic-gallery-overlay` `prev/next 48px` `Esc/←/→`
- ✅ **Split overlay icons (2026-09-01 late):** `oic-split` 50/50 `eye 22px` `cross-circle 22px` `rgba(79,70,229,0.82)` / `rgba(225,29,72,0.84)` `blur(3px)` — was pill `Önizle/Sil` bar
- ✅ **Lock for images (2026-09-01 late):** `readonly` prop (`OForm :readonly="isLocked"`), guards + `v-if="!readonly"` for `Görsel Ekle`/`Sil` + `left.full` preview-only when locked
- ✅ **Test doc non-removable (2026-09-01 late):** Existing `accepted/pending` no X; rejected `Yeni Test Dökümanı Yükle` always visible (even when `isLocked`) — insert on save, replace only when `doc_file_rejected` via `existingId`
- ✅ **Loader for Kabul/Cins (2026-09-01 late):** `OForm printingKabul/printingCins` + `Swal PDF oluşturuluyor... ki-loading spin` + `disabled Oluşturuluyor...` covering clone suffix + export fetch, `@keyframes spin`

## 4. Tech Debt (kept out of `05 §10`)

- **Parent link:** `Documents::tableList` still `where parent_type_id=0` — items visible in main lists
- **Security:** `DEV_ADMIN 111111`, `resetusercradentals` public, `CSRF off`, `decryptFile IDOR`, `pickle hardcoded`, files served in webroot
- **Old containers:** `B2X` still exists stopped on `5431`
- **`permission_version` cache store `file`** — `refreshAllUserPermissions` buggy; users must relogin if heartbeat fails

## 5. How Future LLM Should Resume

1. Read `memory/05-order-system-state.md` (current snapshot) + this file + `01-form-engine.md`.
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