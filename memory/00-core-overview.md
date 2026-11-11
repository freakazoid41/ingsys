# INGSYS Core Overview

> **CURRENT 2026-09-09: both panels live. See `memory/05-order-system-state.md` (LIVE snapshot) then `memory/06-roadmap-next.md`. Mechanics live in `panel/documentation/*` — that is the core, do not duplicate here.**
> **Read order for new sessions: `00` → `05` → `06` → mechanic bibles as needed. `panel/docs/*` is STALE coal mapping (2026-08-01), reference only.**

## 1. What This App Is
Tedarik Yönetim Sistemi — Order Management System (converted from KomurTedarik coal ERP). Generic EAV document engine wearing order-management skin. 4 role templates, one DB:
- **Admin** `immutable-admin` (27 perms) — orders, clients, file approvals, users, roles, reports
- **Supplier** `immutable-reseller` (per-041-02,03 + per-05-01,02 + per-06) — firm form + uploads, bound to client(s)
- **Rapor Personeli** `immutable-rapor-personeli` (per-041-01 + per-05-02 + per-061-01) — read-only orders
- **Satınalma Keyuser** `immutable-satınalma-keyuser` (per-041-01,03 + per-05 01-05 + per-06-01 + per-07-01) — full IB control

Multi-tenant via `panel/public/index.php:11-15` → `Host contains adm ? ADM : GDZ` → `$GLOBALS['SYS_CODE']` → `documents.grp_code`, `user_logs.sys_code`. Users split `GDZ/ADM/BOTH` — `Form.vue:323` select, `UList.vue:115` pill + tri-filter, `AuthController.php:280` login split (list shows all, login enforces).

## 2. Stack
Laravel 12 / PHP 8.2 / PostgreSQL (port 5431) / Sanctum 4 (token+session)
Vue 3 SPA @ `/coalpanel` (admin) + `/tedarikpanel` (public Tedarik, `TedarikPanel.vue`) — Vite 6, Pinia 2, vue-router 4, Tailwind 3
Mail: SMTP Gmail or `intmail.aydemenerji.com.tr:25` via `MAIL_USE_RELAY` (`MailService.php:52`)
SMS: İletişim Makinesi `UserGatewayWS`/`SMSGatewayWS` (`SmsService.php`)
PDF/Excel: dompdf/mpdf + PhpSpreadsheet
Queue/Cache/Session: `database` driver; permission cache: `file` store

## 3. Entry Points
- **HTTP:** `panel/public/index.php` → `panel/bootstrap/app.php:9-32` (routes `web.php`/`api.php`, middleware `ParsePutMultipart`+`CspMiddleware`, `trustProxies('*')`, `validateCsrfTokens except ['*']` → CSRF OFF)
- **SPA shells:** `GET /coalpanel/{any}` → `coalapp.blade.php` + `GET /tedarikpanel/{any}` → `tedarikapp.blade.php` closures in `web.php:30-60` (checks `session type_key+2f_success`)
- **SPA bootstrap:** `resources/js/app.js:1-71` → `authStore.getPermissions()` → parallel `permissionDataStore.fetchRoleTemplates/items()` → `authStore.startHeartbeat()` 30s `GET /api/v1/getpermissions` → mount (serves both panels)
- **Auth blades:** `tedariklogin|loginSms|register|passwordReset` + `tedarikapp` + `moduleSelect` + compiled `public/front/pages/*/page.js`

## 4. The Big Lie: Providers Are Not Providers
`panel/app/Providers/` — only `AppServiceProvider` is real. The rest are **domain services called via `new`**:
| Class | Real Role | File |
|-------|-----------|------|
| `DocumentServiceProvider` | EAV CRUD, status, files | `app/Providers/DocumentServiceProvider.php` |
| `PersonsServiceProvider` | persons/users/permissions/clientPermInfo | `app/Providers/PersonsServiceProvider.php` |
| `ReportServiceProvider` | dashboard aggregates | `app/Providers/ReportServiceProvider.php` |
| `EmailServiceProvider` | mail dispatch wrapper → Jobs | `app/Providers/EmailServiceProvider.php` |
| `EncryptionProvider` | AES-128-CBC + PBKDF2 (`pickle` key) | `app/Providers/EncryptionProvider.php` |

Proper Services: `MailService`, `SmsService`, `PermissionService`, `ExportService`, `RoleTemplateService`, `AuditService` (cached `actor/order/file` + `optionTitle`) in `app/Services/`.

## 5. Data Architecture — EAV + Universal Dictionary
**Core 4 tables:**
```
sys_options       — dictionary: op_key/group_key/ttitle/ctitle
documents         — main entity: type_id→sys_options, qnid (UUID), grp_code, person_id (text!), status
sys_con_ops       — form section instance: main_id→documents/persons, type_id→form type
sys_con_entities  — field value: conn_id→sys_con_ops, entity_tag = field**group**key, table_tag
```
- `entity_tag`: `{field}**{group}**{key}`. File fields: `table_tag='document_files'`, `entity_value = file id`
- Persons mirror: `persons` + `sys_con_ops` + `sys_con_entities` (permissions = JSON array in `op-doc-user-permission-form`)
- No FKs, soft delete (`status=0`), raw SQL everywhere.
- Detail: `memory/02-eav-dictionary.md` + `panel/documentation/form-system-mechanics.md`

## 6. Document Types & State Machines
`sys_options group op-doc`: `op-doc-order` (header), `op-doc-order-item` (parent_id=order), `op-doc-order-serial` (parent_id=item), `op-doc-client` (Cari). `op-doc-transfer` + `op-doc-request/offer` **PURGED** — clones are `op-doc-order` with `transfer_no=EBELN-X`.

- **Order:** `created → transfer_sent → ready_for_shipment → approved/rejected` + `files_rejected`. Kalite `approved` from **ANY** status.
- **File:** `waiting → accepted | rejected → refreshed`
- **Serial:** `op-doc-order-serial` parented to items. Entities: `serial_no`, `production_date` (YYYY-MM-01), `quantity`, `unit`

## 7. Routing — 11 web + ~31 api
**Web** `routes/web.php`: `/`, `/tedarik`, `/register`, `/logout`, `/smscallback`, `/module-select`, `/auth/passwordreset/{code}`, `POST /auth/passchange`, `/coalpanel{any}` (`coalAuth` → `per-041-01`), `/tedarikpanel{any}` (`tedarikAuth` → `per-041-02`), `/order-file/{doc}`, `POST /export/*`, `GET /export/{model}/{type?}`
**API** `routes/api.php`: `auth/checkcode|sendmail|resend-code`, `POST /v1/auth/login/{type?}`, `POST /v1/auth/register`, `POST /v1/auth/resetusercradentals` (PUBLIC!), `GET /v1/me`, `GET /v1/modules`, `ANY /v1/document/{id?}`, `ANY /v1/transaction`, `POST /v1/temp-upload`, `POST /v1/table/{model}`, `POST /v1/export/malzeme-kabul|malzeme-cins-miktar-kabul` (before wildcard), `POST /v1/export/{model}`, `ANY /v1/users|persons`, `GET /v1/notifications`, `POST /v1/trans/set-status|set-file-status*|disable-document|orders/cancel|orders/rename`, `ANY /v1/dashboard/{type}/{period?}`, `GET /v1/getpermissions`

## 8. Auth & Session
Single orange login `GET /` or `GET /tedarik` → `tedariklogin` → `POST /v1/auth/login/tedarik` (reCAPTCHA + 5×15min lock → `Auth::attempt` → 6-digit to `storage/app/*.txt` + Mail/SMS → `GET /smscallback` → `POST auth/checkcode` 120s TTL). Post-2FA: both perms → `/module-select`, else auto-redirect.
Middleware `coalAuth` (`per-041-01`), `tedarikAuth` (`per-041-02`), `CheckPermissionVersion.php:91` (force_logout / version refresh). Frontend `stores/auth.js:43` heartbeat 30s + `pickle.js` 401 handlers.
Detail: `panel/documentation/session-and-login-mechanics.md` + `single-session-enforcement-system.md`

## 9. Permission System
`sys_permission_catalogs` (27 entries) → `sys_role_templates` (4 immutable) → `sys_notification_types` (`tedarik-01..07`) → audit. Read tracking via `notification_reads`.
Tree: `per-00`, `per-041` (01-04), `per-04` (01-04), `per-05` (01-05), `per-06` (01-02), `per-07` (01-02), `per-061` (01-02). `per-08`, `per-04-05` dead.
Roles: `immutable-admin` (27), `immutable-reseller` (041-02,03 + 05-01,02 + 06), `immutable-rapor-personeli`, `immutable-satınalma-keyuser`. Old super-admin/satınalma-personeli DELETED.
Runtime: `PermissionService::has()` → session `sper-*` → file cache. Helpers `checkPerm()`, `docPermCheck()` in `PermissionHelpers.php:215`.
Detail: `panel/documentation/permission-system-analysis.md`

## 10. Frontend Skeleton
`router/index.js:110` → `CoalPanel.vue` + `TedarikPanel.vue:551` (typewriter paper-feed, 1360px frame, 210px sidebar) → `pages/coalsystem/` (`OList/OForm/DList/DForm` **SHARED** via `isTedarik`, decision keep shared). Module Switcher, detailed search, `group_key`.
5 Pinia stores: `auth`, `permissiondata`, `navigation`, `events` (legacy), `formdata`. Central client `lib/pickle.js:824`.
Shared libs: `statusUtils`, `dateUtils`, `statusMaps`, `pipe`, `escape`, `pdf` + composables `useClientModal|useTedarikDropdown|useTedarikHeight|useTableSearch`.
Detail: `panel/documentation/optimize-suggestions.md`

## 11. Critical Gotchas
- EAV is the abstraction — no migrations for new fields
- `Form.vue` monolith (~2892 lines, imperative) — see `memory/01-form-engine.md`
- `Documents::tableList:398` is the complex query (tenant + `LIFNR+SYSTEM` + `main_attr`)
- Schedule: `request:autoclose 01:00` (first-record bug), `active-sessions:clean 02:00`
- Security debt: SQLi concat, `DEV_ADMIN=111111`, public reset, plaintext 2FA txt, CSRF off, `/order-file` IDOR, `pickle` key, relay password logged
- `vendor/` + `node_modules/` committed, `prjBuildLive` wipes documents — never on prod
- Detached `v-model="tedarik*"` invisible to `getFieldValue()` — always prefer `(tedarikX?.trim())||getFieldValue()||orderEntities`. See `tedarik-system-process.md:164`
- Route ordering: export PDFs before `{model}` wildcard

## 12. Doc Map (core = panel/documentation)
- `form-system-mechanics.md` — EAV engine (canonical, memory/01 is intro)
- `file-versioning-system.md` — versioning (canonical)
- `logging-mechanics.md` — enriched audit + side-panel + AuditService
- `notification-receiving-system.md` — bell + Bilgilendirmeler + read tracking
- `notification-sending-system.md` + `TedarikMailHelper` — BUKRS/LIFNR gates, email cards, `?next=` gateway
- `tedarik-system-process.md` — end-to-end order lifecycle + test plan (canonical for §5 flow)
- `session-and-login-mechanics.md` + `single-session-enforcement-system.md`
- `optimize-suggestions.md` — shared composables/libs for next listings
- `export-system.md` — Excel pipeline
- `modules/order-planning.md` — Malzeme Planlama bible (doc types, chain, notifs, gotchas)
- `modules/products.md` — Ürün bible (categories, products, kVA defs, file templates, checks/tagbox field, gotchas)
- `memory/05` — LIVE snapshot, `memory/06` — next steps, `memory/07` — SAP sync command
