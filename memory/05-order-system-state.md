# Order Management System — Current State (2026-08-26)

> **This is the living snapshot. If you are LLM in a future session, read this first after `00-core-overview.md`.**
> **Control panel conversion is DONE. Front panel PENDING (design awaited). SAP cron PENDING (grouping logic defined, not formalized).**
> **⚠️ KEY CORRECTION (Master): Transfers are NOT a separate doc type — they ARE `op-doc-order` cloned with a new number (`EBELN-X`) only when an order is partially split. `op-doc-transfer` type still exists in dict but is NOT used for the UI anymore.**

## 1. What Changed Since Coal

**Coal (KomurTedarik) → Order Management System** on same EAV skeleton. No migration for business fields — only dictionary + Form.vue + Vue pages.

| Layer | Coal | Order System Now |
|-------|------|------------------|
| **DB** | `b2x` on `5431` (postgres `postgres:latest 61d0571c...`) | **`tedarikNewApp`** on `5431` same image `61d0571c2f7b` docker `tedarikNewApp` Up, `127.0.0.1:5431 tedarikNewApp/tedarikNewApp` (`panel/.env:28`) — 23 migrations, 77+18 sys_options |
| **Doc types** `sys_options.group_key=op-doc` | `op-doc-request\|offer\|client\|flat` | **`op-doc-order` (Sipariş header), `op-doc-order-item` (Kalem, `parent_id=order.id`), `op-doc-client` (Cari)**. `op-doc-transfer` still seeded but **NOT used** (clones are `op-doc-order` with `transfer_no` entity `EBELN-X`). `OrderSystemSeeder.php` |
| **Forms** `op-doc-forms` | 5: `request/offer/client/user/flat` | **`op-doc-order-form`** (`order_no/buying_no/spec_code/sys_code/ctitle/created_at` readOnly + `order_desc` textarea rows 3 + `imalatci_firma_adi` + `Malzeme Kabul` `group_key=transfer_kabul` `transfer_kabul_file` + `Malzeme Cins-Miktar Kabul` `group_key=transfer_cins` `transfer_cins_file`), **`op-doc-order-item-form`** (`prod_code/title/quantity/unit` readOnly, NO deal/received price — removed, + `item_test_docs` + `item_images`), `op-doc-client-form` **+ `lifnr` (Cari Kodu)** field. (`panel/resources/js/components/coalparts/Form.vue:1048,1810`) |
| **Status** `op-trans-op-doc-order` | — | **`doc_trans_order_created → transfer_sent → approved/rejected`** (terminal) + **`doc_trans_order_files_rejected` (Reddedilen Dosyalar Mevcut, set automatically when any order/item file is rejected)** — titles renamed `doc_trans_order_transfer_sent → Dosyalar Kontrol Ediliyor #f97316`, `doc_trans_order_approved → Kalite Onayı Verildi #22c55e` (`psql update sys_options`). Guard in `setStatus` (`DocumentServiceProvider.php:745`): `created→transfer_sent`, `transfer_sent→approved/rejected`, `files_rejected→transfer_sent|approved|rejected`, terminal blocked (like offer editableStatuses). File status `doc_file_waiting/accepted/rejected` (`op-trans`) via `documentFileStatus` |
| **Birth status** | — | `registerContent` (`DocumentServiceProvider.php:101`) uses per-type birth map: `op-doc-order→doc_trans_order_created`, `op-doc-transfer→doc_trans_transfer_created`, `op-doc-order-item→doc_trans_created`. FIXED so newborn order shows `Sipariş Oluşturuldu` in `tableList` (`Documents.php:83` `group_key=op-trans-<type>`). |
| **File types** `op-file-types` | 5: `offer_otherdocs / iban / odasicil / vergi / imza` | **+ `op-transfer_kabul_file` (Malzeme Kabul), `op-transfer_cins_file` (Cins-Miktar), `op-item_test_file` (Ürün Test Dokümanı), `op-item_images_file` (Görsel)** |
| **Permissions** | 19 `per-00..08` | **NOT split — reuse `per-05-01/02` for orders, `per-07-01/02` Dökümanlar, `per-06-01/02` Cari**. `PermissionHelpers.php:31` map has `op-doc-order/order-item/transfer → per-05-01/02`. No `per-09`. |
| **Frontend** `router/index.js:24` | 17 routes | **`/coalpanel/orders` OrderList, `/orders/form/:id` OrderForm** (legacy `/request` + `/offer` kept hidden). Transfer routes kept but menu hidden. |
| **Sidebar** `Sidebar.vue:200` | Talep/Teklifler | **Siparişler only (Sipariş Listesi + Oluştur)** + `Dökümanlar` + `Firma` + `Yönetim`. Transferler hidden `v-if=false`. |
| **Build** | — | `app-DDU2gImt.js 889k` — `npm run build` after every Form.vue/router/Sidebar/OForm/OList/OrderItemTable edit. |

## 2. Data Model — CRITICAL (Master’s rules)

1. **Order ↔ Client link = `LIFNR` string** (no FK): `order.spec_code = client.lifnr`. Client form has **`lifnr` = "Cari Kodu"** field (`Form.vue:1051`), client list has **`Cari Kodu` column** (`CList.vue:195`) + mobile card shows it. Order list/detail shows `Cari Kodu`. Match: `SELECT client_qnid FROM documents WHERE lifnr entity = order.spec_code`.
   - `Cari Kodu` = `LIFNR` from SAP, **keep leading zeros** (`0000300181` string, `replace(/\D/g,'').slice(0,10)`).
2. **Order Items = own `op-doc-order-item` docs** linked `parent_id = order.id` (`DocumentServiceProvider.php:86` supports `parent_id`/`parent_qnid`). `Documents::tableList:355` now has `case 'parent_id'` filter.
3. **SAP sends ONLY order items** (flat rows, no headers). Our cron **groups by `EBELN` → creates main `op-doc-order` ourselves** (header fields from first row: `BUKRS→sys_code, LIFNR→spec_code, EBELN→order_no, SUBMI→buying_no, MCOD1→ctitle, BEDAT→created_at`), **then adds each row as `op-doc-order-item`** (`MATNR**EBELP→prod_code, TXZ01→title, MENGE→quantity, MEINS→unit`). Prices `NETPR/WEMNG` no longer stored in form/table but SAP may still send (keep out of UI).
4. **Partial split → clone** as new `op-doc-order` `transfer_no = EBELN-X` (`X` increment). Clone only exists if partially split, NOT auto for every order.

## 3. Current Live Test Data (`panel` `tedarikNewApp` 2026-08-26 17:10)

**Wiped ALL 30 order/item/transfer docs → created ONE fresh SAP order:**

* **Clients 3** `op-doc-client` + `lifnr`: `PANORAMA TEKSTİL 335b8840 0000300181`, `HASÇELİK KABLO d922debd 0000300182`, `HES HACILAR ELEKTRİK b0ef3f9c 0000300183` (backfilled `lifnr` via `psql INSERT sys_con_entities`)
* **Order 1** `3510001793` `qnid 65cd247f-2656-4c10-b267-e44ad11dff33` id 42 `op-doc-order` `spec_code 0000300181 → PANORAMA` `buying_no IH20205008` `sys_code 4000` `ctitle PANORAMA` `created_at 2020-04-17` status `doc_trans_order_created (Sipariş Oluşturuldu)` + 2 files (file 3 `transfer_kabul`, file 4 `transfer_cins`) both `doc_file_waiting`
* **Item 1** `qnid bd29a586-166f-4fad-aacb-eb061b1e246b` id 43 `op-doc-order-item` `parent_id 42` `prod_code 20.6.1.005**00120` `title Premium Kömür Tip A` `quantity 2400.000` `unit ST`
* Counts: `clients 3 | orders 1 | items 1 | transfers 0`
* **Verified flow (2026-08-26):** send at_once → `transfer_sent`; reject file → `files_rejected`; renew/re-send → `transfer_sent`; partial → clone `3510001793-1` + item duplicated + files moved to clone + original marked `partially_sent=["3510001793-1"]`; cancel order → terminal `rejected`. All cleaned up back to `created`.
* **Recreate:** `/tmp/fresh_one_sap.php` (wipes order/item, keeps clients, creates 1 order + 1 item from your SAP array). `Documents::tableList parent_id 42 → 1 item` verified.

## 4. How Data Flows Now (SAP grouping, per Master)

```
SAP sends flat items: [{BUKRS,LIFNR,EBELN,EBELP,MATNR,TXZ01,MENGE,MEINS,BEDAT,SUBMI,NETPR,WEMNG,MCOD1}, ...]
→ cron groups by EBELN:
  → create ONE Documents op-doc-order (order_no=EBELN, spec_code=LIFNR, sys_code=BUKRS, buying_no=SUBMI, ctitle=MCOD1, created_at=BEDAT) [birth doc_trans_order_created]
  → for each row: Documents op-doc-order-item parent_id=order.id (prod_code=MATNR**EBELP, title=TXZ01, quantity=MENGE, unit=MEINS)
→ admin/client opens /coalpanel/orders/form/:id → sees header + Sipariş Kalemleri table (OrderItemTable.vue) + Açıklama textarea rows 3 + İmalatçı + Malzeme Kabul/Cins-Miktar files
→ client picks transfer type (Tek Seferde at_once | Parçalı partial) in OForm transfer card; on SAVE (same /v1/document PUT endpoint) payload carries transfer_mode + selected_items
→ at_once → order status created→transfer_sent (Dosyalar Kontrol Ediliyor orange)
→ partial → backend clones op-doc-order transfer_no=EBELN-X (duplicates selected items under clone parent_id, moves order files to clone) → clone→transfer_sent; original stays (records partially_sent JSON + transfer_no entity)
→ admin rejects a file → order auto-flips to files_rejected (Reddedilen Dosyalar Mevcut red) via syncOrderStatusFromFiles; all accepted → back to transfer_sent
→ admin approves → transfer_sent→approved (Kalite Onayı Verildi green), rejected→terminal red; cancel whole order via /v1/orders/cancel (list İptal Et or detail)
→ files: transfer_kabul/transfer_cins on order + item_test/item_images on item → doc_file_waiting → Dökümanlar tab approve/reject (per-07-02)
→ rejected file renew: client re-uploads in order detail (files stay editable even when locked), re-saves with a transfer mode → files_rejected→transfer_sent
```

## 5. Files Changed (key)

| File | Change |
|------|--------|
| `panel/app/Providers/DocumentServiceProvider.php:86` | `parent_id`/`parent_qnid` support for order-item linking |
| `panel/app/Providers/DocumentServiceProvider.php:101` | **birth status map** per typeKey (fix phantom `doc_trans_created`) |
| `panel/app/Providers/DocumentServiceProvider.php:745` | **status guard** order: created→transfer_sent→approved/rejected terminal |
| `panel/app/Models/Documents.php:355` | **`case 'parent_id'`** filter in `tableList` |
| `panel/app/Helpers/PermissionHelpers.php:31` | `docPermCheck` map `op-doc-order/order-item/transfer → per-05-*` |
| `panel/app/Helpers/DocumentHelpers.php:540` | `finalizeTempFile` mkdir `documents` dir before rename (fix `No such file or directory => 240`) |
| `panel/resources/js/components/coalparts/Form.vue:1048` | client form **`lifnr` = "Cari Kodu"** (10-digit keep zeros) |
| `panel/resources/js/components/coalparts/Form.vue:1810` | order form `order_desc` textarea rows 3 + `transfer_kabul/transfer_cins` files; item form removed deal/received price |
| `panel/resources/js/components/coalparts/Form.vue:2651` | textarea case respects `fitem.rows` (order_desc→3) |
| `panel/resources/js/components/Order/OrderItemTable.vue` | NEW — order detail items table (resolve qnid→id, `parent_id` filter, Ürün Kodu/Adı/Miktar) |
| `panel/resources/js/pages/coalsystem/Order/OForm.vue` | imports `OrderItemTable`, removed "Kalemler parent_id..." description |
| `panel/resources/js/pages/coalsystem/Order/OList.vue:192,217` | `Detay` button (was Aksiyon), `initialFilter op-doc-order` shows ALL orders (never hide) |
| `panel/resources/js/pages/coalsystem/Client/CList.vue:195` | `Cari Kodu` column + mobile card |
| `panel/resources/js/components/coalparts/Sidebar.vue:229` | Transferler hidden `v-if=false` |
| `OrderSystemSeeder.php` | + `doc_trans_order_files_rejected` (Reddedilen Dosyalar Mevcut) in `op-trans-op-doc-order` |
| `DocumentServiceProvider.php:932` | `documentFileStatus` now calls `syncOrderStatusFromFiles` after each file status change |
| `DocumentServiceProvider.php:966` | **NEW `syncOrderStatusFromFiles`** — walks file→conn→doc→parent up to order; any rejected active file → order `files_rejected`, all accepted → `transfer_sent` |
| `DocumentServiceProvider.php:1034` | **NEW `applyOrderStatus`** — writes order status transaction (op-trans-op-doc-order) w/o touching documents.status |
| `DocumentServiceProvider.php` | **NEW `processOrderTransfer($orderQnid,$mode,$selectedItems)`** — at_once sets order transfer_sent; partial clones EBELN-X + `moveOrderFilesToDocument` + `duplicateOrderItem` + `recordPartiallySent`, sets clone transfer_sent |
| `DocumentServiceProvider.php` | **NEW `cancelOrder($id)`** — soft status=0 + terminal rejected transaction for order/transfer |
| `Sys_con_ops.php` | added `type()` belongsTo relation (for whereHas lookup) |
| `DocumentController.php` PUT | order save reads `transfer_mode`/`selected_items` from payload → calls `processOrderTransfer` |
| `DocumentController.php` | **NEW `cancelOrder`** controller + route `POST /v1/orders/cancel` |
| `Form.vue` | + `readonlyFields` prop (forces readOnly/disabled on named fields for order locking) |
| `OrderItemTable.vue` | + `selectable` prop + checkbox column + `@select` emit (partial item selection) + **FIXED: added `mounted()` calling `resolveAndBuild()` (was never called, table never rendered)** |
| `OForm.vue` | **Items table ALWAYS visible** (removed `!(canSend && transferMode==='partial')` guard); transfer card: at_once/partial radio + item checkboxes on partial; `canSend` gates (created/files_rejected); `readonlyFields` lock on sent; cancel button |
| `OList.vue` | + `İptal Et` button per row calling `/v1/orders/cancel`; `files_rejected` → red pill |

## 6. What Still PENDING / Coal

- **SAP cron** NOT formalized → `panel/app/Console/Commands/SyncOrdersCommand.php` + `POST /api/v1/orders/sync-sap` (grouping logic §2.3 defined). Test seed scripts `/tmp/fresh_one_sap.php`, `/tmp/seed_realistic_fixed.php`, `/tmp/fix_orders_are_transfers2.php` are prototype only.
- **Front panel** design PENDING (`memory/idea.md:31`) — **backend transfer flow already BUILT** (order-detail SAVE → `processOrderTransfer` at_once/partial, files_rejected, cancel). What remains is the client-facing **skin**: route/layout (`/front/orders`...) that renders the order detail + at_once/partial radio + item checkboxes + desc/imalatci/2 files and calls the same `PUT /v1/document/{id}`.
- **Dashboard** still coal `ReportServiceProvider.php:479` — needs order metrics.
- **Branding** done `Tedarik Yönetim Sistemi`; SVGs still `CATES.svg/YATAGAN.svg` — optional neutral.
- **Legacy coal** `RList/RForm/OList/OForm/RSummary/OfferSummary` kept hidden, remove later.
- **`op-doc-transfer` type** still in dict — could delete via seeder if Master wants clean (not used).

## 7. Credentials & Infra

- **Docker:** `tedarikNewApp` postgres `61d0571c2f7b` on `127.0.0.1:5431`, `tedarikNewApp/tedarikNewApp`, container `fc1d76f...` Up. Do NOT `migrate:fresh`. `B2X` old exited, don't start both.
- **Panel .env:** `DB_DATABASE=tedarikNewApp` `:5431` `tedarikNewApp/tedarikNewApp`, `APP_NAME="Tedarik Yönetim Sistemi"`, `DEV_ADMIN=kadir@kontent.com.tr`, `SYS_CODE` host `yatagantermik?YATAGAN:CATES`.
- **Artisan:** `php artisan serve --host=127.0.0.1 --port=8000` PID 84235 `/tmp/serve.log`, `GET / →200` `Tedarik Yönetim Sistemi`. `npm run build` → `app-DDU2gImt.js 889k`.
- **Login:** `http://127.0.0.1:8000/` → `kadir@kontent.com.tr / Kadir412.` → 2FA `111111` (DEV_ADMIN). Sidebar `Siparişler` only.
- **Seeds:** `migrate` 23 + `SysRoleTemplateSeeder` (5 roles 19 perms) + `SysSeeder` (77) + `UserSeeder` (9 admins) + `OrderSystemSeeder` (18 dict rows: op-doc-order/order-item/transfer + forms + op-trans-order/transfer statuses + file types + logs). Test data = 1 order/1 item/3 clients (see §3).

## 8. How To Resume

```bash
docker start tedarikNewApp || docker run --name tedarikNewApp -e POSTGRES_USER=tedarikNewApp -e POSTGRES_DB=tedarikNewApp -e POSTGRES_PASSWORD=tedarikNewApp -p 5431:5432 -d postgres:latest
cd panel && php artisan migrate:status # 23 done
docker exec tedarikNewApp psql -U tedarikNewApp -d tedarikNewApp -c "select count(*) from documents where type_id=(select id from sys_options where op_key='op-doc-order')" # 1
cd panel && npm run build # after Form.vue/router/Sidebar/OForm/OList/OrderItemTable edit → app-DDU2gImt.js
php artisan serve --host=127.0.0.1 --port=8000 # http://127.0.0.1:8000/ → Tedarik Yönetim Sistemi
# login kadir / Kadir412. / 111111 → /coalpanel/orders → 1 row 3510001793 Sipariş Oluşturuldu
# detail → Sipariş Kalemleri 20.6.1.005**00120 + Açıklama textarea + Malzeme Kabul/Cins files
# fresh data: php /tmp/fresh_one_sap.php
```
