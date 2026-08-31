# Backend & Frontend Patterns — Reusable Pieces & How They Talk

## 1. Request Flow End-to-End

```
Browser                Laravel                          DB
-------                -------                          --
GET /coalpanel --→ web.php coalAuth closure (session type_key+2f_success)
  → coalapp.blade.php (SPA shell, CSRF meta, SYS_CODE hidden input)
  → app.js initApp() → GET /api/v1/getpermissions → PermissionService::has
  → mount → CoalPanel.vue (Sidebar+Header) → router child page
  → Page.mounted → pickle.js request → api.php middleware auth:sanctum+CheckPermissionVersion
  → DocumentController → DocumentServiceProvider → Model/raw SQL → postgres
  ← JSON ← ← ← pickle.js 401 handlers → Swal toast → retry or force_logout → pinia store → render
```

Global middleware (`bootstrap/app.php:19-27`): `ParsePutMultipart` (broken, fallback `parsePut()` in controllers) → `CspMiddleware` (except IS_TEST) → `trustProxies('*')`. CSRF disabled (`except ['*']`), statefulApi `*`, Sanctum token Bearer + session cookie both required.

---

## 2. Backend Patterns

### 2.1 Generic Document CRUD — Single Endpoint, Any Type
`panel/app/Http/Controllers/DocumentController.php:19-220` `ANY /v1/document/{id?}`

| Method | Frontend call | Backend does | Guard |
|--------|---------------|--------------|-------|
| `GET /v1/document/{qnid}` | `plib.request({url:'/api/v1/document/'+id, method:'GET'})` | `getFormData(qnid)` → `{document, formFormat}` | `docPermCheck(read)` + supplier `clientQnidList` for client + `offerOwnershipCheck` |
| `POST /v1/document` | `FormData {data: JSON.stringify({typeKey, dynamicF, removedData}), dynamicFile*: File|ref}` → POST | `registerContent(0, data, files)` → `Transactions created` → `sendOfferGiven` if offer | `docPermCheck(edit)` + `canResponse` for offers |
| `PUT /v1/document/{qnid}` | same FormData, method PUT | same `registerContent(qnid, ...)` → `clientPermInfo refresh` if client+supplier → `sendClientChanged` + `updatePersonClients` + `doc_trans_offer_revised` auto | same + offer edit state guard (`revision|created|draft` for suppliers) + cancelled 422 |
| `DELETE /v1/document/{qnid}` | DELETE | `removeContent(qnid)` → `status=0` (+ deactivate users if client) | `docPermCheck(edit)` — offers blocked (use cancel-offer) |

`registerContent` is atomic DB transaction + `UserLog` before/after. File keys `dynamicFile*` merged from `FormData` (multipart string refs re-merged in controller:92,154).

### 2.2 Persons CRUD — Same EAV, Separate Endpoint
`panel/app/Http/Controllers/PersonsController.php:429` `ANY /v1/persons|users/{id?}`

- `PersonsServiceProvider::setPerson` + `getPerson` — mirror of document engine but for `persons` table
- `upsertConnectionEntity` handles `userfacilitygroup|userclientgroup|userpermissiongroup`
- Post-commit: `PermissionService::refreshUserPermissionCache + bumpUserPermissionVersion + UserLog`

### 2.3 Status Machine — Controlled Writes
`POST /v1/trans/set-status` → `DocumentController::setStatus:260-295` → `DocumentServiceProvider::setStatus(id, opKey, note)` (`DocumentServiceProvider.php:712-781`)
- Resolves `op_key→sys_options.id`, creates `UserLog log-document-status-update` + `Transactions` (op_id 0)
- Offer special: if `document.status==0` (cancelled) → revive `status=1` + `logOfferReopened` (status write can revive cancelled offers, requires `per-05-02`/admin)

`POST /v1/trans/cancel-offer|reopen-offer` → dedicated endpoints with `required|uuid` validation + `per-08-02 + offerOwnershipCheck` + conditional `UPDATE status=0|1 WHERE status=1|0` (race-safe) + `UserLog` without before (timeline classification). (`DocumentController.php:302-367`, `DocumentServiceProvider.php:407-515`)

`POST /v1/trans/set-file-status|set-file-status-all|disable-document` → `documentFileStatus` (file transactions `op_id=1`) + `refreshAllUserPermissions` + `sendClientFileStatus` mail.

**Order System status (see `memory/05`):**
- Order guard in `setStatus` (`DocumentServiceProvider.php:754`): `created→transfer_sent`, `transfer_sent→approved/rejected`, `files_rejected→transfer_sent|approved|rejected`, terminal blocked.
- `documentFileStatus` now calls `syncOrderStatusFromFiles` → any rejected active file under an order (or its items) auto-flips the order to `doc_trans_order_files_rejected`; all accepted → back to `transfer_sent`.
- **Transfer send happens on the order-detail SAVE** (`PUT /v1/document/{qnid}`, payload `transfer_mode` + `selected_items`) → `DocumentServiceProvider::processOrderTransfer`: `at_once` sets order `transfer_sent`; `partial` clones `op-doc-order` `transfer_no=EBELN-X` + `moveOrderFilesToDocument` + `duplicateOrderItem` + `recordPartiallySent`, sets clone `transfer_sent`.
- `POST /v1/orders/cancel` → `DocumentServiceProvider::cancelOrder` soft `status=0` + terminal rejected transaction (reject & cancel whole order).

### Permission Map — `docPermCheck(typeKey, perm)`

`panel/app/Helpers/PermissionHelpers.php:215`:
```php
'op-doc-request' => ['read'=>'per-05-01', 'edit'=>'per-05-02', 'status'=>'per-05-02'],
'op-doc-client'  => ['read'=>'per-06-01', 'edit'=>'per-06-02'],
'op-doc-offer'   => ['read'=>'per-08-01', 'edit'=>'per-08-02', 'status'=>'per-05-02'],
'op-doc-user'    => ['read'=>'per-04-01', 'edit'=>'per-04-02'] // via checkPerm directly
// Order System: reuse coal perms (NOT split)
'op-doc-order'/'op-doc-order-item'/'op-doc-transfer' => ['read'=>'per-05-01', 'edit'=>'per-05-02', 'status'=>'per-05-02'],
// Dökümanlar (file approve) → per-07-02 via set-file-status
```

### 2.4 Listing & Export — TableList Pattern
`panel/app/Http/Controllers/SystemController.php:100` `POST /v1/table/{model}` → `(new $model)->tableList(params)` → raw SQL with filters.

`Documents::tableList` (`app/Models/Documents.php:398`) is the monster: tenant `grp_code` filter, supplier `clientQnidList` injection, `her_ikisi` OR, `main_attr JSON` lateral, `showExpired|today-ended|is-rodevans|request_id` etc. `Users/Persons/Document_files/UserLog/NotificationLog` each have simpler `tableList`.

Export mirrors list: `POST /v1/export/{model}/{type?}` → `ExportController@index` → `ExportService::exportExcel` (PhpSpreadsheet streamed Xlsx, NO perm check) or `POST /export/offer` → dompdf `exports/offer.blade.php` + attachments ZIP.

### 2.5 Auth & Session — Deep
`AuthController.php:873`
- Login: `POST /v1/auth/login/{type?}` (reCAPTCHA rule + `Cache login:attempts 5 → locked 15min` + `Auth::attempt` + `generateAndSendTwoFactorCode` → `storage/app/{token}-{personId}-login.txt` plaintext 6-digit + `MailService+SmsService` to `contmail*/contphone*`)
- Verify: `POST auth/checkcode` (120s TTL, one-time delete via `Storage::exists + File::lastModified`, `Auth::login`, `loadUserPermissionsToSession`, `clientPermInfo`, `createToken`, `ActiveSession` create, `forceLogoutPerson` sets `force_logout` on others)
- Reset: `POST auth/sendmail` (throttle 4/min → `{key}-refreshmail.txt` → `/auth/passwordreset/{code}` → 2FA SMS → `POST /auth/passchange` bcrypt + `needs_refresh=0`) and public `POST /v1/auth/resetusercradentals/{id}` (dangerous)
- Heartbeat: `GET /v1/getpermissions` → returns `{permissions[], currentStatus{canProceed,canResponse,clientQnidList,rejectedFiles}, typeKey, personId, userName}` plus permission version bump detection
- Session: `active_sessions` (token_id, permission_version, force_logout+reason, current_status mirror). `CheckPermissionVersion` middleware compares `session permission_version` vs cache, refreshes if mismatch.

### 2.6 File & Crypto
`DocumentHelpers.php:777`, `EncryptionProvider.php:119`
- `uploadFile`: 42MB, whitelist `jpg/png/jpeg/pdf/xls/xlsx`, `time()+random+slugify` → `storage/app/public/documents/`, encrypt filename `encrypt(name)` (AES-128-CBC PBKDF2, base64url `salt:iv:ciphertext`), `Document_files(description=encrypted, status=1, qnid, relation)`
- `GET /order-file/{qnid|encrypted}` → `decryptFile`: qnid? lookup `document_files.qnid : decrypt(blob)` → serve with mime (IDOR — no authz)
- `POST /v1/temp-upload` → `tempUploadFile` → `{reference_id, reference}` for staged upload before doc exists
- Versioning: same `entity_tag` new file → old `status=0, replaced_id=newId`. **Both paths version correctly now:** `addFileToDb` (traditional) and `finalizeTempFile` (temp upload) both deactivate old, create new record, chain, copy entities, log `doc_file_refreshed`. See `panel/docs/file-replacement-fix.md`.

### 2.7 Notifications & Jobs
`EmailServiceProvider.php:112` thin wrapper: `sendregisterMails|sendapproveMails|sendresetMail|sendOfferGiven|sendOfferStatus|sendClientChanged|sendClientFileStatus` → dispatches `SendNotificationMailJob|SendResetMailJob` (ShouldQueue `database`) → `MailService::sendMail` (relay override `MailService:52-84`, TLS verify off, `NotificationLog pending→sent|error`) + optionally `SmsService::sendSms` → `SMSGatewayWS`. Recipient resolved via `PersonsServiceProvider::getNotificationUsers(opKey)` (JSON-contains `notif-*`).

Panel notifications: `GET /v1/notifications` → `SystemController::getNotifications` → `ReportServiceProvider` per `notif-00..03` (awaiting users/files/new offers/revised offers) → `navigationStore.notifications` → Header bell + dashboard cards. Retry via `notification:retry` command + `RetryNotificationSendJob`.

---

## 3. Frontend Patterns

### 3.1 The Central Client — `pickle.js:824`
```js
const plib = new Plib();
await plib.request({ url:'/api/v1/document', method:'POST', data:{...} }, file, formData);
// or with FormData:
const env=new FormData(); env.append('data',JSON.stringify(formData));
for(let k in formData.files) env.append(k, formData.files[k].file);
await plib.request({url, method}, null, env);
```
`request(rqs, file, formData)`:
- Headers: `X-CSRF-TOKEN` from `meta[name=csrf-token]` + `Authorization: Bearer localStorage.token` + `X-Requested-With`
- Body: `DELETE→URLSearchParams`, `PUT|POST→FormData` (or provided FormData)
- 401 intercept: `permission_changed` → Swal toast → `GET /api/v1/getpermissions` → retry once; `force_logout` → clear token → Swal → `window.location='/'`
- Non-JSON response → Swal HTML modal (unescaped — XSS surface)
- Helpers: `checkForm(selector)` (validates `.form-item` visible+required, handles multiselect/checkbox/lang JSON), `clearElements`, `validatePassword`, `fileInfo`, `toast(Swal,type,msg)`, `formatMoney`, `compressImage`, `crypFunc` base64, `getNumberOfDays` etc.

### 3.2 Pinia Stores — 5
| Store | File | State | Key actions / endpoints |
|-------|------|-------|------------------------|
| `auth` | `stores/auth.js:43` | `permissions[], currentStatus{canProceed,canResponse,clientQnidList,rejectedFiles}, typeKey, personId, userName` | `getPermissions→GET /v1/getpermissions`, `startHeartbeat` 30s |
| `permissiondata` | `permissiondata.js:67` | `items (catalog tree), roleTemplates` | `fetchRoleTemplates→GET /v1/roles/templates`, `fetchRoleItems→GET /v1/roles/items` |
| `navigation` | `navigation.js:82` | `breadcrumps, breadbuttons, routeParams, notifications, sys_code (DOM input)` | `getNotifications→GET /v1/notifications`, `setBread/setButtons` + sessionStorage persist |
| `events` | `events.js:76` | `tasks, events` | `setTaskData→GET /v1/dashboard/getOngoingTasks`, `setEventData→GET /v1/dashboard/monthlyEvents` (legacy dead) |
| `formdata` | `formdata.js:16` | `formData, addional, rawData` | `setData(data, addional), getData()` — no API, list→form carrier |

### 3.3 Router — `router/index.js:103` (17 routes, no guard)
All under `/coalpanel`, parent `layouts/CoalPanel.vue`. `beforeEach/afterEach` only closes `KTDrawer`, no auth. Server `web.php` `$coalAuth` closure is the real gate. `createWebHistory()` → needs `public/index.php` fallback for SPA.

Copy pattern for new entity:
```js
{ path:'/coalpanel/leave', name:'LList', component:()=>import('@/pages/coalsystem/Leave/LList.vue') },
{ path:'/coalpanel/leave/form/:id?', name:'LForm', component:()=>import('@/pages/coalsystem/Leave/LForm.vue') },
```

### 3.4 Page Template — List & Form Pair

**List (`RList.vue` pattern):** PickleTable wrapper → `POST /v1/table/documents` with `initialFilter [{key:'form-type',type:'=',value:'op-doc-X-form'}, {key:'type',type:'=',value:'op-doc-X'}]` + optional `is-rodevans`, `showExpired` etc. Row click → `router.push {name:'RForm', params:{id: row.qnid}}`. Status pill rendered from `status.split('**')` or JSON history.

**Form (`RForm.vue:169` pattern):**
```js
mounted(){
  checkData = async()=> this.id ? await plib.request({url:'/api/v1/document/'+this.id, method:'GET'}) : {success:false};
  checkData().then(r=>{ formDataStore.setData(r.data.formFormat); formDataStore.rawData=r.data; this.loadForm=true });
}
methods:{
  async submitForm(formData){
    formData.typeKey='op-doc-X';
    const chk=plib.checkForm('.form-item'); if(!chk.valid) return;
    const env=new FormData(); env.append('data',JSON.stringify(formData));
    for(let k in formData.files) env.append(k, formData.files[k].file||JSON.stringify(formData.files[k].reference));
    const rsp=await plib.request({url:'/api/v1/document'+(this.id?'/'+this.id:''), method:this.id?'PUT':'POST'}, null, env);
    plib.toast(Swal, rsp.success?'success':'error', rsp.msg, ()=>router.push({name:'LList'}));
  }
}
```
Template:
```vue
<RSummary v-if="supplierView" :entities="..." :document="..." :addOfferCallback="addOffer" />
<Form v-else formtypes="op-doc-X-form" :savecallback="submitForm" />
<OfferRequestTable v-if="id" :requestId="id" />
<RequestLogTimeline :requestQnid="id" />
```
Supplier read-only vs admin edit branch is common (see `RForm.vue:150`).

### 3.5 Components — Presentational + Data-Fetching Split
- **Layout:** `CoalPanel.vue` (Sidebar+Header+Simplebar + `localStorage sa-theme`), `App.vue` (pure router-view)
- **Coalparts:** `Form.vue` (engine), `Sidebar.vue` (menu, `per-*` gated, `toggleMini`, `markActiveRoute` DOM), `Header.vue` (breadcrumb via `navigationStore`, notifications bell → Swal modal via `getNotifications`), `RSummary/OfferSummary` (read-only hero + spec grid + calory table + status badge, props-driven, no API), `RequestLogTimeline/OfferLogTimeline` (fetches `POST /v1/table/userlog` `filter doc_qnid`, diffs `before/after` entities), `AppFab.vue` (floating bar/wheel, `fabtype=bar|leftIcon`)
- **Dashboard:** `pages/coalsystem/Dashboard.vue` picks `Admin.vue` (8 widgets) or `Client.vue` (5) or `Default.vue` by `typeKey`; widgets each call `/api/v1/dashboard/{topstats|monthlyoffers|monthlydistribution|importantinfo}` + 3 duplicate `mergeNotifications` functions + Chart.js/FullCalendar
- **Offer:** `OfferRequestTable` (table of offers for a request), `OfferTable` (dead, unused), `OfferLogTimeline`, `OfferSummary`

### 3.6 Styling / Build
`vite.config.js:49` — entry `resources/js/app.js + coal-swal.js`, 4 coaltheme CSS, alias `@→resources/js`, `sourcemap:true`. `tailwind.config.js` + `postcss`. Build: `npm run dev|build`. Auth pages use separate `@vite(['resources/js/coal-swal.js', 'resources/views/auth/...'])` per blade.

---

## 4. Integration Points — External Systems

| System | Config | Flow |
|--------|--------|------|
| **SMS İletişim Makinesi** | `.env ILETISIM_*`, `config/services.php:iletisimmakinesi`, `SmsService.php:409` | `getToken (cached 24min bug) → POST SMSGatewayWS/sendSMS` (phoneNumbers JSON, templateText, originatorId) |
| **Mail** | `.env MAIL_* + MAIL_USE_RELAY`, `MailService.php:395` | `renderHtmlMessage → emails/layout.blade.php` (logo by SYS_CODE) → SMTP or relay:25 (verify_peer false) → `NotificationLog` |
| **TCMB FX** | `Classes/Currencies/TCMB.php:70` | `GET https://www.tcmb.gov.tr/kurlar/today.xml` → parse `BanknoteSelling` → `Currencies::truncate()+create` per `SYS_CUR_INFO` |
| **reCAPTCHA v2** | `RECAPTCHA_*`, `Rules/Recaptcha.php:42` | `POST google.com/recaptcha/api/siteverify` (test key `6LeIx...` in env) |
| **PDF/Excel** | `ExportService.php:117` | `PhpSpreadsheet` Xlsx streamed; `dompdf+mpdf` for `exports/offer.blade.php` + ZIP decrypt attachments |

---

## 5. Common Pitfalls & Fixes For New Apps

- **PUT multipart broken on Apache** → always handle fallback `parsePut()` (see `DocumentController:139-144`). Frontend `FormData` with `method PUT` may be rewritten to `POST` with `_method=PUT` by vite proxy?
- **`entity_tag` colon/special char** → `strip_tags` on save, no sanitization on read → XSS in Swal HTML modals if title contains `<script>`
- **Hidden field validation skip** → `pickle.js:checkForm` skips `getClientRects().length==0` fields — don't mark hidden fields `required`
- **`target_type` missing** → `grp_code` stays null → row invisible to tenant filtering → always include `target_type` (even hidden) for tenant-aware docs
- **`her_ikisi` switch** — documents with `her_ikisi=1` bypass `grp_code` filter (shown to both tenants), handled in `Documents::tableList`
- **`permission_version` cache store `file`** → deleting `permissions.user.*` keys doesn't affect DB cache store, `refreshAllUserPermissions` is buggy — after role changes, users must relogin if heartbeat fails
- **Sys_code DOM read** → `Header/Sidebar/Dashboard` read `input[name=SYS_CODE].value` — if blade snippet removed, they crash. Inject via `coalapp.blade.php` hidden input.
- **`$key` clobbering in DocumentController PUT** → `foreach ($data as $key => $value)` overwrites `$key` (set from `getFormData` as `op-doc-order`). Use `$fkey` as loop variable. Fixes transfer/offer/client logic after the loop. (`DocumentController.php:154`)

