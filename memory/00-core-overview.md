# INGSYS Core Overview — For Future Sessions

> **⚠️ CURRENT STATE 2026-08-28: CONVERTED to Order Management System — DB `tedarikNewApp` (5431) docker `tedarikNewApp` Up. Transfers are `op-doc-order` clones (`EBELN-X`), `op-doc-transfer` type NOT used. Order↔Client linked by `LIFNR` (`Cari Kodu`). 3 fresh SAP orders (`3510001793` 2 items, `3510002100` 3 items, `3510003500` 4 items) + 3 clients. Order detail: items ROW list (10-15 rows, scrollable), transfer selection above when canSend, Form below. **Lock: `ready_for_shipment` = FULL lock (desc+imalatci+files). `files_rejected` keeps files editable.** File replacement **FIXED**: both `addFileToDb` and `finalizeTempFile` now create version chains. See `panel/docs/file-replacement-fix.md`. See `memory/05-order-system-state.md` (LIVE), `memory/06-roadmap-next.md`. Panel `http://127.0.0.1:8000` (`kadir@kontent.com.tr / Kadir412. / 111111`).**

> **Source:** `panel/` is the real app. `memory/` is session brain. `panel/docs/` has stale coal docs. This file is the 30-second brain dump.
> **Read first:** `panel/docs/01-mimari-genel-bakis.md`, `panel/docs/TEKNIK_DOKUMANTASYON.md` then `memory/05-order-system-state.md`

## 1. What This App Is
KomurTedarik — coal procurement / tender ERP. Two roles, one DB, two domains:
- **Admin** `op-pert-admin` — creates suppliers, approves docs, opens requests (ihale), evaluates offers
- **Supplier** `op-pert-reseller` — self-registers (`user_status=-1`), fills firm form + uploads files, after `doc_file_accepted` can bid

Multi-tenant via `panel/public/index.php:11-15` → `Host contains yatagantermik ? YATAGAN : CATES` → `$GLOBALS['SYS_CODE']` → `documents.grp_code`, `user_logs.sys_code`.

## 2. Stack
Laravel 12 / PHP 8.2 / PostgreSQL (port 5431) / Sanctum 4 (token+session)  
Vue 3 SPA @ `/coalpanel` — Vite 6, Pinia 2, vue-router 4, Tailwind 3  
Mail: SMTP Gmail or `intmail.aydemenerji.com.tr:25` via `MAIL_USE_RELAY` (`MailService.php:52`)  
SMS: İletişim Makinesi `UserGatewayWS`/`SMSGatewayWS` (`SmsService.php`)  
PDF/Excel: dompdf/mpdf + PhpSpreadsheet  
Queue/Cache/Session: `database` driver; permission cache: `file` store

## 3. Entry Points
- **HTTP:** `panel/public/index.php` → `panel/bootstrap/app.php:9-32` (routes `web.php`/`api.php`, middleware `ParsePutMultipart`+`CspMiddleware`, `trustProxies('*')`, `validateCsrfTokens except ['*']` → CSRF OFF)
- **SPA shell:** `GET /coalpanel/{any}` closure in `web.php:30-48` (checks `session type_key+2f_success` → `resources/views/coalapp.blade.php`)
- **SPA bootstrap:** `resources/js/app.js:1-71` → `authStore.getPermissions()` → parallel `permissionDataStore.fetchRoleTemplates/items()` → supplier `!canProceed` redirect → `authStore.startHeartbeat()` 30s `GET /api/v1/getpermissions` → mount

## 4. The Big Lie: Providers Are Not Providers
`panel/app/Providers/` — only `AppServiceProvider` is real. The rest are **domain services called via `new`**:
| Class | Real Role | File |
|-------|-----------|------|
| `DocumentServiceProvider` | EAV CRUD, status, files | `app/Providers/DocumentServiceProvider.php` |
| `PersonsServiceProvider` | persons/users/permissions/clientPermInfo | `app/Providers/PersonsServiceProvider.php` |
| `ReportServiceProvider` | dashboard aggregates | `app/Providers/ReportServiceProvider.php` |
| `EmailServiceProvider` | mail dispatch wrapper → Jobs | `app/Providers/EmailServiceProvider.php` |
| `EncryptionProvider` | AES-128-CBC + PBKDF2 (`pickle` key) | `app/Providers/EncryptionProvider.php` |

## 5. Data Architecture — EAV + Universal Dictionary
**Core 4 tables:**
```
sys_options       — dictionary: op_key/group_key/ttitle/ctitle (every type lives here)
documents         — main entity: type_id→sys_options, qnid (UUID), grp_code, person_id (text!), status
sys_con_ops       — form section instance: main_id→documents/persons, type_id→form type, conn_id, sub_type_id
sys_con_entities  — field value: conn_id→sys_con_ops, entity_tag = field**group**key, entity_value, table_tag
```

- `entity_tag` format: `{field}**{group}**{key}` e.g. `contphone**userfacilitygroup**main-0`
- File fields: `table_tag='document_files'` and `entity_value = file id`
- Persons mirror: `persons` + `sys_con_ops` + `sys_con_entities`
- No FKs, soft delete (`status=0`), raw SQL everywhere.

## 6. Document Types & State Machines
- **Request:** `doc_trans_created → started → finished | cancelled`
- **Offer:** `created/draft → sended → review → approved|rejected|revision → revised → review…`
- **Order:** `doc_trans_order_created → transfer_sent → ready_for_shipment → approved/rejected` + `files_rejected`
- **File:** `doc_file_waiting → accepted | rejected → refreshed`

## 7. Routing — 8 web + ~30 api
**Web** `routes/web.php:69`: `/`, `/register`, `/logout`, `/coalpanel{any}`, `/order-file/{doc}`  
**API** `routes/api.php:69`: `ANY /v1/document/{id?}`, `POST /v1/temp-upload`, `POST /v1/table/{model}`, `POST /v1/trans/set-status|cancel-offer|set-file-status*`, `POST /v1/orders/cancel`, `GET /v1/getpermissions` (heartbeat)

## 8. Auth & Session
2FA flow: login → 6-digit code → `Auth::login` → `loadUserPermissionsToSession` → `ActiveSession` → SPA. Heartbeat 30s `GET /v1/getpermissions`. Middleware `CheckPermissionVersion` refreshes on mismatch.

## 9. Permission System
`sys_permission_catalogs` (`per-XX-YY`) → `sys_role_templates` → `sys_notification_types` (`notif-*`). Per-person JSON in EAV. `DEV_ADMIN` backdoor. `docPermCheck()` in `PermissionHelpers.php`.

## 10. Frontend Skeleton
`router/index.js` (routes under `/coalpanel`) → `layouts/CoalPanel.vue` (Sidebar+Header) → pages in `resources/js/pages/coalsystem/`.
5 Pinia stores: `auth`, `permissiondata`, `navigation`, `events`, `formdata`.
Central client `lib/pickle.js` — fetch wrapper + form toolkit + UI helpers.

## 11. Critical Gotchas For New Apps
- **EAV is the abstraction** — no migrations for new fields, just `sys_options` + `Form.vue` schema
- **Form.vue is monolith** (~3300 lines, imperative DOM) — see `memory/01-form-engine.md`
- **Security debt:** SQLi, `DEV_ADMIN=111111`, CSRF off, IDOR, hardcoded keys
- **File replacement FIXED** — both `addFileToDb` and `finalizeTempFile` create version chains. See `panel/docs/file-replacement-fix.md`.

## 12. Doc Map
- `panel/docs/file-replacement-fix.md` — file replacement fix (2026-08-28)
- `panel/docs/01-mimari-genel-bakis.md` — overview
- `panel/docs/TEKNIK_DOKUMANTASYON.md` — full guide
- `memory/05-order-system-state.md` — LIVE snapshot
- `memory/06-roadmap-next.md` — plans
