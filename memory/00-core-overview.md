# INGSYS Core Overview — For Future Sessions
**⚠️ on start of the everysession first read the panel/documantation/* for system mechanics

> **⚠️ CURRENT STATE 2026-09-05: 8/21/8/0/37 fresh `tedarikNewApp:5431` `grp_code=GDZ` (SAP --fresh + serials/files wipe). OList/DList bad-code purged + shared `composables/useClientModal|useTedarikDropdown|useTedarikHeight|useTableSearch` + `lib/pipe|statusMaps|escape` + Şirket backend-only `loadingClients` + uretim `YYYY/MM` monthSelect + `DList useAuthStore()` fix + `AuthController IS_TEST` bypass 111111 + `UserSeeder` kbbozat41 tedarikçi `0000300186` YILDIZ. Prev 2026-09-04 late+4: Permission rebuilt + single login + OList pagination + DList Filtreler + LOGGING ENRICHED + LList side-panel + AuditService cached + indexes.** DB `tedarikNewApp` :5431. `EBELN-X` clones, `op-doc-transfer` PURGED. `LIFNR` link, `GDZ/ADM`. `/tedarik` → `/tedarikpanel` 1360px 12px 210px 6 tabs. **Shared `OList/OForm/DList/DForm` via `isTedarik`**, Module Switcher, Detailed search, `group_key`. **`GET /api/v1/file-detail/:qnid` OLD-APP replica** + `LList` side-panel. `panel/documentation/optimize-suggestions.md` has shared composables for next listings.

> **👉 READ FIRST: `memory/05-order-system-state.md` (LIVE), then `06-roadmap-next.md`. This file = architecture reference.**

> **Source:** `panel/` is the real app. `memory/` is empty baseline, `panel/docs/` has 11 mapping docs (2026-08-01) — coal docs are now STALE, see `memory/05`. This file is the 30-second brain dump. Read first: `panel/docs/01-mimari-genel-bakis.md`, `panel/docs/TEKNIK_DOKUMANTASYON.md` then `memory/05`.

## 1. What This App Is
Tedarik Yönetim Sistemi — Order Management System (converted from KomurTedarik coal ERP). Generic EAV document engine wearing order-management skin. 4 role templates, one DB:
- **Admin** `immutable-admin` (27 perms) — manages orders, clients, file approvals, users, roles, reports
- **Supplier** `immutable-reseller` (per-041-02,03 + per-05-01,02 + per-06) — fills firm form + uploads files, bound to client(s)
- **Rapor Personeli** `immutable-rapor-personeli` (per-041-01 + per-05-02 + per-061-01) — İB sees only orders (no edit)
- **Satınalma Keyuser** `immutable-satınalma-keyuser` (per-041-01,03 + per-05 01-05 + per-06-01 + per-07-01) — İB full control

Multi-tenant via `panel/public/index.php:11-15` → `Host contains adm ? ADM : GDZ` (ex `yatagantermik ? YATAGAN : CATES`) → `$GLOBALS['SYS_CODE']` → `documents.grp_code`, `user_logs.sys_code`.

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
- **Auth blades:** `resources/views/auth/tedariklogin|loginSms|register|passwordReset.blade.php` + `tedarikapp.blade.php` + `moduleSelect.blade.php` + compiled `public/front/pages/*/page.js` (`tedariklogin` orange 560×840 140px Gdz, `loginSms` unified orange 560×720, `moduleSelect` orange 560×400)

## 4. The Big Lie: Providers Are Not Providers
`panel/app/Providers/` — only `AppServiceProvider` is real. The rest are **domain services called via `new`**:
| Class | Real Role | File |
|-------|-----------|------|
| `DocumentServiceProvider` | EAV CRUD, status, files | `app/Providers/DocumentServiceProvider.php:1096` |
| `PersonsServiceProvider` | persons/users/permissions/clientPermInfo | `app/Providers/PersonsServiceProvider.php:696` |
| `ReportServiceProvider` | dashboard aggregates | `app/Providers/ReportServiceProvider.php:479` |
| `EmailServiceProvider` | mail dispatch wrapper → Jobs | `app/Providers/EmailServiceProvider.php:112` |
| `EncryptionProvider` | AES-128-CBC + PBKDF2 (`pickle` key) | `app/Providers/EncryptionProvider.php:119` |

Proper Services: `MailService`, `SmsService`, `PermissionService`, `ExportService`, `RoleTemplateService`, `AuditService` (cached `actor/order/file` + `optionTitle`, 2026-09-04) in `app/Services/`.

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
`sys_options group op-doc`: `op-doc-order` (Sipariş header), `op-doc-order-item` (Kalem, parent_id=order), `op-doc-order-serial` (Seri, parent_id=item), `op-doc-client` (Cari). `op-doc-transfer` **PURGED 2026-09-02** (was seeded but never used — clones are `op-doc-order` with `transfer_no=EBELN-X`; 6 `sys_options` rows deleted, `OrderSystemSeeder.php` + `DocumentServiceProvider` + `Form.vue` + `PermissionHelpers` cleaned).

- **Order:** `doc_trans_order_created → transfer_sent → ready_for_shipment → approved/rejected` + `files_rejected`
- **File:** `doc_file_waiting → accepted | rejected → refreshed`
- **Serial:** `op-doc-order-serial` docs parented to items. Entities: `serial_no`, `production_date` (YYYY-MM-01), `quantity`, `unit`

## 7. Routing — 11 web + ~31 api
**Web** `routes/web.php:69`: `/` + `/tedarik` (single orange login `tedariklogin`), `/register`, `/logout`, `/smscallback` (2FA), `/module-select` (post-2FA module picker), `/auth/passwordreset/{code}`, `POST /auth/passchange`, `/coalpanel{any}` (`coalAuth` → `per-041-01`), `/tedarikpanel{any}` (`tedarikAuth` → `per-041-02`), `/order-file/{doc}`, `POST /export/offer`, `GET /export/{model}/{type?}`  
**API** `routes/api.php:69`: `POST auth/checkcode|sendmail|resend-code`, `POST /v1/auth/login/{type?}` (`admin`|`tedarik` → `session auth_panel`), `POST /v1/auth/register`, `POST /v1/auth/resetusercradentals` (PUBLIC!), `GET /v1/me`, `GET /v1/modules` (available modules for current user), `ANY /v1/document/{id?}`, `ANY /v1/transaction`, `POST /v1/temp-upload`, `POST /v1/table/{model}`, `POST /v1/export/malzeme-kabul|malzeme-cins-miktar-kabul`, `POST /v1/export/{model}`, `ANY /v1/users|persons`, `GET /v1/notifications`, `POST /v1/notificationlog/{id}/retrigger`, `GET/POST/DELETE /v1/roles/templates`, `GET /v1/roles/items`, `POST /v1/trans/set-status|cancel-offer|reopen-offer|set-file-status*|disable-document|orders/cancel|orders/rename|export/malzeme-kabul|malzeme-cins-miktar-kabul`, `ANY /v1/dashboard/{type}/{period?}`, `GET /v1/getpermissions` (heartbeat)

## 8. Auth & Session
**Single login:** `GET /` or `GET /tedarik` → `tedariklogin` blade (orange, `560×840`). `POST /v1/auth/login/tedarik` → `AuthController.php` flow: reCAPTCHA + `Cache login:attempts 5×15min` → `Auth::attempt` → `generateAndSendTwoFactorCode` → 6-digit to `storage/app/{token}-{personId}-login.txt` + Mail/SMS → redirect `GET /smscallback` (orange 2FA card). `POST auth/checkcode` (120s TTL) → if user has both `per-041-01` AND `per-041-02` → redirect `/module-select`; single module → auto-redirect via `session('target_module')` + hidden input in tedariklogin blade. Multi-module: `/module-select` blade shows available modules, user picks → `/coalpanel` or `/tedarikpanel`.

Middleware `coalAuth` requires `per-041-01`, `tedarikAuth` requires `per-041-02`, both allow `has('all')` DEV_ADMIN.

`public/front/pages/tedariklogin/page.js` sets `localStorage token` → routes by modules; `loginSms/page.js` unified.

Middleware `CheckPermissionVersion.php:91` on every protected request: if `force_logout` → 401, else if `permission_version` mismatch → `loadPermissionsToSession` + bump.

Frontend `stores/auth.js:43` heartbeat 30s + `pickle.js:105-154` 401 handlers (`permission_changed` → retry, `force_logout` → `/`).

## 9. Permission System
`sys_permission_catalogs` (27 entries, `per-XX-YY` codes) → `sys_role_templates` (4 immutable roles in `storage/entities/coal_roles_templates.json` + custom) → `sys_notification_types` (`notif-*`) → `sys_role_template_audit`.

**Permission tree:** `per-00` (Bildirim), `per-041` (Modüller 01→04), `per-04` (Kontrol 01→04), `per-05` (Siparişler 01→05 granular), `per-06` (Firma 01→02), `per-07` (Dökümanlar 01→02), `per-061` (Raporlar 01→02). **`per-08` and `per-04-05` are dead/removed.**

**4 role templates:** `immutable-admin` (Admin, 27 perms), `immutable-reseller` (Tedarikçi, per-041-02,03 + per-05-01,02 + per-06), `immutable-rapor-personeli` (İB Görüntüleme, per-041-01 + per-05-02 + per-061-01), `immutable-satınalma-keyuser` (İş Birimi, per-041-01,03 + per-05 01-05 + per-06-01 + per-07-01). Old `immutable-super-admin`, `immutable-satınalma-personeli` DELETED.

Per-person JSON array in EAV `op-doc-user-permission-form`. Runtime via `PermissionService::has()` → `DEV_ADMIN backdoor` → `session sper-{code}` → `file cache permissions.user.{id}` (30d, `microtime*1e6` version). Helpers: `checkPerm()`, `docPermCheck(typeKey, read|edit|status)` in `PermissionHelpers.php:215`. Frontend `GET /v1/getpermissions` drives menu/button visibility. `updateUserPermissions()` fans out role changes + `bumpUserPermissionVersion`. `SysRoleTemplateSeeder.php` uses `op_key` as unique identifier, auto-removes obsolete roles/perms.

## 10. Frontend Skeleton
`router/index.js:110` (routes under `/coalpanel` + `/tedarikpanel`, NO auth guard) → `layouts/App.vue:35` → `layouts/CoalPanel.vue:82` (admin Sidebar+Header) + `layouts/TedarikPanel.vue:551` (Tedarik **typewriter** `f2f2f3` bg `fixed inset:0 overflow:hidden` root `22px 18px 18px 48px`, frame `1360px 12px height:calc(100vh-40px) overflow:visible` holder-fixed, sidebar `210px 100%` pinned, `main:overflow:visible bg:#fff` + `main-inner:height:auto bg:#fff will-change:transform translateY(-scrollY)` paper-feed + `ResizeObserver` + `body height:scrollHeight+40` window drives only main, hidden at browser viewport, `OForm tedarik-detail bg:#fff` so step gaps not grey, logo 82px + label 11.5px, menu `flex1 center 64px 12px -52` protruding 38px, bottom 64px same, PickleTable card-rows) → pages in `resources/js/pages/coalsystem/` (Orders/OList+OForm **SHARED** via `isTedarik` (`$route.path.startsWith('/tedarikpanel')`) — same file serves `/coalpanel/orders` + `/tedarikpanel/orders`, decision **keep shared 2026-09-02** (see `memory/05 §7`); detailed search `OList.vue:940` 3×3 + `Filtreler` `teleport fixed z9999` `modalClients` backend-only `loadingClients` `flatpickr range` `Y/m` monthSelect `uretim Y/m`, Module Switcher `Sidebar.vue:393`/`TedarikPanel.vue:64`, Documents/DList `isTedarik` flat, Users/*, Roles/*, Logs/LList) → `composables/useClientModal|useTedarikDropdown|useTedarikHeight|useTableSearch` + `lib/pipe|statusMaps|escape` shared for next listings (see `panel/documentation/optimize-suggestions.md`)

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
- `panel/docs/mapping/*.md` (11 files) — file-by-file maps
- `panel/docs/mapping/10-models.md` — start here for DB
- `panel/docs/mapping/15-frontend-core.md` + `17-frontend-components.md` — start here for Form.vue
- `panel/documentation/logging-mechanics.md` — **enriched audit system** (actor+order+file frozen, 7 triggers, LList side-panel, AuditService perf)
- `panel/documentation/file-upload-versioning-mechanics.md` — file versioning (`replaced_id` chain, `isMultiFile` `**` count)
- `panel/documentation/form-system-mechanics.md` — EAV engine
- `panel/documentation/optimize-suggestions.md` — **shared composables + reuse for next listings** (`useClientModal` etc., `statusMaps`, `pipe`, `escape`)
- `memory/05-order-system-state.md` — LIVE order system state (read after `00`)
- `memory/06-roadmap-next.md` — next steps + decision tree
