# INGSYS Core Overview — For Future Sessions

> **⚠️ CURRENT STATE 2026-09-02 late: Order Management System + Public Tedarik Panel POLISHED.** DB `tedarikNewApp` :5431, 8 orders / 21 items / 8 clients / 0 files / 0 serials (re-seeded 2026-09-02 GDZ). Transfers = `op-doc-order` clones (`EBELN-X`), `op-doc-transfer` **PURGED 2026-09-02** (6 rows deleted). Order↔Client by `LIFNR`. Tenants **GDZ/ADM** (ex CATES/YATAGAN) via `GDZ.svg/ADM.svg`. Public login `/tedarik` → `/tedarikpanel` orange Gdz card 560×840 140px logo → TedarikPanel card 1360px white `12px` shadow, sidebar 210px, 6 tabs 64×12 protruding -52 (38px outside) `48px` left gap, logo 82px + `Malzeme Tedarik İş Süreci` 11.5px, menu centered flex1, PickleTable card-rows `0 7px` `auto`. **Shared pages** `OList/OForm` via `isTedarik` (keep shared 2026-09-02), Module Switcher `Modüller` above Çıkış → `/coalpanel|/tedarikpanel`, Tedarik detailed search 3×3 hover `absolute` (Stok/Sipariş/Alım/Seri/Üretim/Şirket=client list/Tedarikçi=users with lifnr/Onay/Tarih). File replacement FINAL + item files (Test single + Görseller multi) via `group_key`. **FIX 2026-09-02 late: Şirkete Göre Ara empty → openClientModal multi + instant hardFallback + data scope fix, Detaylı Filtre/Sıfırla auto-close `showDetailed=false`. OForm `isTedarik` 6-step detail (`OForm.vue:966` screenshot 1:1 fresh order header+warning+1 Malzeme/tedarik tipi with `OrderItemTable` +2 Açıklama `tedarikDesc`+3 İmalatçı `tedarikImalatci`+4 İndir `printMalzemeKabul/Cins` +5 Yükle `tedarikKabul/CinsFile` temp-upload +6 Gönder `formatDate` fix `OForm.vue:963`).** Panel `http://127.0.0.1:8000` (`kadir@kontent.com.tr / Kadir412. / 111111`).
> **👉 READ FIRST: `memory/08-session-summary.md` (clean 2-min handoff), then `05-order-system-state.md` (LIVE), `06-roadmap-next.md`. This file = architecture reference.**

> **Source:** `panel/` is the real app. `memory/` is empty baseline, `panel/docs/` has 11 mapping docs (2026-08-01) — coal docs are now STALE, see `memory/05`. This file is the 30-second brain dump.
> **Read first:** `panel/docs/01-mimari-genel-bakis.md`, `panel/docs/TEKNIK_DOKUMANTASYON.md` then `memory/05-order-system-state.md`

## 1. What This App Is
Tedarik Yönetim Sistemi — Order Management System (converted from KomurTedarik coal ERP). Generic EAV document engine wearing order-management skin. Two roles, one DB:
- **Admin** `op-pert-admin` — manages orders, clients, file approvals
- **Supplier** `op-pert-reseller` — self-registers, fills firm form + uploads files

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
- **SPA bootstrap:** `resources/js/app.js:1-71` → `authStore.getPermissions()` → parallel `permissionDataStore.fetchRoleTemplates/items()` → supplier `!canProceed` redirect → `authStore.startHeartbeat()` 30s `GET /api/v1/getpermissions` → mount (serves both panels)
- **Auth blades:** `resources/views/auth/coallogin|tedariklogin|loginSms|register|passwordReset.blade.php` + `tedarikapp.blade.php` + compiled `public/front/pages/*/page.js` (`tedariklogin` orange 560×840 140px Gdz, `loginSms` unified orange 560×720)

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
`sys_options group op-doc`: `op-doc-order` (Sipariş header), `op-doc-order-item` (Kalem, parent_id=order), `op-doc-order-serial` (Seri, parent_id=item), `op-doc-client` (Cari). `op-doc-transfer` **PURGED 2026-09-02** (was seeded but never used — clones are `op-doc-order` with `transfer_no=EBELN-X`; 6 `sys_options` rows deleted, `OrderSystemSeeder.php` + `DocumentServiceProvider` + `Form.vue` + `PermissionHelpers` cleaned).

- **Order:** `doc_trans_order_created → transfer_sent → ready_for_shipment → approved/rejected` + `files_rejected`
- **File:** `doc_file_waiting → accepted | rejected → refreshed`
- **Serial:** `op-doc-order-serial` docs parented to items. Entities: `serial_no`, `production_date` (YYYY-MM-01), `quantity`, `unit`

## 7. Routing — 10 web + ~30 api
**Web** `routes/web.php:69`: `/` (admin login), `/tedarik` (tedarik login `tedarik-login`), `/register`, `/logout`, `/smscallback` (unified orange 2FA), `/auth/passwordreset/{code}`, `POST /auth/passchange`, `/coalpanel{any}`, `/tedarikpanel{any}`, `/order-file/{doc}`, `POST /export/offer`, `GET /export/{model}/{type?}`  
**API** `routes/api.php:69`: `POST auth/checkcode|sendmail|resend-code`, `POST /v1/auth/login/{type?}` (`admin`|`tedarik` → `session auth_panel`), `POST /v1/auth/register`, `POST /v1/auth/resetusercradentals` (PUBLIC!), `GET /v1/me`, `ANY /v1/document/{id?}`, `ANY /v1/transaction`, `POST /v1/temp-upload`, `POST /v1/table/{model}`, `POST /v1/export/malzeme-kabul|malzeme-cins-miktar-kabul`, `POST /v1/export/{model}`, `ANY /v1/users|persons`, `GET /v1/notifications`, `POST /v1/notificationlog/{id}/retrigger`, `GET/POST/DELETE /v1/roles/templates`, `GET /v1/roles/items`, `POST /v1/trans/set-status|cancel-offer|reopen-offer|set-file-status*|disable-document|orders/cancel`, `ANY /v1/dashboard/{type}/{period?}`, `GET /v1/getpermissions` (heartbeat)

## 8. Auth & Session
`AuthController.php:891` flow: `POST /v1/auth/login/{type?}` (`tedarik`|`admin` → `session auth_panel`, reCAPTCHA + `Cache login:attempts 5×15min` → `Auth::attempt` → `generateAndSendTwoFactorCode` → 6-digit to `storage/app/{token}-{personId}-login.txt` + Mail/SMS) → `GET /smscallback` (single orange card for both panels, `tedariklogin` 560×840 vs `coallogin` blue) → `POST auth/checkcode` (120s TTL, reads `session auth_panel` → `loginRoute = tedarik-login|login`) → `Auth::login` → `loadUserPermissionsToSession` → `clientPermInfo` → `createToken` → `ActiveSession` → `forceLogoutPerson` → SPA (`/tedarikpanel` or `/coalpanel`). `public/front/pages/tedariklogin/page.js` sets `localStorage token` → `/tedarikpanel`; `loginSms/page.js` unified.

Middleware `CheckPermissionVersion.php:91` on every protected request: if `force_logout` → 401, else if `permission_version` mismatch → `loadPermissionsToSession` + bump.

Frontend `stores/auth.js:43` heartbeat 30s + `pickle.js:105-154` 401 handlers (`permission_changed` → retry, `force_logout` → `/`).

## 9. Permission System
`sys_permission_catalogs` (`per-XX-YY` codes, 19 entries) → `sys_role_templates` (5 immutable roles in `storage/entities/coal_roles_templates.json` + custom) → `sys_notification_types` (`notif-*`) → `sys_role_template_audit`.

Per-person JSON array in EAV `op-doc-user-permission-form`. Runtime via `PermissionService::has()` → `DEV_ADMIN backdoor` → `session sper-{code}` → `file cache permissions.user.{id}` (30d, `microtime*1e6` version). Helpers: `checkPerm()`, `docPermCheck(typeKey, read|edit|status)` in `PermissionHelpers.php:215`. Frontend `GET /v1/getpermissions` drives menu/button visibility. `updateUserPermissions()` fans out role changes + `bumpUserPermissionVersion`.

## 10. Frontend Skeleton
`router/index.js:110` (routes under `/coalpanel` + `/tedarikpanel`, NO auth guard) → `layouts/App.vue:35` → `layouts/CoalPanel.vue:82` (admin Sidebar+Header) + `layouts/TedarikPanel.vue:386` (Tedarik card `f2f2f3` bg `22px 18px 18px 48px` root, frame `1360px` `12px` `overflow:visible`, sidebar `210px` `overflow:visible z10`, logo 82px + label 11.5px, menu `flex1 center 64px 12px -52` protruding 38px, bottom 64px same, PickleTable card-rows) → pages in `resources/js/pages/coalsystem/` (Orders/OList+OForm **SHARED** via `isTedarik` (`$route.path.startsWith('/tedarikpanel')`) — same file serves `/coalpanel/orders` + `/tedarikpanel/orders`, decision **keep shared 2026-09-02** (see `memory/05 §7`); detailed search `OList.vue:940` 3×3 grid hover `absolute top:52 z40` + `Filtreler` dropdown `teleport fixed z9999` `9` radios + `Şirkete Göre Arama` → `client-modal 720px` `modalClients` lazy `200` `hardFallback 8` + `flatpickr range` `tarih_araligi`, Module Switcher `Sidebar.vue:393`/`TedarikPanel.vue:64` Modüller modal `modules[]` → `/coalpanel`|`/tedarikpanel`, Documents/DList `groupBy:group_key`, Users/*, Roles/*, Logs/LList, NotificationLogs/NList, Notifications/NSettings, Dashboard.vue) + `resources/js/pages/tedarik/Dashboard.vue` (placeholder)

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
- `memory/05-order-system-state.md` — LIVE order system state (read after `00`)
- `memory/06-roadmap-next.md` — next steps + decision tree
