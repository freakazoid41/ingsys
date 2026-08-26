# 03 — İlişki Haritası

> İstek → kod yolu referansı. "Bu ekran/endpoint hangi dosyalara dokunuyor?" sorusunun cevabı.

## 1. Route → Controller → Servis haritası

### Web (routes/web.php)

| Route | Middleware | Hedef | Dokundukları |
|---|---|---|---|
| `GET /` | — | `AuthController@coallogin` | `auth/coallogin.blade.php` + `/front/pages/coallogin/page.js` |
| `GET /register` | — | `AuthController@register` | `auth/register.blade.php` |
| `GET /logout` | — | `AuthController@logout` | UserLog(log-logout), session flush |
| `GET /smscallback` | — | `AuthController@loginSms` | `auth/loginSms.blade.php` |
| `GET /auth/passwordreset/{code}` | — | `AuthController@passwordReset` | storage txt key kontrolü → 2FA |
| `POST /auth/passchange` | — | `AuthController@passChange` | User.password, needs_refresh=0 |
| `GET /coalpanel/{any?}` | auth:sanctum + CheckPermissionVersion | closure `$coalAuth` | session `type_key`+`2f_success` → `coalapp.blade.php` (SPA) |
| `GET /order-file/{doc}` | auth:sanctum + … | closure → `decryptFile()` | EncryptionProvider, storage/app/public/documents |
| `POST /export/offer` | auth:sanctum + … | `ExportController@offerPdf` | dompdf `exports/offer.blade.php` + ZIP |
| `GET /export/{model}/{type?}` | auth:sanctum + … | `ExportController@index` | ExportService → PhpSpreadsheet |
| `GET /setapartment/{x}`, `GET /closeapartment` | auth:sanctum + … | ⚠ metot YOK (B1) | — |

### API (routes/api.php)

| Route | Hedef | Servis/Model zinciri |
|---|---|---|
| `POST /auth/checkcode` | `AuthController@checkCode` | Storage txt → Auth::login → PermissionService → ActiveSession → Sanctum token |
| `POST /auth/sendmail` · `resend-code` (throttle 4/dk) | `AuthController@sendMail/resendCode` | MailService, SmsService |
| `POST /v1/auth/login/{type?}` | `AuthController@loginUser` | Recaptcha rule → Cache kilit → PersonsServiceProvider.getPerson → 2FA |
| `POST /v1/auth/register` | `AuthController@registerUser` | PersonsServiceProvider.setPerson → EmailServiceProvider.sendregisterMails |
| `POST /v1/auth/resetusercradentals/{id}` | `PersonsController@resetUserCradentals` | ⚠ public route (A3) → SendResetMailJob |
| `GET /v1/me` (auth) | `Api/V1/User/MeController` | UserResource |
| `ANY /v1/document/{id?}` | `DocumentController@index` | docPermCheck → DocumentServiceProvider.getFormData/registerContent/removeContent → EmailServiceProvider (teklif/müşteri mailleri) |
| `ANY /v1/transaction/{id?}` | `DocumentController@transaction` | ⚠ PUT kırık (B4); DELETE → removeTransaction |
| `GET /v1/get-apartments` · `POST /v1/set-apartments` | ⚠ metot YOK (B1) | — |
| `POST /v1/admin/refresh-perms/{id}` | ⚠ metot YOK (B1) | — |
| `POST /v1/table/{model}` | `SystemController@table` | `(new $model)->tableList()` — whitelist'siz dinamik model |
| `POST /v1/export/{model}/{type?}` | `ExportController@index` | ExportService.exportExcel |
| `ANY /v1/users/{id?}` · `ANY /v1/persons/{id?}` | `PersonsController@uindex/index` | PersonsServiceProvider.setPerson/getPerson/removeContent |
| `GET /v1/notifications` | `SystemController@getNotifications` | ReportServiceProvider |
| `POST /v1/notificationlog/{id}/retrigger` | `SystemController@retriggerNotification` | NotificationLog → MailService/SmsService |
| `GET /v1/notification-users` · `POST /v1/set-notification-groups` · `GET /v1/notification/groups` | `PersonsController` | PersonsServiceProvider.getNotificationUsers/updateUserNotificationGroups |
| `GET/POST/DELETE /v1/roles/templates[/{id}]` · `GET /v1/roles/items` (×2 tanım) | `PersonsController@rolesTemplate/rolesItems` | RoleTemplateService → SysRoleTemplate/SysPermissionCatalog + audit |
| `GET /v1/trans/prepare-payment` · `POST /v1/trans/set-payment` | ⚠ metot YOK (B1) | — |
| `POST /v1/trans/set-status` | `DocumentController@setStatus` | docPermCheck(status) → DocumentServiceProvider.setStatus → sendOfferStatus |
| `POST /v1/trans/set-file-status[-all]` | `DocumentController@setFileStatus(All)` | per-07-02 → documentFileStatus → refreshAllUserPermissions (A7!) → sendClientFileStatus |
| `ANY /v1/dashboard/{type}/{period?}` | `ReportController@dashboard` | ReportServiceProvider.dashboardInfo: topstats/monthlyoffers/monthlydistribution/importantinfo (⚠ clienttopstatus B3) |
| `ANY /v1/setbackground` | `PersonsController@changeBackground` | User.bg_image |
| `GET /v1/getpermissions` | `AuthController@getPermissions` | PermissionService.ensureSessionFreshness + session perms |

Global middleware zinciri: `ParsePutMultipart` (⚠ A8, kırık) → `CspMiddleware` → trustProxies(*); CSRF tüm route'larda kapalı (A6).

## 2. Frontend ekran → API haritası

| Ekran (route) | Dosya | API çağrıları | İzin |
|---|---|---|---|
| Dashboard `/coalpanel` | `pages/coalsystem/Dashboard.vue` → Admin/Client/Default | `/v1/dashboard/topstats, monthlyoffers, monthlydistribution, importantinfo`, `/v1/notifications` | type_key'e göre |
| Talep listesi `/coalpanel/request` | `Request/RList.vue` | `/v1/table/documents` (type=op-doc-request), `/v1/trans/set-status` | per-05-01/02 |
| Talep formu `/coalpanel/request/form/:id?` | `Request/RForm.vue` + RSummary + OfferRequestTable + RequestLogTimeline | `GET/PUT /v1/document/:id`, `POST /v1/document`, `/v1/trans/set-status`, `/v1/table/userlog` | per-05-02; tedarikçi teklif verir |
| Teklif listesi `/coalpanel/offer` | `Offer/OList.vue` | `/v1/table/documents` (op-doc-offer), `/v1/trans/set-status`, DELETE `/v1/document/:id` | per-08-01/02, per-05-02 |
| Teklif formu `/coalpanel/offer/form/:id?` | `Offer/OForm.vue` + OfferSummary + OfferLogTimeline | `GET/PUT /v1/document/:id`, `/export/offer` (PDF) | tedarikçi kendi teklifi |
| Müşteri listesi `/coalpanel/client` | `Client/CList.vue` | `/v1/table/documents` (op-doc-client) | per-06-01 |
| Müşteri formu `/coalpanel/client/form/:id?` | `Client/CForm.vue` | `GET/PUT /v1/document/:id`, `/v1/trans/set-file-status(-all)`, `POST /v1/auth/register` | per-06-02; tedarikçi kendi firması (clientQnidList) |
| Kullanıcılar `/coalpanel/users*` | `Users/UList.vue`, `UForm.vue` | `ANY /v1/users/:id`, `/v1/persons/:id`, resetusercradentals | per-04-* |
| Roller `/coalpanel/roles` | `Roles/Roles.vue` | `/v1/roles/templates`, `/v1/roles/items` | per-00-* |
| Bildirim ayarları | `Notifications/NSettings.vue` | `/v1/notification-users`, `/v1/set-notification-groups`, `/v1/notification/groups` | admin |
| Sistem logları `/coalpanel/sistem-loglari` | `Logs/LList.vue` | `/v1/table/userlog` | per-04-04 |
| Bildirim logları `/coalpanel/notifikasyon-loglari` | `NotificationLogs/NList.vue` | `/v1/table/notificationlog`, `/v1/notificationlog/:id/retrigger` | admin |
| Belgeler `/coalpanel/documents` | `Documents/DList.vue` | `/v1/table/documents`, `/v1/trans/set-file-status` | per-07-01/02 |

Ortak: tüm istekler `lib/pickle.js` (Plib) üzerinden; 401 `permission_changed` →
sessionStorage tazeleyip retry; `force_logout` → giriş sayfasına.
Heartbeat: 30 sn'de bir `GET /v1/getpermissions` (`stores/auth.js`).

## 3. Veri yazma zinciri (belge kaydetme)

```
Form.vue (dynamicF toplar)
 → POST/PUT /api/v1/document/{id?}            (FormData: data=JSON + dynamicFile*)
 → DocumentController@index                    (docPermCheck + reseller istisnaları)
 → DocumentServiceProvider::registerContent    (DB transaction)
     ├─ documents (main_* alanları, type_id ← sys_options op_key)
     ├─ removedData → sys_con_entities sil / document_files pasife al
     ├─ dynamicF her bölüm:
     │    ├─ sys_con_ops (main_id, type_id, sub_type_id)
     │    └─ sys_con_entities (entity_tag=alan, entity_value=değer)
     ├─ dynamicFile* → addFileToDb → uploadFile (42MB, jpg/png/jpeg/pdf/xls/xlsx)
     │    → storage/app/public/documents/{şifreli-ad}
     │    → document_files.description = EncryptionProvider.encrypt(ad)
     │    → transactions(doc_file_waiting) + user_logs(log-file-added)
     ├─ op-doc-request/offer → req_no sayacı, target_type → documents.grp_code
     └─ user_logs (before/after JSON, log-tender-update)
 → (teklif/müşteri ise) EmailServiceProvider → Job → notification_logs
```

## 4. Durum değiştirme zinciri

```
UI buton (data-key=doc_trans_*)
 → POST /v1/trans/set-status {id, op_key, note}
 → DocumentController@setStatus (docPermCheck 'status' + teklif-gönderme istisnası)
 → DocumentServiceProvider::setStatus
     ├─ user_logs (log-document-status-update)
     └─ transactions (target_id=belge, type_id←sys_options, note)
 → belge teklifse EmailServiceProvider::sendOfferStatus → alıcı = bildirim grubu + ilgili müşteri
```

Dosya onay/red: `/v1/trans/set-file-status(-all)` → `documentFileStatus`
(transactions op_id=1) → `refreshAllUserPermissions()` (⚠ A7 kırık invalidation)
→ `sendClientFileStatus` maili.

## 5. Bildirim zinciri

```
Olay (controller)
 → EmailServiceProvider::send*  (alıcıları PersonsServiceProvider::getNotificationUsers ile çözer)
 → Job (SendNotificationMailJob / SendInfoMailJob / SendResetMailJob — queue 'default', database driver)
 → MailService::sendMail  (SMTP veya relay; MAIL_USE_RELAY)
      ve/veya SmsService::sendSms (İletişim Makinesi: token → SMSGatewayWS)
 → NotificationLog: pending → sent/error (+detail JSON)
 → Hata: php artisan notification:retry --queue → RetryNotificationSendJob
 → Panel: NList.vue retrigger → POST /v1/notificationlog/{id}/retrigger
```

## 6. Giriş zinciri (özet hat)

```
coallogin.blade → POST /v1/auth/login → loginUser (kilit+reCAPTCHA)
 → storage txt'ye kod + kontaklara SMS/mail
 → loginSms.blade → POST /auth/checkcode → checkCode (120sn, tek kullanımlık)
 → Auth::login → loadUserPermissionsToSession → createToken
 → ActiveSession.create + eski oturumlara force_logout (A7: log kısmı kırık)
 → /coalpanel → coalapp.blade → Vue app.js
     → GET /v1/getpermissions → store'lar → 30sn heartbeat
 → her istek: CheckPermissionVersion (force_logout? version eski mi?)
```

## 7. Modül → mapping dosyası çapraz referansı

| Konu | Detay |
|---|---|
| Modeller ve ER | `mapping/10-models.md`, `mapping/14-config-database.md` |
| Her route'un metot karşılığı | `mapping/11-http.md` |
| Mail/SMS/izin/export servis detayı | `mapping/12-services.md` |
| Komutlar, job'lar, schedule | `mapping/13-infra.md` |
| Router/store/pickle.js | `mapping/15-frontend-core.md` |
| Sayfa-işlev listesi | `mapping/16-frontend-pages.md` |
| Component detayları | `mapping/17-frontend-components.md` |
| Blade/mail şablonları/i18n | `mapping/18-views-i18n-mail.md` |
