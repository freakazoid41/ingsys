# Tedarik System Process — End-to-End Order Lifecycle (2026-09-04)

> **Purpose:** Malzeme Tedarik İş Süreci — SAP creates purchase orders, suppliers (Tedarikçiler) complete them with serials + files, GDZ/ADM (İB) reviews files and closes orders. This is the single source of truth for testing both panels.
> **Read after:** `logging-mechanics.md`, `file-upload-versioning-mechanics.md`, `form-system-mechanics.md`, `session-and-login-mechanics.md`, `memory/05-order-system-state.md`.

**Key files:**
- `app/Console/Commands/SyncOrdersCommand.php` — SAP sync
- `app/Providers/DocumentServiceProvider.php` — `registerContent`, `processOrderTransfer`, `duplicateOrderItem`, `decrementItemQuantity`, `createSerialEntries`, `syncOrderStatusFromFiles`, `applyOrderStatus`, `documentFileStatus`, `acceptAllOrderFiles`, `cancelOrder`, `renameOrder`
- `app/Helpers/DocumentHelpers.php` — `tempUploadFile`, `finalizeTempFile`, `addFileToDb`
- `app/Models/Documents.php:398` — `tableList` (orders), `app/Models/Document_files.php:150` — `tableList` (files)
- `resources/js/pages/coalsystem/Order/OList.vue` + `OForm.vue` (shared via `isTedarik`) + `components/OrderItemTable.vue`
- `resources/js/pages/coalsystem/Documents/DList.vue` + `DForm.vue` (shared via `isTedarik`)
- `app/Http/Controllers/DocumentController.php` + `ExportController.php`
- `app/Services/AuditService.php` — frozen snapshots
- `panel/.env:28` — `tedarikNewApp` :5431, `public/index.php:11` — `adm?ADM:GDZ`

---

## 1. Purpose & Actors

**Purpose:** Converted from KomurTedarik coal ERP. Same EAV engine (`documents` + `sys_con_ops` + `sys_con_entities` + `sys_options`), new business = purchase order fulfilment. No migrations for new fields — only `sys_options` + `Form.vue` + Vue pages.

**Stack:** Laravel 12 / PHP 8.2 / PostgreSQL `tedarikNewApp` @ `127.0.0.1:5431` / Sanctum 4 / Vue 3 SPA @ `/coalpanel` (admin) + `/tedarikpanel` (supplier) `TedarikPanel.vue:551` Vite 6 Pinia 2, Tailwind 3.

**Tenants:** `public/index.php:11` `Host contains adm ? ADM : GDZ` (ex `yatagantermik ? YATAGAN : CATES`) → `$GLOBALS['SYS_CODE']` → `documents.grp_code`, `user_logs.sys_code`. `GDZ.svg/ADM.svg`, `gdz.jpg/adm.jpg`, `GDZ Sistem` in `sys_options`.

**Roles (4 immutable, `SysRoleTemplateSeeder.php:168`, 27 perms `sys_permission_catalogs`):**

| Role | `op_key` | Perms |
|------|----------|-------|
| Admin | `immutable-admin` | 27 perms all |
| Supplier | `immutable-reseller` | `per-041-02`(Tedarik modül) + `per-041-03` + `per-05-01,02` (Sipariş gör/düzenle) + `per-06` (Firma) |
| Rapor Personeli | `immutable-rapor-personeli` | `per-041-01` + `per-05-02` + `per-061-01` (view only) |
| Satınalma Keyuser | `immutable-satınalma-keyuser` | `per-041-01,03` + `per-05 01-05` + `per-06-01` + `per-07-01` |

Strict guards 2026-09-04: `cancelOrder → per-05-04 ONLY`, `renameOrder → per-05-05 ONLY`, `Kalite Onayı (acceptAllOrderFiles) → per-05-03 ONLY`, file status `per-07-02`, file list `per-07-01 && per-07` `SystemController.php:41`, tedarik routes guarded `router/index.js:103` `/tedarikpanel/documents` requires `per-07-01`, `/tedarikpanel/orders` requires `per-05-01`.

---

## 2. Data Model — Critical

1. **Order ↔ Client link = `LIFNR` string (no FK):** `order.spec_code = client.lifnr` entity `Cari Kodu`, keep leading zeros `0000300184`.
2. **Order Items = `op-doc-order-item` docs** `parent_id = order.id` (not `documents.parent_id` legacy). Fields `prod_code (= MATNR**EBELP)`, `title (TXZ01)`, `quantity (MENGE)`, `unit (MEINS: ST/KG/M)`.
3. **Serials = `op-doc-order-serial` docs** `parent_id = item.id`. Entities `serial_no`, `production_date (YYYY-MM-01)`, `quantity`, `unit`. Parent item flag `has_serials=1`.
4. **Forms:** `op-doc-order-form` (order_no/buying_no(SUBMI)/spec_code/sys_code/ctitle(MCOD1)/created_at(BEDAT d/m/Y) readOnly + `order_desc` textarea rows3 + `imalatci_firma_adi` + `transfer_kabul` `transfer_kabul_file` single `hideAdd:true` + `transfer_cins` `transfer_cins_file` single `hideAdd:true`), `op-doc-order-item-form` (prod_code/title/quantity/unit readOnly + `item_test_docs` `item_test_file` single + `item_images` `item_images_file` multi `**img-{id}`), `op-doc-order-serial-form`, `op-doc-client-form + lifnr`.
5. **Status `op-trans-op-doc-order`:** `doc_trans_order_created → doc_trans_order_transfer_sent (Dosyalar Kontrol Ediliyor) → doc_trans_order_ready_for_shipment (Sevke Hazır) → doc_trans_order_approved (Kalite Onayı Verildi) / doc_trans_order_rejected` + `doc_trans_order_files_rejected (Reddedilen Dosyalar Mevcut, auto)`. `documents.status` binary (1 active, 0 cancelled), rich history in `transactions` `op_id 0/1`.
6. **Files:** `op-transfer_kabul_file`, `op-transfer_cins_file`, `op-item_test_file`, `op-item_images_file` in `op-file-types`. Files linked via `sys_con_entities table_tag=document_files entity_value=fileId`. Versioning = new `sys_con_entities` row per upload + `document_files status=1` active, `replaced_id` backward chain.

---

## 3. Step 0 — SAP Sync (Source of Truth)

SAP sends **flat item rows only**. Cron groups by `EBELN`.

**Command:** `panel/app/Console/Commands/SyncOrdersCommand.php`

```bash
cd panel
php artisan orders:sync --json=/tmp/sap_payload.json --dry-run   # preview
php artisan orders:sync --json=/tmp/sap_payload.json             # idempotent skip existing EBELN
php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh # wipe orders+items+EAV+trans then recreate (clients NOT deleted)
```

**Payload shape (`memory/07-sap-sync-mechanic.md:32`):**

```json
[
  {"BUKRS":"4000","LIFNR":"0000300184","EBELN":"3510004200","EBELP":"00010","MCOD1":"DEMİR ÇELİK A.Ş.","MATNR":"40.1.2.001","TXZ01":"Premium Kok 1. Sınıf","MENGE":"3500","MEINS":"ST","BEDAT":"22/08/2026","SUBMI":"SAP-2026-001","NETPR":"0","WEMNG":"0"}
]
```

Mapping: `LIFNR→spec_code`, `EBELN→order_no+transfer_no`, `EBELP appended MATNR**EBELP→prod_code`, `MCOD1→ctitle`, `TXZ01→title`, `MENGE→quantity`, `MEINS→unit`, `BEDAT d/m/Y→created_at`, `SUBMI→buying_no`, `BUKRS→sys_code`. `person_id='system'`.

**Flow per EBELN group:**
1. Skip if `sys_con_entities entity_tag=order_no` exists.
2. Create/find client by `lifnr`.
3. Create `Documents type_id=op-doc-order` + EAV `sys_con_ops form type op-doc-order-form` + entities.
4. Birth `Transactions doc_trans_order_created op_id 0`.
5. For each row: `Documents type_id=op-doc-order-item parent_id=order.id` + EAV + transaction.

**Current live (`memory/05:7` 2026-09-03):** 8 orders, 21 items (21 rows → 8 EBELN), 8 clients, 7 files, 0 serials, 37 trans `grp_code=GDZ` via `/tmp/sap_fresh_payload.json`.

**Idempotency:** check `entity_tag=order_no` with `EBELN`. `--fresh` deletes `op-doc-order` + `op-doc-order-item` docs + EAV + transactions + `op_id=1` file trans + `document_files` + `storage/app/public/documents/*` manually for full clean.

---

## 4. Step 1 — Supplier Login (Single Orange Login)

**Entry:** `GET /` or `GET /tedarik` → `resources/views/auth/tedariklogin.blade.php` orange card `560×840 140px Gdz` `Sipariş Platformu` `E-Posta/Şifreniz 56px` `Giriş Yap 58px #FF4713` `panel/docs` `AuthController.php:873`.

**Flow:**
1. User enters `email / password` (e.g. `kadir@kontent.com.tr / Kadir412.`).
2. `POST /v1/auth/login/tedarik` (`type=tedarik` → `session auth_panel=tedarik`) — reCAPTCHA + `Cache login:attempts 5×15min lock` → `Auth::attempt` → `generateAndSendTwoFactorCode` → 6-digit `storage/app/{token}-{personId}-login.txt` + Mail (`MailService.php:52`) + SMS (`SmsService.php` İletişim Makinesi `UserGatewayWS`) via `contmail*/contphone*`. `DEV_ADMIN` fixed `111111` works for ALL users when `IS_TEST=true`.
3. Redirect `GET /smscallback` → unified orange 2FA `560×720` `loginSms.blade.php` 6×52px inputs 44px countdown.
4. User enters code → `POST auth/checkcode` 120s TTL one-time `Storage::exists + File::lastModified` → `Auth::login` → `loadUserPermissionsToSession` + `clientPermInfo` + `createToken` + `ActiveSession` `forceLogoutPerson` marks old `active_sessions force_logout=true` (single-session `single-session-enforcement-system.md`).
5. If user has both `per-041-01` AND `per-041-02` → redirect `/module-select` blade `resources/views/auth/moduleSelect.blade.php` `560×400` `Modüller` list `Yönetim→/coalpanel` `#154b91` / `Tedarik→/tedarikpanel` `#FF5A1F` via `AuthController::moduleSelect/getAvailableModules` + `GET /v1/modules` API; single module → auto-redirect via `session('target_module')` hidden input.
6. SPA stores token `localStorage token` `public/front/pages/tedariklogin/page.js` → `GET /api/v1/getpermissions` heartbeat 30s `stores/auth.js:43` + `CheckPermissionVersion.php:91` per-request `force_logout` or `permission_version` mismatch → refresh.

**Middleware:** `coalAuth` requires `per-041-01`, `tedarikAuth` requires `per-041-02`, both allow `has('all')` DEV_ADMIN `routes/web.php:69`.

---

## 5. Step 2 — Order List (Tedarik View)

**Route:** `/tedarikpanel/orders` → `OList.vue` `isTedarik = $route.path.startsWith('/tedarikpanel')` shared `router/index.js:110`.

**UI `TedarikPanel.vue:551` typewriter:**
- Root `fixed inset:0 overflow:hidden` + frame `1360px 12px height:calc(100vh-40px) overflow:visible` holder-fixed, sidebar `210px 100%` pinned, `main overflow:visible bg:#fff` + `main-inner height:auto will-change:transform translateY(-scrollY)` paper-feed, body height = `scrollHeight+40`, `ResizeObserver` + `watch $route.path`, mobile `<=992px` disables. `f2f2f3` bg `22px 18px 18px 48px`, logo `82px`, menu `flex1 center 64px 12px -52` protruding 38px, 6 tabs, `pickletable` card-rows.

**Table config:**
- `POST /v1/table/documents` `Documents::tableList:398` with `initialFilter [{form-type=op-doc-order-form}, {type=op-doc-order}]` + optional `is-rodevans` etc.
- **Reseller filter `Documents.php:134`:** if `session clientQnidList` non-empty (from `PersonsServiceProvider::clientPermInfo`), resolve `lifnrs` from `op-doc-client` `lifnr` entities → `WHERE spec_code IN (lifnrs)` for `op-doc-order/item/serial` case. No client → 0 rows (fails closed). Admin open.
- **Detailed search `OList.vue:52` hover `absolute top:52 z40` 3×3 `tedarik-detailed-panel`:** 9 keys `stok_kodu(prod_code EXISTS item)` `siparis_kodu(order_no)` `alim_kodu(buying_no)` `seri_no/uretim_tarihi(EXISTS serial via item)` `sirket/tedarikci(spec_code/ctitle)` `onay_durumu(transactions)` `tarih_araligi(i.created_at or created_at entity)` via `Documents::tableList` EXISTS + lateral `prod_code` for order filter. `Filtrele/Sıfırla/Excel` → `table.setFilter([])`.
- **Filtreler dropdown `OList.vue` 2026-09-02 late:** `Filtreler 280px 9 radios` (`seri_no/tarihe_gore/tarih_araligi/alim/siparis/beklemede/dosya/tamamlanan/hepsi`) + `Şirkete Göre Arama FF4713` → `client-modal 720px PickleTable localData 200` lazy `modalClients` `hardFallback 8` + `flatpickr range Y-m-d → tarih_araligi '|'`, `Detaylı twin Şirket/Tedarikçi readonly → same modal single`, `dropdownPos fixed z9999` teleport to body escaping `tedarik-main overflow:hidden`. Filter ordering: `alim_kodu→buying_no`, `siparis_kodu→order_no`, `stok_kodu→prod_code` lateral, `transactions IN` for `beklemede/tamamlanan` comma, `sirket multi OR lifnr/title`, `tarih_araligi to_date(entity_value,'DD/MM/YYYY')` for `BEDAT` vs `i.created_at Y-m-d`.
- **Rows:** card-rows `border-collapse:separate 0 7px auto 13/13.5px` solid pills + bottom note, hidden `mainSearch`, `clone EBELN-X` shows `3510001793'den ayrıldı` link `findAndNavigateToOrder` via `POST /v1/table/documents all=base`, `Parçayı Sil` for clones vs `İptal Et`.
- **Actions `OList.vue:1005`:** `Aksiyonlar grey + Detaylar teal → Kalite Onayı Ver ve Kapat (per-05-03)` + `Sipariş Numarasını Düzenle clones only -X` suffix-only input `OList.vue:205`. Status pill disabled for tedarik `cursor default`.
- **Height `OList.vue:1005 2026-09-04`:** `.tedarik-main .tedarik-card .pickletable:not(.pt-auto-height) .divTable` + inline `height:90% !important overflow:auto`, pagination stays bottom `75vh/calc(75vh-280px)`.

---

## 6. Step 3 — Order Detail (Tedarik 6-Step) `/tedarikpanel/orders/form/:id`

**Page `OForm.vue:966` shared `isTedarik` computed:**

### 6.1 Header `tedarik-detail-header` 2026-09-02 late
- Gradient `fff→fcfdff radius 16px` + top `3px gradient #FF5A1F→fb923c`
- `← Tüm Siparişler` pill `fff7ed→fff fed7aa hover translateX(-2px)` `goBackToList` panel-aware `isTedarik ? TedarikOrderList : OrderList`
- `office-bag 36px gradient icon` + company `ctitle` + `TDNO :` badge `fff7ed fed7aa`
- Meta right `11.5px #94a3b8 label + 13px bold value` `ctitle/buying_no/order_no/formatDate(created_at))`
- Drum pill `tedarik-status-drum 12.5px 700 tedarik-status-dot 7px` computed `tedarikStatus` from `parsedStatus[last]` `orderStatus:120`: `created→Beklemede #FF5A1F`, `transfer_sent→Kontrol #fef3c7`, `ready_for_shipment→Sevke Hazır`, `approved→Onaylandı #dcfce7`, `rejected→Reddedildi #fee2e2`, `files_rejected→orange`
- Clone banner indigo `Kaynak Sipariş` + `Orijinal Siparişe Git` `navigateToParentOrder` `EBELN-X → EBELN` via search `OForm.vue:1023`

**Data load `OForm.vue mounted`:**
- `GET /v1/document/:qnid` → `DocumentServiceProvider::getFormData` (`sys_con_ops JOIN sys_options group op-doc-forms conn_id=0 status1` + `sys_con_entities LEFT JOIN` + file JSON `CASE table_tag=document_files THEN json_build_object(description,qnid,id,status,last_status)` + `documents + status json_agg ORDER BY t.id`) → `{document, formFormat:{op-doc-order-form:{rowId:{entities,files}}}}` `form-system-mechanics.md:215`.
- `formDataStore.setData(formFormat)` → `Form.vue:2892` hidden scaffolding `<Form ref=formRef formtypes=op-doc-order-form>`
- `checkHasPartitions()` query `POST /v1/table/documents all=baseNo+'-'` → `hasPartitions` boolean.
- `isLocked` computed from `lockedStatuses` includes `files_rejected?false:true` etc. `isTransferLocked = isLocked`, `tedarikDisplayMode = canSend ? transferMode : storedTransferMode`.
- Guards: no-id `/coalpanel/orders/form` redirects to `OrderList`; `isAtOnceDisabled = hasPartitions`.

### 6.2 Step 1 — Transfer Type
`OrderItemTable.vue` `hideHeader=true` `OForm.vue:1023` (admin keeps header):
- Wrapper removed `border:none radius transparent bg transparent` `oic-hide-header`, list `gap8`, `max-height none overflow visible`.
- **Tedarik theme `OrderItemTable.vue` tedarik 2026-09-02 late:** `tedarik-thead` (Malzeme Adı/K.Parti No/Birimi/S.Miktarı / Stokta Mevcut/Durum) + `oic-row--tedarik flex 12px 12.5px orange #FF5A1F pill Test Dokümanı Bekleniyor / green / red` + grey `Detaylar` button, `getKPartiNo()` `**` split, `getTedarikDurum()` from `existingTestFiles` status.
- Radios `Tek Parça (at_once)` vs `Parçalı (partial)` `atOnceMode` prop. If `hasPartitions` → `Tek Seferde disabled grey pointer-events none` + banner, `transferMode='partial'` forced `buildTransferPayload() & printMalzemeKabul() block at_once`.
- Locked `Parçalı` orders show active with lock icon `Sipariş kilitlendi — sevkiyat tipi değiştirilemez` `mounted sync transferMode = storedTransferMode`.
- `DETAYLAR MASTER` `tedarikDetailsCollapsed` toggle `tedarik-additional is-collapsed/is-expanded max-height 3200px 0.38s` around all sub areas; checkbox `toggleCard` when `hideHeader` → checking opens all, unchecking deletes `split/serial` + closes all.
- `AT_ONCE COLLAPSED` default `true` when `hideHeader && atOnceMode && undefined`, Excel forces `false`. `Genişlet` hidden `v-if=!hideHeader` for tedarik.

### 6.3 Step 2 — Açıklama & Step 3 — İmalatçı
- `order_desc` textarea rows3 `tedarikDesc` data, `imalatci_firma_adi` input `tedarikImalatci`.
- **Lock `OForm.vue Desc/Imalatci lock 2026-09-03`:** `:disabled=isLocked` (not `isFilesLocked`), `submitForm` skips `order_desc/imalatci` when locked, grey lock note. Validation `imalatci` required when `isTedarik` in `submitForm`.
- Data inject via `dynamicF['op-doc-order-form**rowId'].entities['order_desc','imalatci_firma_adi']` + `getFieldValue()` handles compound `field**group**id` prefix, `getCurrentFormData()`.

### 6.4 Step 4 — Print (Malzeme Kabul + Cins-Miktar)
- Green `Malzeme Kabul Formu Yazdır` + purple `Malzeme Cins-Miktar Kabul Formu Yazdır` `printMalzemeKabul/Cins` `OForm.vue` `.print-cins-btn #7c3aed→#6d28d9`, also in locked card when `canPrintKabul = canSend || files_rejected` (reprint from rejected uses `effectiveTransferMode = canSend ? transferMode : storedTransferMode`, order_no stays suffix if already `EBELN-X`, items from `itemTable.items` when `selectedItems` empty).
- Flow `OForm printMalzemeKabul`: validates `imalatci` Swal `Tamam` if empty + `hasPartitions` guard, collects items (all for at_once, only selected for partial — both read `itemTable.serials` frontend state not DB `item.serials` for new partitions `2026-09-01 serial fix`), calculates clone suffix `POST /v1/table/documents all=baseNo+'-'` count `→ EBELN-(max+1)`, `buying_no` stays original NO suffix, `FormData qnid,items JSON,order_no,buying_no,created_at,order_desc,imalatci_firma_adi,ctitle→company` → `POST /v1/export/malzeme-kabul|malzeme-cins-miktar-kabul` `ExportController::malzemeKabul/malzemeCinsMiktarKabul` (must be before `POST /v1/export/{model}` wildcard `routes/api.php:41-43`), reads `getFormData()` DB as PRIMARY for `imalatci` (request only overrides if non-empty) `trim((string)...)` fix for `ConvertEmptyStringsToNull`, renders `exports/malzeme-kabul.blade.php` / `malzeme-cins-miktar-kabul.blade.php` (title `SEVK EDİLECEK MALZEMENİN CİNSİ VE MİKTARI` 4-row vertical label|":"|value + items flattened by serials `serial_no` column + signature grid `imalatci` inside `80px` row not floating).
- Loader `printingKabul/printingCins` `Swal PDF oluşturuluyor... ki-loading spin #059669/#7c3aed` covering suffix query + fetch + blob, buttons `:disabled opacity 0.72 Oluşturuluyor...`, `Swal.close()` on done, downloads `malzeme-kabul-{order_no}.pdf` / `malzeme-cins-miktar-{order_no}.pdf` via dompdf A4. Expected flow: print → sign pen → scan → upload to step 5.

### 6.5 Step 1 Continued — Item Serials & Split (Core Brain `OrderItemTable.vue`)
**General:**
- Props `readonly:Boolean` `isLocked` passed `OForm :readonly=isLocked` (`files_rejected` strict but child overrides for rejected test doc replace). `orderDate` prop `orderEntities.created_at` `d/m/Y or Y-m-d → YYYY-MM-01` `parsedOrderDate` auto-fills KG/M date.
- `itemsMap` computed O(1), `initFlatpickr` only `.oic-fp-month:not(.fp-initialized)`, `serials` watcher debounce 150ms `notifySelect`, `highlightQnid` tracks last.

**ST <300:**
- Checkbox `Seri Numarası Girilecek?` toggle `toggleCard` → auto N rows `1 ST` each max 300, each `Seri No + Malzeme Üretim Tarihi (flatpickr monthSelect) + 1 ST`.
- `ST >=300` checkbox hidden, no serial entry.

**KG / M (required):**
- Table `Parti No (default '-') + Malzeme Üretim Tarihi (auto orderDate, not required clearable) + Miktar + + -` min 1 row, sum must = split amount (partial) or full qty (at_once). `rebuildSerialsForItem`, `addSerialRow`, `removeSerialRow` (min 1). Scrolled `max-height 320px` per item, `serialCollapsed` toggle `Genişlet/Daralt` (hidden for tedarik via `oic-hide-header` CSS, `v-show` body), color `at_once #f8fafc` vs `partial #fffbeb→#fef3c7 oic-serial-partial`, `Daralt margin-left:auto` far right, summary badge `hash 2 seri toplam 500 KG`.

**Sync `syncSplitFromSerials` 2026-08-31:**
- Debounced `serials` watcher → sum serial qty → `splitAmounts[item.id] = sum` if `>0`, `_syncingSplit` flag prevents `splitAmounts ↔ serials` infinite loop. Called from `addSerialRow/removeSerialRow/serial qty change/Excel`.

**Excel upload 2026-08-31:**
- 4× `Excel'den Yükle + Şablon` buttons (at_once ST/KG/M + partial ST/KG/M) `import * as XLSX from xlsx`, `excelFileInputs` ref map, `triggerExcelUpload(item)` captures `_excelUploadSplitAmt`, `downloadExcelTemplate()` generates `SRLCODE/SRLDATE/QUANTITY`, `parseSerialExcel` replaces serials, ST forces qty 1 checkbox auto, KG/M from Excel, `_excelUploadTime 1000ms` prevents `rebuildSerials` overwrite, `serialCollapsed=false` keeps expanded, ST mismatch Swal confirm `splitAmounts = parsed.length`.

**Existing serial view:**
- `fetchSerialsForItems()` on load for `has_serials=1` items, collapsible read-only purple gradient `serialViewCollapsed` default collapsed, disabled inputs `formatSerialDate YYYY-MM-01 → MM.YYYY`.

### 6.6 Step 5 — File Uploads
**Order-level (Step 5 cards):**
- `Malzeme Kabul` `group transfer_kabul transfer_kabul_file single hideAdd:true` + `Malzeme Cins-Miktar` `transfer_cins` single. Tedarik data `tedarikKabulFile/CinsFile/Ref` + `getTedarikRowId/onTedarikKabulSelect/onTedarikCinsSelect/handleTedarikTempUpload` `OForm.vue:140` + temp `POST /v1/temp-upload` `handleTedarikTempUpload` handles `rsp.data vs rsp` top-level, `fkey field**group**rowId*-*fid` now `rowId before *-* so explode 2 clean`, backend `leftPart + !is_numeric→0 + addFileToDb(...,existingFileId)` not garbage `OForm.vue + DSP:362`.
- Locked display `OForm EXISTING FILES LOCKED 2026-09-02 late:` `parseTedarikExistingFiles()` + `getTedarikDisplayName()` detects `salt:iv:ct` encrypted → `Malzeme Kabul Formu` friendly, `tedarikExistingKabul/Cins` grey chip doc icon + name + status pill `Beklemede/Onaylandı/Reddedildi` + eye preview `previewTedarikFile()` iframe, rejected red chip + `Yeni dosya seç`, `isFilesLocked` disables + note pills unwrapped via `getTedarikNote/hasTedarikNote JSON.parse {"note":"dfadfa"} → dfadfa` `OForm.vue:963`.
- Accepted visible `2026-09-03`: accepted shows locked green `f0fdf4 86efac Onaylandı + eye + Onaylayan who + Notu Gör` even when other rejected, not editable.

**Per-item files `OrderItemTable.vue` 2026-09-01 mechanics:**
- Collapsible `Dosyalar` per item.
- `Test Dökümanı` `item_test_docs item_test_file` single `hideAdd:true` rejectable/acceptable, status badge, single slot `existingTestFiles` first only, upload hidden when exists, remove via `_removedExistingFiles {id:connId,key:entity_tag,fileId}` `fetchItemFiles()` parses BOTH `conn.files` + `conn.entities` JSON strings `item_test_file/item_images_file` deduped by id, stores `entity_tag+connId`, `triggerTestUpload/onTestFileSelected/removeTestFile` guards `if(readonly && !isTestRejected) return` `isTestRejected(id)` allows replace even when locked `2026-09-02`, chip `oic-previewable` clickable + eye `oic-item-file-preview purple`, `previewExistingFile` iframe uses `entity_tag` to label, `previewLocalImage` pdf→iframe xls→window.open image→Swal `URL.createObjectURL` + lifecycle `beforeUnmount revoke`.
- `Ürün Görselleri` `item_images item_images_file` multi `**img-{uploadId}` unique tag (`3× **`) so images append not replace (legacy broken `same tag → status0 only last active` fixed `OForm.vue saveItemFiles UNIQUE tag`), `oic-image-grid 84×84 12px cover src=/order-file/{qnid} + URL.createObjectURL + onThumbError PDF fallback`, `fetchItemFiles` generic `removedData` stored, `removeExistingImageFile` generic push `connId+key`, `OForm saveItemFiles generic connId=rf.connId||rf.id key=rf.key`, `left.full` preview-only when readonly, `oic-split 50/50 eye 22px rgba(15,23,42,0.48)→#4f46e5 + cross-circle →#e11d48 blur3px`.
- Gallery `gallery:{visible,items,index} getGalleryItems merges existing+uploaded openGallery globalIdx teleport oic-gallery-overlay rgba(15,23,42,0.88) blur6 prev/next 48px counter stage 70vh thumb 64px active #818cf8 Esc←→`.
- Thumb lock `readonly` guards `Görsel Ekle/Sil` `v-if=!readonly`, rejected test `Yeni Test Dökümanı Yükle` always visible.
- Data stored `itemFiles {itemId: [File+previewUrl]}` + `itemTestFiles` + `_removedExistingFiles`, emitted `item-files`, `OForm @serials` listener.

### 6.7 Step 6 — Gönder (Submit)
- Visible `v-if=canSend || orderStatus===doc_trans_order_files_rejected` `OForm.vue:151`.
- Button `Gönder` `isSubmitting` → disabled `opacity0.72` `ki-loading spin Gönderiliyor + Kaydediliyor` below `OForm Gönder loading 2026-09-03` true on entry false on all exits (validation fail/saveItemFiles fail/catch/after PUT).
- **Validation before submit:** `imalatci` required + `checkForm('.form-item')` visible required + serials valid `allValid itemsMap rebuildSerials` + split amounts `>0 && <=quantity` + KG/M sum checks, else `plib.toast` Swal.
- **Flow `submitForm:966`:**
  1. `saveItemFiles()` **BEFORE** order PUT — for each `itemId` with files: `PUT /v1/document/{itemQnid}` with `dynamicF['op-doc-order-item-form**connId'].entities` empty + `files {item_test_file**item_images**connId: file OR item_images_file**item_images**connId**img-id: file}` + `removedData` if test/images removed (via `itemRemovedFiles` tracking). Returns bool aborts on fail, clears `itemFiles` after success so retry never re-sends.
  2. Build `formData` hidden scaffold `formDataStore` + inject `dynamicF['op-doc-order-form**rowId'].entities['order_desc','imalatci_firma_adi']` skipping when `isLocked`, + `dynamicFile**transfer_kabul|transfer_cins` `fileReference` via `handleTedarikTempUpload`.
  3. `envelope FormData append data JSON.stringify(formData)` + files `dynamicFile*` `file || JSON.stringify(reference)` `pickle.js:824`.
  4. `PUT /v1/document/:qnid` `DocumentController:index` `registerContent(qnid, data, files)` DB transaction.

---

## 7. Backend Submit — `processOrderTransfer` Inside Transaction `DocumentServiceProvider.php:754`

Called from `DocumentController.php` after `registerContent` with `transfer_mode (at_once/partial) + selected_items [{qnid, amount, serials}] + item_serials [{itemId:serials}]`.

**Steps:**
1. **Guard:** `hasActivePartitions(baseOrderNo, excludeId)` `SELECT count where order_no ILIKE baseNo-'%' AND status=1 AND type op-doc-order` — if `hasPartitions && mode==at_once` → error `Bu sipariş daha önce parçalı gönderildi...` blocks. Clones `status=0` → guard releases `isAtOnceDisabled` false again.
2. **`saveTransferModeEntity(order, mode)`** stores `transfer_mode` EAV `transfer_mode**transfer_group**rowId` for frontend `storedTransferMode` display.
3. **AT_ONCE branch:**
   - `Documents::where(qnid).update status?` no, keep.
   - `setStatus qnid doc_trans_order_transfer_sent` via `setStatus:972` → `UserLog log-document-status-update {actor,document,from,to,note}` + `Transactions op0 type statusKey target doc.id log_id note 300 truncated`.
   - For each `item_serials[itemId]` → `createSerialEntries(item.id, serials)` loops → `Documents type op-doc-order-serial parent_id=item.id` + `sys_con_ops op-doc-order-serial-form` + `sys_con_entities serial_no/production_date/quantity/unit` + `setHasSerialsFlag(item.id) has_serials=1`.
4. **PARTIAL branch:**
   - Clone order: `Documents create type op-doc-order title=order_no+'-'+(maxSuffix+1)` `transfer_no=EBELN-X` `parent_id=order.id` (?) `person_id=session`, `saveTransferModeEntity(clone, partial)` + `saveTransferModeEntity(order, partial)`.
   - For each `selectedItems` → `decrementItemQuantity(originalQnid, splitAmount)` `EAV quantity = old - split` + `storeOriginalQuantity` entity `original_quantity`, parses `float vs int ST`, + `duplicateOrderItem(itemQnid, clone.id, splitAmount)` copies all EAV **except** `item_test_file/item_images_file` tags (junk skipped `2026-09-01`), sets `quantity=splitAmount` + `split_from_qnid + split_amount + original_quantity`, `type op-doc-order-item` `parent_id=clone.id`.
   - `createSerialEntries(cloneItem.id, serials)` per split.
   - `moveOrderFilesToDocument(originalItem→cloneItem)` per `selectedItems` where `itemFiles` were finalized BEFORE PUT — relinks `sys_con_entities conn_id old→new + entity_tag str_replace(oldConnId,newConnId)` otherwise replacement detection fails (`registerContent` lookup `conn_id+tag`).
   - Order-level files `transfer_kabul/cins` finalize inside order's own `registerContent` before this step so they move correctly (were `relation=temp` before).
   - Clone `setStatus doc_trans_order_transfer_sent` + `Transactions`.
5. **Commit** → `getFormData(qnid)` for `after` → `UserLog log-tender-update {before:getFormData, after:getFormData, actor, document, note:file_note}` `registerContent:407` forwards `file_note|note` to `finalizeTempFile/addFileToDb` `DSP:362,373`.
6. **File versioning inside `registerContent`:** `isMultiFile(fileName) substr_count ** >=3` dynamic `2026-09-04 late+2` `DSP:22` — `2× ** = single` uses `like typeTag**%` + `reused tag` version (keeps `new-→83` fix `4ac9262`) `finalizeTempFile/addFileToDb replacement → old status0 new replaced_id=old`, `3× ** = multi` exact `entity_tag=fileName existingFileId=0 entityTagToUse=fileName` → `status1 append` all active (fixes `item_images_file 3 images overwrote` `073b8de` vs `4ac9262`).
7. **Sync not here** — `syncOrderStatusFromFiles` fires on file status change.

**After save toast `OForm.vue still-rejected 2026-09-02`:**
- Parse `detail.formFormat` + `itemTable.existingTestFiles` where `doc_file_rejected && no replacement` → if `lastOp==files_rejected` Swal info `Kaydedildi — hala reddedilen dosyalar var: …` else success toast + `router.push isTedarik?TedarikOrderList:OrderList`.

---

## 8. Admin Review — Documents `/coalpanel/documents` & `/tedarikpanel/documents`

**Shared `DList.vue:320` `isTedarik` flat list grouping REMOVED (pagination split bug) — `groupBy group_key` not needed now flat, `groupFormatter` deleted, `enhanceGroupHeader` removed.**
- `POST /v1/table/document_files` `Document_files::tableList:150` — selectable `file, type_title, relation, relation_qnid, entity_tag, last_status json (op_key,title,name,note,created_at ORDER BY t.id DESC), old_versions json_agg, group_key COALESCE same-conn order_no else parent order_no`, `product_name title from parent item` for test docs `bg-info`. `status1 only`, `grp_code=SYS_CODE`, `parent active`, `se.table_tag=document_files` + `se.entity_value ~ ^[0-9]+$` filter prevents `340.60 invalid integer` crash `Document_files.php old_versions fix 2026-09-02`, exclude `item_images_file`.
- **LIFNR scope `Document_files.php:150 2026-09-03`:** reseller `clientQnidList → lifnr resolve SELECT lifnr FROM client docs → filter d.qnid IN OR spec_code IN OR parent.spec_code IN OR lifnr IN` fails closed admin open verified `tinker 0000300186→3`.
- **Columns:** `Belge Başlık 700 #0f172a 13px + file icon 36×36 circle (is-test #fef3ff/ki-flask, is-cins #fff7ed/ki-chart-simple, is-kabul #ecfdf5/ki-clipboard)` for tedarik `180px` vs admin `18%`, `Sipariş / İlişki 27% merged single line gap6 two pills ordercode #f8fafc + relation #eef2ff if oc!=ic` `DList.vue:362`, `Güncel Durum` tedarik solid `Kontrol #FF5A1F, Onaylandı #22c55e, Reddedildi #ef4444, Yenilendi #facc15 5px 10px 600 cursor default` vs admin pastel, `Detaylar` tedarik `Aksiyonlar #8e8e93 Swal Önizle/Yeniden Talep Et/Detay/İlişkiye Git + Detaylar #0e8ea4 → TedarikDForm` `DList.vue:390`.
- Row = `tr bg #fff radius14 shadow 0 2px 8px hover translateY(-2px)`, `td borderless transparent` `DList.vue:1402`.
- Height `height:auto paginationType:number` both, wrapper `height:auto min-height0` `.tedarik-main .tedarik-docs-page .pickletable`, `TedarikPanel setupTypewriterScroll docs early-return frame/main height:auto body ''` `TedarikPanel.vue:100` + `DList mounted` forces `coalpanel #kt_content flex0 auto`.
- **Filters `DList.vue:75 2026-09-04 late normal no Detailed`:** `Filtreler teleport fixed z9999 280px 8 radios` `Seri No→prompt key:seri_no like serial entities, Tarihe Göre Sırala→created_at desc, Tarih Aralığı→flatpickr Y-m-d range→tarih_araligi pipe, Sipariş No Sırala→group_key asc, Beklemede doc_file_waiting, Onaylanan doc_file_accepted, Reddedilen doc_file_rejected, Hepsini Göster→clear` divider `Şirkete Göre Arama FF4713 → client-modal 720px modalClients localData 8 hardFallback Tümünü Seç Filtrele/Temizle` no Detailed 3×3, `Document_files.php:210` `seri_no/siparis_kodu/tarih_araligi/sirket/file_status` cases, `OList.vue:1919 Swal text-color fix #0f172a` `::placeholder #94a3b8`, date range ` - → |` fix `DList.vue:242` + backend `sep |/ - / to / —` robust `Document_files.php:223`.
- `Yeniden Talep Et 2026-09-03:` `handleRetake(row)→POST /v1/trans/set-file-status doc_file_rejected + note textarea + toast + table.updateRow` card footer `fff7ed/fed7aa ki-arrows-loop full-text pill is-retake`.

**File Detail `GET /api/v1/file-detail/:qnid` `DocumentController.php:580` + `DForm.vue:1`:**
- Resolves `file→d→order (order vs item parent)` LIFNR scope, `getFormData(orderQnid)` header `order_no/buying_no/ctitle/spec/created_at`, items `parent_id=order.id → prod_code/title/unit/quantity`, files `(d.id=? OR parent_id=?) se %item_images excluded i.status 0/1 includes old versions + last_status ORDER BY t.id DESC`. Supplier row gradient `fff→fff7ed fed7aa clickable goOrder() panel-aware`.
- Layout header `order_no big` + supplier, warning + items `1fr 110 90` hover `fffbeb`, files `1fr 130 300 90 TİP/TARİH/DURUM centered` `file-card 14px hover cbd5e1 active #FF5A1F fff→fff7ed active-tri`, `status-pill 999px gradient is-success #00a651 is-fail #e30613`, `incele #d97706 90px solo`, `decide-row flex 50/50 pills Onayla/Reddet → Kaydet 100% → Red box 100% same 300px box-sizing` `DForm.vue:385`, `isOldVersion file_status 0 → old-locked f8fafc Eski versiyon — değiştirilemez + hint`, `isDecidable waiting/refreshed||!k && canDecide && !old → after accepted/rejected locked f0fdf4 İşlem tamamlandı` `DForm.vue:88`.

---

## 9. File Status & Auto Order Status

**Single:** `POST /v1/trans/set-file-status` `DSP:1178 per-07-02` → find `sys_con_entities table_tag=document_files entity_value=fileId` → `UserLog doc_file_* {file_id, file:{id,qnid,status,field,group_key,entity_tag,relation_id,order_qnid,order_no}, actor, from,to,desc,note}` `doc_file_* itself as type_id not log-file-status-trans` + `Transactions op1 note/description 300 actor short` → `syncOrderStatusFromFiles` + `refreshAllUserPermissions` + `sendClientFileStatus` mail.

**Bulk Kalite:** `POST /v1/trans/set-status doc_trans_order_approved` from `OList.vue Kalite Onayı` checks `canKalite per-05-03 ONLY` not ended → `acceptAllOrderFiles:1069` loops order+item files where `status1` not already accepted → `doc_file_accepted per file` enriched.

**Auto `syncOrderStatusFromFiles:1346` N+1→1 batch `AuditService.php:346`:**
- `SELECT DISTINCT ON (target_id) … WHERE target_id IN (fileIds) ORDER BY target_id, id DESC` → `lastMap`.
- `hasRejected = any lastOp == doc_file_rejected`.
- If `hasRejected && order last != files_rejected` → `applyOrderStatus:1398` `fromTitle via AuditService::optionTitle cached` + `rejectedNote unwraps transactions.note {"note":"real"} → Dosya reddedildi: real` → `log-order-update {actor,document,from,to,desc Sipariş Durumu Güncellendi,note rejectedNote}` + `Transactions op0`.
- If `!hasRejected && last == files_rejected` → back `transfer_sent` (or `ready`).

**Manual order status `POST /v1/trans/set-status` `DSP:972 setStatus`:** `doc_trans_order_created→transfer_sent`, `transfer_sent→approved/rejected`, `files_rejected→transfer_sent|approved|rejected`, terminal blocked. Also fires `UserLog log-document-status-update`.

**Other logs `logging-mechanics.md:4`:** `cancelOrder:2046 log-order-update Sipariş İptal Edildi`, `renameOrder:2110 old→new`, `removeContent:751 log-tender-update before/after`, file uploads `addFileToDb:805 / finalizeTempFile:554 log-file-added file+actor+note`.

**Frontend notes `DForm.vue:78 noteOf` unwraps `JSON.parse(n)?.note ?? n` with `null→''` fallback to show placeholder `Red nedeni yazın...`, `OForm getTedarikNote` same, `LList.vue orderLabelMap` fallback for old logs missing `from.title`.

---

## 10. Closure, Rename, Cancel, Remove

- **Kalite close `OList.vue Aksiyonlar`:** `Kalite Onayı Ver ve Kapat` teal → Swal → `POST /v1/trans/set-status doc_trans_order_approved + acceptAllOrderFiles` → all files accepted → order approved. Disabled grey `Zaten Kapalı` if `approved/rejected`.
- **Rename `POST /v1/orders/rename` `DSP:2110 DocumentController:580` per-05-05 ONLY suffix `^\d+ ≥1`:** frontend base pill locked + input X only builds `base-X`, backend `preg_replace` base guard duplicate `status1` check, updates `order_no+transfer_no` + UserLog.
- **Cancel whole / clone `POST /v1/orders/cancel` per-05-04 ONLY / `DELETE /v1/document/:qnid` `removeContent:751`:** `documents.status=0` + `doc_trans_order_rejected` + `UserLog`. For clones `parent_id!=0 + EBELN-X` → `restoreQuantitiesForClone:1381` loops clone items `split_from_qnid+split_amount` → `incrementItemQuantity(originalQnid, split_amount)` ST int / KG/M float, deactivates clone serials `status0` + clone items `status0`. Single clone item delete `restoreQuantityForSingleCloneItem`. `OList table.deleteRow(qnid) script.js:680` not reload. `isCloneRow` label `Parçayı Sil` vs `İptal Et`.
- **Remove file link:** via `OForm saveItemFiles removedData {id:connId,key:entity_tag}` or `OrderItemTable _removedExistingFiles` → `registerContent removedData` deactivates `document_files status0` + delete entity where active.

---

## 11. Logging & Side-Panel `logging-mechanics.md:5` `AuditService.php:1`

- `actorSnapshot()` → `users JOIN persons JOIN sys_options` cached 300s, `order(docId)` `MAX(CASE tag)` aggregate cached, `file(fileId)` chain cached, `optionTitle` `rememberForever`, `diff` flattened.
- 8 indexes `2026_09_04_000005_add_audit_indexes`: `idx_sce_conn_tag (conn_id,entity_tag)`, `idx_sce_table_value (table_tag,entity_value)`, `idx_sce_entity_tag_like text_pattern_ops`, `idx_sco_main_conn (main_id,type_id) WHERE conn_id=0`, `idx_df_relation_status (relation_id,status) WHERE relation='documents'`, `idx_trans_target_op (target_id,op_id)`, `idx_sys_op_key UNIQUE`.
- `LList.vue:163 Sistem logları POST /v1/table/userlog UserLog::tableList:41` `columnClick` parses `actor/document/file/from/to/note` → `log-modal-grid flex 16px` left `jsonToDetails tree 60vh expand/collapse/copy swal 1500px` + right `side-card 340px` `İşlemi Yapan avatar initials 0b5fff→7c3aed + name/email + pills role/type_key/sys_code/ip + user_id/person_qnid`, `Sipariş order_no big mono + transfer/buying/spec/ctitle/qnid`, `Dosya field(group_key)/qnid/order_no/tag`, `Durum Geçişi from #fff7ed → to #eff6ff pills op_key mono`, `Not fffbeb`.
- Legacy no-actor logs side hidden.

---

## 12. How to Test Full Process From Both Panels (For Next Session)

**Prereq:**
```bash
docker start tedarikNewApp || docker run --name tedarikNewApp -e POSTGRES_USER=tedarikNewApp -e POSTGRES_DB=tedarikNewApp -e POSTGRES_PASSWORD=tedarikNewApp -p 5431:5432 -d postgres:latest
cd panel && php artisan migrate:status # 23 done
npm run build # after any Vue change → app-*.js
php artisan serve --host=127.0.0.1 --port=8000
# logins: kadir@kontent.com.tr / Kadir412. → 111111 → /coalpanel or /tedarik
# fresh: cp /tmp/sap_payload.json /tmp/sap_fresh_payload.json && php artisan orders:sync --json=/tmp/sap_fresh_payload.json --fresh && rm -rf storage/app/public/documents/* + wipe serials/files manual if needed
```

**Test A — Supplier At Once (Both Panels):**
1. Login as supplier (reseller lifnr 0000300184) at `http://127.0.0.1:8000/tedarik` → `/tedarikpanel/orders` should show only his orders (e.g. 3510004200).
2. Click order `3510004200` → detail header correct, drum `Beklemede`.
3. Choose `Tek Parça`, fill `İmalatçı Firma` + `Açıklama`.
4. For KG item add serial row `Parti -, Tarih auto, Miktar 100`, for ST tick checkbox.
5. Click `Malzeme Kabul Yazdır` → PDF downloads `malzeme-kabul-3510004200.pdf` with imalatci inside signature 80px. Same for Cins.
6. Upload `transfer_kabul` + `transfer_cins` files (pdf 1MB) + per-item `Test Dökümanı` + 2 `Ürün Görselleri` thumbs appear 84×84.
7. `Gönder` → success toast → list status `Dosyalar Kontrol Ediliyor`. Check admin `/coalpanel/orders` same status.
8. Login as admin at `http://127.0.0.1:8000/` → `/coalpanel/documents` should show files `group_key=3510004200` 4 rows (`kabul/cins + 2 test`), `GET /api/v1/file-detail/<file qnid>` shows files `1fr 130 300 90`.
9. In `DForm` click `İncele` eye → preview iframe, then `Onayla` → green pill, `Transactions` check `doc_file_accepted`. Check supplier list auto refresh via `sync` already?

**Test B — Supplier Partial + Clone + Restore:**
1. Supplier opens `3510004300` (2 items) → hasPartitions false → both radios enabled.
2. Select item1 `Böl 500 ST`, item2 `Böl 100 KG`, add KG serial rows sum 100 (auto sync Böl).
3. Upload 1 test file per selected item (unique tag).
4. `Gönder` → clone `3510004300-1` created, original qty decremented `~~1000~~→500`, clone shows `500`. Check `Documents::where transfer_no 4300-1`.
5. In `OList` admin sees `3510004300-1 'den ayrıldı` link → click goes to original.
6. In `/coalpanel/documents` files now `group_key=3510004300-1` for clone item tests.
7. Try `Tek Parça` on original now → disabled grey banner.
8. Delete clone via `Parçayı Sil` on `OForm` locked card or `OList` → original qty restored `500→1000`, clone `status0`, `hasActivePartitions false → Tek Parça unlocks`.

**Test C — Rejection Loop:**
1. Admin in `DForm` for a file → `Reddet` with note `kalite uygun değil` → file `doc_file_rejected` + note `{"note":"kalite uygun değil"}` + order `files_rejected` via sync with `rejectedNote Dosya reddedildi: kalite uygun değil`.
2. Supplier `/tedarikpanel/documents` sees `Reddedildi #ef4444` + file card red, detail header still `Gönder` visible via `canPrintKabul = files_rejected`, upload area shows `Yeni dosya seç` red chip.
3. Supplier re-uploads same slot `item_test_file` → `replaced_id` chain new active, old status0. Save → `doc_file_refreshed` + order back `transfer_sent`.
4. Check `LList` at `POST /v1/table/userlog` → side-panel `340px` shows `İşlemi Yapan` avatar + `Sipariş order_no big` + `Dosya field/group` + `from Dosyalar Kontrol Ediliyor → Reddedilen Dosyalar Mevcut` + `Not kalite uygun değil` yellow box. Expand tree `60vh`.

**Test D — Cancel/Rename/Export:**
1. Clone `3510001793-1` → `Sipariş Numarasını Düzenle` suffix-only `2` → `3510001793-2` Check duplicate guard.
2. `Kalite Onayı Ver ve Kapat` on `3510004400-1` → all files `doc_file_accepted` + order `approved` → button becomes disabled grey.
3. Export `POST /v1/export/document_files` via `DList Filtrele` or `OList Filtrele` → xlsx downloads.

**Test E — Both Panels Parity:**
- Every `OList/OForm/DList/DForm` check `isTedarik` panel-aware routes: clone/back/list stays in same panel (`TedarikOrderForm` vs `OrderForm`).
- Permissions: supplier without `per-05-03` should NOT see `Kalite` button; without `per-07-01` should NOT access `/tedarikpanel/documents` (router `beforeEach` blocks).
- Typewriter scroll: tedarik docs `height:auto body ''` vs admin `75vh`, no 460px blank `TedarikPanel.vue:100`.

**Expected counts after fresh + manual Test A+B:** 8 base + 1 clone = 9 orders, 21 + 2 clone items = 23 items, serials created, 4+ files, `user_logs` + `transactions` enriched with `actor/document/file` frozen.

---

## 13. Gotchas For Testers

- `target_type` hidden field required for `grp_code` routing — always include even hidden.
- `her_ikisi=1` bypasses `grp_code` filter (both tenants).
- `PUT multipart` broken on Apache — `ParsePutMultipart` global + `parsePut()` fallback `DocumentController:141`.
- `entity_tag` `**` count decides `single vs multi` — `transfer_kabul` 2× `**` version via prefix, `item_images` 3× `**` append; hardcoded `MULTI_FILE_TYPES` removed.
- `getFormData json_agg ORDER BY t.id` ensures `parsedStatus[last]` is current.
- `transactions.varchar(300)` truncates — rich in `user_logs` TEXT only.
- `removeFile` never deletes physical disk on versioning — only `documents:purge` deletes.
- `DEV_ADMIN=111111` backdoor, `CSRF off`, `/order-file` IDOR — known.

---

## 14. File Map

| File | Role |
|------|------|
| `00-core-overview.md` | arch brain |
| `01-form-engine.md` | Form.vue |
| `02-eav-dictionary.md` | EAV + logs §6b |
| `03-app-factory-guide.md` | new doc recipe |
| `04-backend-frontend-patterns.md` | patterns |
| `05-order-system-state.md` | LIVE snapshot |
| `06-roadmap-next.md` | next steps |
| `07-sap-sync-mechanic.md` | sync command |
| `panel/documentation/*` | 10 mechanic bibles (this file is process) |
