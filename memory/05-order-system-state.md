# Order Management System — Current State (2026-09-09)

> **Read after `00-core-overview.md`. Mechanics live in `panel/documentation/*` — this file is LIVE snapshot only, no mechanic duplication.**
> **Control panel DONE. Tedarik panel DONE & POLISHED. Tenants GDZ/ADM. SAP group-by-EBELN. Permissions rebuilt 2026-09-04. Dashboards rebuilt 2026-09-06. Bilgilendirmeler DONE 2026-09-07. Email redesign DONE 2026-09-09.**

## 1. Latest (2026-09-09, newest first)
- **late4 LOGO EQUALITY:** GDZ vs ADM aspect mismatch fixed CSS-only (`logo-adm` shave). Rule: NEVER edit logo viewBoxes. Mail PNGs re-baked.
- **late3 TENANT LOGOS:** auth blades + `TedarikPanel.vue` sidebar switch `GDZ.svg` / `adm-logo.svg` by `SYS_CODE`. Mails follow ORDER system, unaffected.
- **late2 FILE→ORDER FIX:** `DocumentController` new `resolveFileOrderDoc()` (entity-conn based, no parent overshoot) + `file_qnid` for detail CTA. `DList.vue:850` JSON leak fixed via shared `noteOf()`.
- **late1 EMAIL REDESIGN:** `TedarikMailHelper.php` NEW + `layout.blade.php` tedarik theme + `?next=` gateway + CID logos. kadir (BOTH, all 7 groups) got 7×GDZ + 7×ADM for `3510004400-1`, all `sent`. Spec: `notification-sending-system.md:9`, gateway: `session-and-login-mechanics.md:12`.
- **2026-09-08 late03 LEAK FIX:** `downloadAllOrderFiles` / `listOrderFiles` / `syncOrderStatusFromFiles` scoped by `(d.id + dt.op_key=order) OR (parent + item)` — base no longer leaks clone files. `OForm.vue:151` `hasAnyFile` hides strip when empty.
- **2026-09-07 late15-18:** imalatci print trap (`tedarikImalatci` preferred over `getFieldValue()`), tedarik-03 resend on refresh, save loading + Detail Kalite, list Kalite confirm.
- Full per-commit history removed — use `git log`. Current behavior: `panel/documentation/tedarik-system-process.md`.

## 2. What Changed Since Coal
Same EAV skeleton, new business. No migration for business fields.

| Layer | Coal | Now |
|-------|------|-----|
| DB | `b2x` 5431 | `tedarikNewApp` 5431, 23 migrations, `GRP GDZ` default |
| Doc types | request/offer/client/user/flat | `op-doc-client/order/item/serial` only (request/offer/transfer PURGED) |
| Forms | 5 legacy | `op-doc-order-form` (order_no/buying_no/spec/sys/ctitle/created readOnly + desc + imalatci + kabul/cins single) + `item-form` (prod/title/qty/unit + test single + images multi) + `serial-form` + `client-form` (+lifnr +client_system) |
| Status | — | `created → transfer_sent → ready → approved/rejected` + `files_rejected`. Kalite `approved` from ANY |
| File types | 5 | + `transfer_kabul/cins`, `item_test`, `item_images` |
| Perms | 19 | 27 (`per-00/041/04/05-01..05/06/07/061`), `per-08` dead. 4 immutable roles |
| Frontend | 17 routes | `/coalpanel/orders` + `/tedarikpanel` (7 tabs), shared `OList/OForm/DList/DForm` via `isTedarik` |
| Auth | blue + orange | single orange `tedariklogin`, `/module-select` post-2FA, `DEV_ADMIN` + `111111` IS_TEST |
| Tenants | YATAGAN/CATES | `adm ? ADM : GDZ` |

## 3. Data Model — Critical (no FKs)
1. **Order↔Client = `LIFNR+SYSTEM`:** `order.spec_code = client.lifnr AND order.sys_code (BUKRS→GDZ/ADM) = client.client_system`. Display `SYSTEM-LIFNR`.
2. **Items** = `op-doc-order-item` (`parent_id=order.id`). Fields `prod_code (=MATNR**EBELP)`, `title`, `quantity`, `unit` (ST int / KG,M float).
3. **Serials** = `op-doc-order-serial` (`parent_id=item.id`). `serial_no`, `production_date` (YYYY-MM-01), `quantity`, `unit`. KG/M required, ST<300 optional, ST≥300 none.
4. **Partial → clone** `op-doc-order` `transfer_no=EBELN-X` only when split. Restores qty on delete.
5. Rules detail: `panel/documentation/tedarik-system-process.md:2`

## 4. Flow (5 lines, detail in mechanics)
SAP flat rows → `orders:sync` groups by EBELN → supplier 6-step detail (transfer type → desc → imalatci → print PDFs → files → Gönder `processOrderTransfer`) → admin `DList/DForm` approve/reject → `syncOrderStatusFromFiles` auto `files_rejected ↔ transfer_sent` → Kalite close / cancel / rename.
Test plan: `panel/documentation/tedarik-system-process.md:12`. File rules: `file-versioning-system.md`. Logs: `logging-mechanics.md`. Notifs: `notification-*.md`.

## 5. Credentials & Infra
- Docker `tedarikNewApp` `127.0.0.1:5431`, `.env` `DB_DATABASE=tedarikNewApp`
- `php artisan serve --host=127.0.0.1 --port=8000`
- Logins: `kadir@kontent.com.tr / Kadir412.` → `111111` → `/coalpanel` or `/tedarik`
- Fresh: `php artisan orders:reset` (wipe serials/files/reads/cache + `sync --fresh`). Payload `/tmp/sap_fresh_payload.json` 21 rows → 8 EBELN. See `memory/07`.

## 6. How To Resume
```bash
docker start tedarikNewApp
cd panel && php artisan migrate:status # 23 done
cd panel && npm run build # after any Vue edit
php artisan serve --host=127.0.0.1 --port=8000
php artisan orders:reset # clean slate when needed
```

## 7. Active Debt (history removed)
- File replacement FINAL: new `sys_con_entities` row per upload, active = `document_files.status=1`, `new.replaced_id=old`. Do NOT change without Master.
- `ready_for_shipment` full lock; `files_rejected` keeps files editable.
- `getFormData json_agg ORDER BY t.id` — last = current.
- ST cap 300. PHP `??` vs `?:` on `''`. Export PDFs before `{model}` wildcard.
- Open: `Documents::tableList parent_type_id=0` items in main lists; security (`DEV_ADMIN`, public reset, CSRF off, `/order-file` IDOR, `pickle` key); `B2X` stopped container; `permission_version file` store flaky.
- Shared-pages decision: `OList/OForm/DList/DForm` stay SHARED via `isTedarik`, fork only if supplier flow diverges.

## 8. Memory Map
| File | Contents |
|------|----------|
| `00` | architecture + doc map |
| `01` | Form.vue engine intro (canonical: `form-system-mechanics.md`) |
| `02` | EAV intro (canonical: `form-system-mechanics.md`) |
| `03` | new doc-type recipe |
| `04` | patterns intro (canonical: mechanics) |
| `05` | THIS live snapshot |
| `06` | next steps |
| `07` | SAP sync command |
| `idea` | original spec |
