# Order Management System — Current State (2026-08-28)

> **This is the living snapshot. If you are LLM in a future session, read this first after `00-core-overview.md`.**
> **Control panel conversion is DONE. Front panel PENDING (design awaited). SAP cron PENDING.**
> **Transfers are `op-doc-order` clones (`EBELN-X`). `op-doc-transfer` type NOT used.**

## 1. What Changed Since Coal

| Layer | Order System Now |
|-------|------------------|
| **DB** | `tedarikNewApp` on `5431`, 23 migrations, 77+18 sys_options |
| **Doc types** | `op-doc-order` (header), `op-doc-order-item` (Kalem, `parent_id=order.id`), `op-doc-client` (Cari) |
| **Forms** | `op-doc-order-form` (SAP fields readOnly + desc textarea + imalatci + **single-file** `hideAdd:true` kabul/cins), `op-doc-order-item-form` (prod_code/title/quantity/unit readOnly), `op-doc-client-form` + `lifnr` |
| **Status** | `created → transfer_sent → ready_for_shipment → approved/rejected` + `files_rejected` |
| **Lock** | `ready_for_shipment` = FULL lock (desc+imalatci+files). `files_rejected` keeps files editable. `OForm.vue:63` `isFilesLocked`. `Form.vue:2385` compound-name lock. |
| **File replacement** | **FIXED:** Both `addFileToDb` and `finalizeTempFile` create version chains. See `panel/docs/file-replacement-fix.md`. |

## 2. Data Model

1. **Order ↔ Client** = `LIFNR` string: `order.spec_code = client.lifnr`. Keep leading zeros.
2. **Order Items** = `parent_id = order.id`. `Documents::tableList` has `case 'parent_id'`.
3. **SAP groups by EBELN** → creates order + items (MATNR**EBELP, TXZ01, MENGE, MEINS).
4. **Partial split** → clone `op-doc-order` `transfer_no = EBELN-X`.

## 3. Current Test Data (2026-08-28)

| # | EBELN | id | Müşteri | LIFNR | Items | Tarih |
|---|-------|----|---------|-------|-------|-------|
| 1 | 3510001793 | 114 | PANORAMA TEKSTİL | 0000300181 | 2 | 17/04/2020 |
| 2 | 3510002100 | 117 | HASÇELİK KABLO | 0000300182 | 3 | 01/06/2020 |
| 3 | 3510003500 | 121 | HES HACILAR ELEKTRİK | 0000300183 | 4 | 15/09/2020 |

All `doc_trans_order_created`. 3 clients exist.

## 4. Flow

```
SAP flat rows → cron groups by EBELN → order + items
→ OrderForm: ROW list (scrollable 10-15 rows) + transfer selection (at_once/partial)
→ SAVE → at_once: created→transfer_sent, partial: clone EBELN-X
→ admin rejects file → files_rejected; all accepted → ready_for_shipment (FULL LOCK)
→ admin approves → ready_for_shipment→approved; cancel via /v1/orders/cancel
→ renew: files_rejected → re-upload → transfer_sent → ready_for_shipment
```

## 5. Files Changed (key)

| File | Change |
|------|--------|
| `DocumentServiceProvider.php:86` | `parent_id`/`parent_qnid` support |
| `DocumentServiceProvider.php:101` | birth status map per typeKey |
| `DocumentServiceProvider.php:745` | status guard order |
| `DocumentServiceProvider.php:209-229` | **FIXED:** entity lookup before finalizeTempFile, passes `$existingFileId` |
| `DocumentHelpers.php:529` | **FIXED:** `finalizeTempFile` handles replacement (deactivate old, create new, chain, copy entities, log `doc_file_refreshed`) |
| `Documents.php:355` | `case 'parent_id'` filter in tableList |
| `PermissionHelpers.php:31` | docPermCheck map `op-doc-order → per-05-*` |
| `Form.vue:1048,1880` | client `lifnr`, order form + `hideAdd:true` single-file |
| `Form.vue:2385,2995` | `readonlyFields` compound-name lock |
| `OrderItemTable.vue` | Row list (scrollable, selectable checkbox) |
| `OForm.vue:63` | `lockedStatuses` + `isFilesLocked` |
| `OList.vue` | `order_no` first, `Sipariş Sevke Hazır` yellow badge |
| `CList.vue:195` | `Cari Kodu` column |
| `Sidebar.vue:229` | Transferler hidden |

## 6. Pending

- SAP cron (grouping logic defined, not formalized)
- Front panel design (backend ready)
- Dashboard rebuild (coal queries still)
- Legacy coal pages hidden, remove later

## 7. Credentials

- Docker: `tedarikNewApp` postgres on `5431`
- Panel: `http://127.0.0.1:8000` → `kadir@kontent.com.tr / Kadir412. / 111111`
- Seeds: 23 migrations + 5 roles + 77 sys_options + 9 admins + 18 order system dicts

## 8. How To Resume

```bash
docker start tedarikNewApp
cd panel && php artisan migrate:status
cd panel && npm run build
php artisan serve --host=127.0.0.1 --port=8000
# login kadir / Kadir412. / 111111 → /coalpanel/orders
# fresh SAP data: cd panel && php artisan tinker (run SAP grouping script)
```

## 9. Known Bugs / Debt

- **File replacement FIXED 2026-08-28.** Both paths version correctly. Docs: `panel/docs/file-replacement-fix.md`.
- `syncOrderStatusFromFiles` only fires from `documentFileStatus`, not `registerContent`.
- `transfer_no` entity on main order — OList uses `order_no` first.
