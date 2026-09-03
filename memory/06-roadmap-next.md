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

All logged in `memory/05 §4`. Highlights:
- Serial system: KG/M required, ST<300 optional, Excel upload, collapse UI, auto date
- Split/partition: quantity types, clone `EBELN-X`, partition lock, quantity restore
- Malzeme Kabul/Cins-Miktar PDF + reprint; file replacement v2 + `syncOrderStatusFromFiles`
- SAP sync fresh data (8 orders / 21 items, 2026-09-02 08:20 re-seed GDZ, `grp_code=GDZ`)
- 2026-09-02: rejected `Yeni Test Yükle` unlock + still-rejected toast + DList grouping header full-length + pickletable header-cache + popup + join fix
- 2026-09-02 late: Tedarik public panel `/tedarik` → `/tedarikpanel` (orange 560×840 140px Gdz, unified 2FA 560×720), `GDZ/ADM` rename + DB re-seed, `public/index.php` `adm ? ADM : GDZ` + **TedarikPanel polish 2026-09-02 late: card 1360×12 sidebar 210 6×64px tabs -52 protruding 38px, logo 82 + label, menu centered, OList card-rows 0 7px, pickletable guard**
- 2026-09-02 late: **TRANSFER PURGE** — `op-doc-transfer` + `op-doc-transfer-form` + `doc_trans_transfer_*` (4) purged (6 rows) + `OrderSystemSeeder`+`DocumentServiceProvider`+`DocumentController`+`PermissionHelpers`+`Form.vue` (113-line) + `OList`/`OForm` → only `op-doc-order` + `doc_trans_order_*`; bug `OList:248 transfer_approved → blocked` fixed
- 2026-09-02 late: **Module Switcher + Detailed Search** — `Sidebar.vue:393`/`TedarikPanel.vue:64` `Modüller` above Çıkış → modal `Yönetim|Tedarik` `/coalpanel|/tedarikpanel`, `OList.vue` detailed 3×3 hover `absolute top:52 z40` hidden by default (`showDetailed:false`, `Detaylı Filtre` toggle) + `Documents::tableList` 9 keys, kept **SHARED** `OList/OForm` via `isTedarik` (decision 2026-09-02)
- 2026-09-02 late: **Filter Overhaul** — `OList.vue` `Filtreler` dropdown `teleport fixed z9999` `9` radios + `Şirkete Göre Arama` → `client-modal 720px` `modalClients` lazy `200` `hardFallback 8` + `PickleTable local 8` + `flatpickr range Y-m-d → tarih_araligi |` `to_date` BEDAT, `Detaylı` twin `Şirket/Tedarikçi` `readonly` → same modal `single`, `persons` fetch killed, `Documents.php` ordering `alim/siparis` + multi `sirket/transactions IN`
- 2026-09-02 late: **Filter Fixes** — `OList.vue:376 sirkete_gore` empty `showClientModal` → `openClientModal('multi')` + instant `hf` + `PickleTable data: localData` scope → `this.modalClients.slice()`; `Detaylı Filtrele/Sıfırla` auto-close `showDetailed=false` `OList.vue:519/533`
- 2026-09-02 late: **Tedarik Detail Fresh Order 6-Step** — `OForm.vue:966` `isTedarik` header+warning+1 `OrderItemTable` +2 `tedarikDesc`+3 `tedarikImalatci`+4 `printMalzemeKabul/Cins`+5 file temp-upload+6 Gönder, `formatDate` fix `OForm.vue:963`, injected `dynamicF` + `dynamicFile` for `transfer_kabul|transfer_cins`
- 2026-09-02 late: **Typewriter Scroll — holder-fixed/paper-feed** — `TedarikPanel.vue:551` `root:fixed inset:0 overflow:hidden` + `frame:calc(100vh-40px) overflow:visible` + `main:overflow:visible bg:#fff` + `main-inner:height:auto bg:#fff translateY(-scrollY)` + `body height=scrollHeight+40` + `ResizeObserver` + `watch $route` — only `tedarik-main` travels, hidden at browser viewport, `frame+tabs` pinned, `scrollBehavior:smooth`, mobile disabled
- 2026-09-02 late: **White BG Fix** — `OForm.vue:1463 tedarik-detail bg:#fff` + `TedarikPanel main/main-inner bg:#fff` — gaps between `tedarik-step-card`s not floating on grey emptiness outside `frame`; overflow white paper clipped at viewport (browser limit)
- 2026-09-02 late: **Tedarik Items Theme image 1:1** — `OrderItemTable hideHeader` + `tedarik-thead` + `oic-row--tedarik` (orange pill + gray Detaylar, `getKPartiNo`/`getTedarikDurum`), header `Sipariş Kalemleri` + `#28` removed, wrapper double border removed, `oic-hide-header` borderless
- 2026-09-02 late: **Header/Wrapper Cleanup** — `Sipariş Kalemleri` header stripped for tedarik only, outer wrapper `bg:#fff border` removed, `oic-hide-header` makes card flush
- 2026-09-02 late: **Transfer Locked** — `isTransferLocked`/`tedarikDisplayMode` disables radio + banner on locked, step 6 hidden on locked (not new/rejected), `mounted` syncs `transferMode`
- 2026-09-02 late: **Existing Files Locked** — `parseTedarikExistingFiles` + `getTedarikDisplayName` + `previewTedarikFile` + status badges + lock note
- 2026-09-02 late: **Panel-Aware Routes** — `navigateToParentOrder`/`findAndNavigateToOrder`/`goBackToList` → panel-specific route names
- 2026-09-02 late: **Header Beautified** — `← Tüm Siparişler` fancy pill + `office-bag` icon + `TDNO` badge + right-aligned meta + `tedarik-status-dot` + gradient top bar
- 2026-09-02 late: **Step 6 Hidden** — Gönder only for new/rejected, not locked (transfer_sent/ready/approved/rejected)
- 2026-09-02 late: **Checklist Cleanup** — duplicate `Genişlet/Daralt` removed, file header grey bar gone, memoized ResizeObserver + body height transition
- 2026-09-02 late: **Drum Status** — `OForm tedarikStatus` pill in `tedarik-detail-header` shows order current status (`Beklemede`/`Kontrol`/`Sevke`/`Onay`/`Red`)
- 2026-09-02 late: **At_Once Collapsed + Detaylar Master** — `isCollapsed` default true for atOnce tedarik, `tedarikDetailsCollapsed` + `Detaylar` toggles whole `tedarik-additional` (files+serial+split) + `Genişlet/Daralt` hidden `v-if="!hideHeader"` + empty file header `display:none`
- 2026-09-02 late: **Checkbox Auto** — tedarik partial checkbox `toggleCard` auto opens/closes whole drawer via `tedarikDetailsCollapsed`

- 2026-09-03: **Aksiyonlar** — Kalite Onayı Ver ve Kapat (tüm dosyalar kabul) + Sipariş No -X suffix-only (base lock, duplicate check) + status pill disabled tedarik + ended guard
- 2026-09-03: **Desc/Imalatci lock** — files_rejectedte açıklama/imalatçı kalıcı kilit, sadece dosyalar açık; submitForm skip
- 2026-09-03: **SQL 22P02** — handleTedarikTempUpload rsp vs rsp.data + fkey **rowId + backend leftPart !numeric→0 + existingFileId + like prefix
- 2026-09-03: **File split** — admin mechanic mirror (like prefix, reuse old.tag) 3510004600-2 7→0 + 19.replaced_id=7 + tag repair
- 2026-09-03: **Status label** — Transfer Gönderildi→Dosyalar Kontrol Ediliyor, Approved→Kalite Onayı Verildi, DB UPDATE + labelMap

- 2026-09-03 late: **Who/Why visible** — getTedarikNote unwraps JSON t.description.note, inline Reddeden/Onaylayan + Notu Gör modal with who+when+note
- 2026-09-03 late: **Accepted visible** — step5 accepted not hidden when other rejected, green lock + status
- 2026-09-03 late: **Gönder loading** — isSubmitting + ki-loading spin + Kaydediliyor


- 2026-09-03: **Tedarik Doküman LIVE** — `DList.vue isTedarik` `/tedarikpanel/documents` (shared, `isTedarik` flat list grouping REMOVED expensive → `Sipariş Kodu` `group_key` for test files parent, LIFNR-scoped `Document_files::tableList` for reseller, `router/index.js:58` + `TedarikPanel.vue:164` menu fix, `height:auto number` both panels)
- 2026-09-03 late: **Height fix** — `pickletable height:auto` both panels (was 100%/90% + calc(100vh-280) → blank), `DList` card `overflow:hidden 16px`, `CoalPanel Simplebar` + `Tedarik typewriter` content-driven (docs page `frame/main height:auto` early-return), `DList` `order-list-body flex:0 0 auto`, `tedarik-docs` orange `fff7ed`
- 2026-09-03 late: **Grouping removed** — `DList` `groupBy` + `enhanceGroupHeader` deleted, `Sipariş Kodu` column live for order+test files

- 2026-09-03 late: **File-Detail OLD-APP replica LIVE** — `GET /api/v1/file-detail` + `DForm.vue` `Gdz` header `1fr 130 300 90` `isOldVersion` lock, `isDecidable` lock after accepted/rejected, `İncele` excluded to `90px`, widths unified `300px`, `Sipariş / İlişki` merge, `Yeniden Talep Et` text pill + nice modals, `goOrder` shortcut

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