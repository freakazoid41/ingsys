# INGSYS Core Overview — For Future Sessions

> **⚠️ CURRENT STATE 2026-08-28: CONVERTED to Order Management System — DB `tedarikNewApp` (5431) docker `tedarikNewApp` Up. Transfers are `op-doc-order` clones (`EBELN-X`), `op-doc-transfer` type NOT used. Order↔Client linked by `LIFNR` (`Cari Kodu`). 3 fresh SAP orders (`3510001793` 2 items, `3510002100` 3 items, `3510003500` 4 items) + 3 clients. Order detail: items ROW list (10-15 rows, scrollable), transfer selection above when canSend, Form below. **Lock: `ready_for_shipment` = FULL lock (desc+imalatci+files). `files_rejected` keeps files editable.** File replacement **FIXED**: both `addFileToDb` and `finalizeTempFile` now create version chains. See `panel/docs/file-replacement-fix.md`. See `memory/05-order-system-state.md` (LIVE), `memory/06-roadmap-next.md`. Panel `http://127.0.0.1:8000` (`kadir@kontent.com.tr / Kadir412. / 111111`).**

> **Source:** `panel/` is the real app. `memory/` is empty baseline, `panel/docs/` has 11 mapping docs (2026-08-01) — coal docs are now STALE, see `memory/05`. This file is the 30-second brain dump.
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
- **Auth blades:** `resources/views/auth/coallogin|loginSms|register|passwordReset.blade.php` + compiled `public/front/pages/*/page.js` (source missing)

## 4. The Big Lie: Providers Are Not Providers
`panel/app/Providers/` — only `AppServiceProvider` is real. The rest are **domain services called via `new`**:
| Class | Real Role | File |
|-------|-----------|------|
| `DocumentServiceProvider` | EAV CRUD, status, files | `app/Providers/DocumentServiceProvider.php:1096` |
| `PersonsServiceProvider` | persons/users/permissions/clientPermInfo | `app/Providers/PersonsServiceProvider.php:696` |
| `ReportServiceProvider` | dashboard aggregates | `app/Providers/ReportServiceProvider.php:479` |
| `EmailServiceProvider` | mail dispatch wrapper → Jobs | `app/Providers/EmailServiceProvider.php:112` |
| `EncryptionProvider` | AES-128-CBC + PBKDF2 (`pickle` key) | `app/Providers/EncryptionProvider.php:119` |

Proper Services: `MailService`, `SmsService`, `PermissionService`, `ExportService`, `RoleTemplateService` in `app/Services/`.

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
- Persons mirror: `persons` + `sys_con_ops` (`user-contact/client/permission/notification`) + `sys_con_entities` (permissions = JSON array in `op-doc-user-permission-form`)
- No FKs, soft delete (`status=0`), raw SQL everywhere.

## 6. Document Types & State Machines
`sys_options group op-doc`: `op-doc-request`, `op-doc-offer`, `op-doc-client` (+ `op-doc-*-form` in `op-doc-forms`)

- **Request:** `doc_trans_created → started → finished | cancelled`
- **Offer:** `created/draft → sended → review → approved|rejected|revision → revised → review…`
- **File:** `doc_file_waiting → accepted | rejected → refreshed`
Every transition = `transactions` row + `user_logs` row. `setStatus()` resolves `op_key→sys_options.id` — null if typo → crash. Known bug: two components send `doc_trans_offer_accepted` not in dict.

## 7. Routing — 8 web + ~30 api
**Web** `routes/web.php:69`: `/`, `/register`, `/logout`, `/smscallback`, `/auth/passwordreset/{code}`, `POST /auth/passchange`, `/coalpanel{any}`, `/order-file/{doc}`, `POST /export/offer`, `GET /export/{model}/{type?}`  
**API** `routes/api.php:69`: `POST auth/checkcode|sendmail|resend-code`, `POST /v1/auth/login|register`, `POST /v1/auth/resetusercradentals` (PUBLIC!), `GET /v1/me`, `ANY /v1/document/{id?}`, `ANY /v1/transaction`, `POST /v1/temp-upload`, `POST /v1/table/{model}`, `POST /v1/export/{model}`, `ANY /v1/users|persons`, `GET /v1/notifications`, `POST /v1/notificationlog/{id}/retrigger`, `GET/POST/DELETE /v1/roles/templates`, `GET /v1/roles/items`, `POST /v1/trans/set-status|cancel-offer|reopen-offer|set-file-status*|disable-document`, `ANY /v1/dashboard/{type}/{period?}`, `GET /v1/getpermissions` (heartbeat)

## 8. Auth & Session
`AuthController.php:873` flow: `POST /v1/auth/login` (reCAPTCHA + `Cache login:attempts 5×15min` → `Auth::attempt` → `generateAndSendTwoFactorCode` → 6-digit to `storage/app/{token}-{personId}-login.txt` + Mail/SMS to `contmail*/contphone*`) → `GET /smscallback` → `POST auth/checkcode` (120s TTL one-time) → `Auth::login` → `loadUserPermissionsToSession` → `clientPermInfo` → `createToken` → `ActiveSession` → `forceLogoutPerson` (single-session) → SPA.

Middleware `CheckPermissionVersion.php:91` on every protected request: if `force_logout` → 401, else if `permission_version` mismatch → `loadPermissionsToSession` + bump.

Frontend `stores/auth.js:43` heartbeat 30s + `pickle.js:105-154` 401 handlers (`permission_changed` → retry, `force_logout` → `/`).

## 9. Permission System
`sys_permission_catalogs` (`per-XX-YY` codes, 19 entries) → `sys_role_templates` (5 immutable roles in `storage/entities/coal_roles_templates.json` + custom) → `sys_notification_types` (`notif-*`) → `sys_role_template_audit`.

Per-person JSON array in EAV `op-doc-user-permission-form`. Runtime via `PermissionService::has()` → `DEV_ADMIN backdoor` → `session sper-{code}` → `file cache permissions.user.{id}` (30d, `microtime*1e6` version). Helpers: `checkPerm()`, `docPermCheck(typeKey, read|edit|status)` in `PermissionHelpers.php:215`. Frontend `GET /v1/getpermissions` drives menu/button visibility. `updateUserPermissions()` fans out role changes + `bumpUserPermissionVersion`.

## 10. Frontend Skeleton
`router/index.js:103` (17 routes under `/coalpanel`, NO auth guard) → `layouts/App.vue:35` → `layouts/CoalPanel.vue:82` (Sidebar+Header+Simplebar) → pages in `resources/js/pages/coalsystem/` (18 pages: Request/*, Offer/*, Client/*, Users/*, Roles/*, Documents/DList, Logs/LList, NotificationLogs/NList, Notifications/NSettings, Dashboard.vue, Example/*)

5 Pinia stores: `auth`, `permissiondata`, `navigation` (breadcrumbs+notifications+sys_code DOM read), `events` (legacy), `formdata` (list→form carrier, typo `addional`)

Central client `lib/pickle.js:824` — fetch wrapper (CSRF meta + Bearer localStorage) + form toolkit (`checkForm`, `clearElements`, `validatePassword`) + UI (`toast`, `formatMoney`, `compressImage`, `crypFunc` base64).

## 11. Critical Gotchas For New Apps
- **EAV is the abstraction** — no migrations for new fields, just new `sys_options` + `Form.vue` schema
- **Form.vue is monolith** (~2892 lines, imperative `document.createElement`, not Vue template) — see `memory/01-form-engine.md`
- **16 models** in `app/Models/` — `Documents::tableList:398` is most complex raw SQL (tenant + supplier `clientQnidList` + `her_ikisi` + `main_attr` JSON)
- **Schedule:** `Kernel.php` → `request:autoclose 01:00` (bug: only first record), `active-sessions:clean 02:00`
- **Security debt:** SQLi 9 spots raw concat, `DEV_ADMIN=111111` backdoor, public `resetusercradentals`, plaintext `storage/app/*.txt` 2FA, `.env.example` has live secrets, CSRF off, `/order-file` IDOR, `pickle` hardcoded key, `mail relay_password` logged unmasked
- **Vendor committed:** `panel/vendor/` + `node_modules/` in git (`.gitignore` missing), `prjBuildLive` does `migrate:fresh` + wipes documents — never run on prod

## 12. Doc Map
- `panel/docs/01-mimari-genel-bakis.md` — overview
- `panel/docs/TEKNIK_DOKUMANTASYON.md:411` — full guide
- `panel/docs/02-bulgular-ve-dogrulama.md` — all bugs/findings
- `panel/docs/03-iliski-haritasi.md` — endpoint ↔ screen cross-table
- `panel/docs/mapping/*.md` (11 files) — file-by-file maps: 10-models, 11-http, 12-services, 13-infra, 14-config-database, 15-frontend-core, 16-frontend-pages, 17-frontend-components, 18-views-i18n-mail, 19-mevcut-dokuman-inceleme, 20-misc
- `panel/docs/mapping/10-models.md` — start here for DB
- `panel/docs/mapping/15-frontend-core.md` + `17-frontend-components.md` — start here for Form.vue
