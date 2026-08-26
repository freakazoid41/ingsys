# HTTP Katmanı — Dosya Haritası
> Kapsam: 13 dosya (app/Http) + 2 route dosyası · Tamamı okundu.

Route eşleştirme göstergeleri: ✓ = route karşılığı mevcut · ✗ = route var ama metot YOK (kırık route) · ⚠ = dikkat gerektiren eşleşme.

---

## `app/Http/Controllers/AuthController.php` (~873 satır)
- **Amaç:** Tüm kimlik doğrulama yaşam döngüsü: login sayfası, kayıt, 2FA (SMS/e-posta kodu), şifre sıfırlama, çıkış, izin sorgulama.
- **Semboller (public metotlar ve route eşleşmeleri):**
  - `login()` — route YOK (yorumda bırakılmış); `login` view döner. Ölü sayılabilir.
  - `coallogin()` — ✓ `GET /` (web.php, name:login). `auth.coallogin` view + `/front/pages/coallogin/page.js`.
  - `loginSms()` — ✓ `GET /smscallback` (name:login-sms). SMS kod giriş ekranı view'ı.
  - `register()` — ✓ `GET /register` (name:register). Kayıt view'ı.
  - `registerUser(Request)` — ✓ `POST /v1/auth/register` (api.php, name:register-user). İki mod: klasik form (reCAPTCHA zorunlu, `App\Rules\Recaptcha`) ve AJAX (`X-Requested-With: XMLHttpRequest`, `cli_id` zorunlu). `PersonsServiceProvider::setPerson(0, ...)` ile `user_status=-1` (onay bekliyor), `type_key=op-pert-reseller` kişi oluşturur; `EmailServiceProvider::sendregisterMails()` adminlere bilgi maili atar. E-posta benzersizlik kontrolü `Auth::attempt` ile yapılıyor (yanlış desen — login denemesi yapar).
  - `loginUser(Request)` — ✓ `POST /v1/auth/login/{type?}` (name:login-user; throttle yorumda = KAPALI). Akış: (1) session flush + reCAPTCHA/email/şifre validasyonu; (2) `User::where(email, status=1)`; (3) Cache tabanlı kilitleme — `login:attempts:{email}` / `login:locked:{email}`, 5 denemede 15 dk kilit, kilit/ denemeler `UserLog`'a yazılır (`log-login-failed`, `log-lock`); (4) `Auth::attempt` + `PersonsServiceProvider::getPerson`; (5) başarıda session'a `type_key`, `person_id`, `email`, `ptitle` yazar; (6) `generateAndSendTwoFactorCode()` ile kod üretip `login-sms` route'una yönlendirir (localhost:8000'de `debug_code` session'a basılır).
  - `checkCode(Request)` — ✓ `POST auth/checkcode`. 2FA doğrulama: `code_N` inputlarından kodu birleştirir; `*` karakteri = kod geçersiz. Kod, local diskte `{token}-{person_id}-login.txt` dosyasından okunur; 120 sn'den eski kod reddedilir; dosya okunduktan sonra silinir. Doğruysa: `Auth::login`, `loadUserPermissionsToSession()` helper, session'a `person_id/user_id(qnid)/type_key/2f_success/currentStatus` (`PersonsServiceProvider::clientPermInfo`), ilk-giriş tespiti (`UserLog` sayısı 0 veya `needs_refresh=1` veya `{token}-refreshmailsms.txt` varsa), `DEV_ADMIN` backdoor'u (firstLogin=false), `UserLog` (`log-login`), eski `ActiveSession` force_logout kayıtlarını temizleme, `PermissionService::forceLogoutPerson()` ile TEK OTURUM zorlaması, Sanctum `createToken("API TOKEN")`, `ActiveSession::create` (token_id, ip, user_agent, permission_version). Token `sms-success`/`sms-firstlogin` flash'ı ile login sayfasına döner. Yanlış kodda `log-login-code-failed` — **girilen ve beklenen kod düz metin loglanır**.
  - `generateAndSendTwoFactorCode(User, $person, $token, $isResend)` (protected) — 6 haneli kod (`DEV_ADMIN` ise sabit `111111`), local diske yazar, session'a `login_person/token/login_type` koyar. Kişinin `contacts` JSON'ındaki `contmail*` → `MailService::sendMail`, `contphone*` → `SmsService::sendSms`. Hiçbiri başarısızsa kullanıcı e-postasına yedek mail; o da olmazsa ve DEV_ADMIN ise hardcoded telefona (5438826976) SMS. `debug_code` sadece localhost:8000.
  - `resendCode(Request)` — ✓ `POST auth/resend-code` (throttle:4,1). Maks 2 tekrar, 60 sn bekleme; session'daki `login_person`'dan user/person bulup `generateAndSendTwoFactorCode(..., true)` çağırır.
  - `sendMail(Request)` — ✓ `POST auth/sendmail` (throttle:4,1). Şifre sıfırlama maili: `bin2hex(random_bytes(10))` anahtar üretir, `{key}-refreshmail.txt` dosyasına e-postayı yazar, `/auth/passwordreset/{key}` linkli mail atar (`MailService::renderHtmlMessage` + CTA). Kullanıcı yoksa bile aynı "gönderildi" mesajı (user-enumeration koruması niyetiyle ama mesaj farklı: başarı/başarısız ayrımı hâlâ sızıyor).
  - `passwordReset(Request, $code)` — ✓ `GET /auth/passwordreset/{code}` (web). Loginli kullanıcıya direkt view; loginsizse `{code}-refreshmail.txt`'den e-postayı okur, dosyayı siler, `{code}-refreshmailsms.txt` bayrak dosyası yazar ve `generateAndSendTwoFactorCode` ile SMS akışına sokar → `login-sms`'e yönlendirir. (İçte önce `rand()` ile kod dosyası yazıp sonra generateAndSendTwoFactorCode'un üzerine yazmasına izin veriyor — ölü/çift yazım.)
  - `passChange(Request)` — ✓ `POST /auth/passchange`. `auth-forgot` session'ı + login şartıyla şifreyi `Hash::make` ile günceller, `needs_refresh=0`, logout + session flush.
  - `checkMail(Request)` — route YOK (tanımsız/ölü). E-posta müsaitlik JSON'ı.
  - `logout(Request)` — ✓ `GET /logout` (name:logout). `UserLog` (`log-logout`) + session flush + login'e redirect. (Sanctum token silinmiyor.)
  - `getPermissions(Request)` — ✓ `GET /v1/getpermissions`. `PermissionService::ensureSessionFreshness`, `currentStatus.canResponse==false` ise `clientPermInfo` ile yeniler. `PermissionService::has(user,'all')` true ise **hardcoded tam yetki listesi** (per-00…per-08-02) döner — gizli admin backdoor'u; değilse `session('perms')`.
- **İlişkiler:** `PersonsServiceProvider`, `EmailServiceProvider`, `MailService`, `SmsService`, `PermissionService`; modeller `User`, `Persons`, `Sys_options`, `UserLog`, `ActiveSession`; `App\Rules\Recaptcha`; helper `loadUserPermissionsToSession()`; view'lar `auth.coallogin`, `auth.loginSms`, `auth.register`, `auth.passwordReset`.
- **Bulgular:**
  - 🔴 `DEV_ADMIN` backdoor'u: 2FA kodu `111111` sabit, firstLogin bypass, yedek SMS hardcoded numaraya.
  - 🔴 2FA kodları düz metin dosyada (`storage/app/*.txt`); yanlış denemede beklenen kod `UserLog`'a yazılıyor.
  - 🟠 `loginUser` throttle yorum satırı (sadece cache-kilit var); `resetusercradentals` route'u auth grubu DIŞINDA (bkz. PersonsController).
  - 🟠 `getPermissions`'da hardcoded süper-yetki listesi.
  - 🟡 `registerUser`'da benzersizlik kontrolü için `Auth::attempt` kullanımı hatalı desen; `passwordReset` içinde gereksiz çift kod dosyası yazımı; localhost debug kodu session flash ile istemciye sızıyor.

## `app/Http/Controllers/Controller.php` (~8 satır)
- **Amaç:** Boş abstract base controller.
- **Semboller:** `abstract class Controller`.
- **Bulgular:** —

## `app/Http/Controllers/DocumentController.php` (~347 satır)
- **Amaç:** Belge (cari/teklif/talep) CRUD + transaction + durum/dosya-durumu yönetimi; teklif yaşam döngüsü e-postaları.
- **Semboller:**
  - `index(Request)` — ✓ `ANY /v1/document/{id?}`. Metot dağıtıcı:
    - **Yetki:** `typeKey` POST'ta body'den, diğerinde `DocumentServiceProvider::getFormData(id)`'den alınır; `docPermCheck($key, read|edit)` helper'ı. İstisna: `op-doc-client` + reseller + kendi `clientQnidList`'indeyse GET/PUT serbest. `op-doc-offer` + `currentStatus.canResponse==false` → 403 (onaylanmamış tedarikçi teklif veremez).
    - **GET** → `getFormData(id)` form+document datası.
    - **POST** → `registerContent(0, data, files)`; teklifse ve oluştuysa `EmailServiceProvider::sendOfferGiven` (yetkili kullanıcılara bilgi maili).
    - **PUT** → reseller teklifleri sadece `doc_trans_offer_revision|doc_trans_created|doc_trans_offer_draft` durumlarında düzenleyebilir (değilse 403). `ParsePutMultipart` başarısızsa fallback: `parsePut()` helper + `$_FILES`. `registerContent(id, ...)`. Teklif son durumu `doc_trans_offer_revision` ise otomatik `setStatus(doc_trans_offer_revised)` + revizyon maili. `op-doc-client` güncellemesinde: reseller ise `currentStatus` session'ı yenilenir; `sendClientChanged` maili + `updatePersonClients` (kişi-cari bağları güncellenir).
    - **DELETE** → `removeContent(id)`.
  - `transaction(Request)` — ✓ `ANY /v1/transaction/{id?}`. Admin değilse GET dışı 403. Sadece DELETE aktif (`removeTransaction(id)`); PUT case'i yorumda bırakılmış — **PUT çağrılırsa `$response` tanımsız** (bug).
  - `setStatus(Request)` — ✓ `POST /v1/trans/set-status`. `id`+`op_key` validate; `docPermCheck($key,'status')`; reseller teklifi `doc_trans_offer_sended`'a özel izin istisnası. `DocumentServiceProvider::setStatus(id, op_key, note)`; teklifse `sendOfferStatus` maili.
  - `setFileStatus(Request)` — ✓ `POST /v1/trans/set-file-status`. `per-07-02` izni (`PermissionService::has`). `documentFileStatus(id, op_key, note)`; başarıda `refreshAllUserPermissions()` helper + bağlı cari kontaklarına `sendClientFileStatus` maili.
  - `setFileStatusAll(Request)` — ✓ `POST /v1/trans/set-file-status-all`. Aynı işi belgenin tüm dosyalarına döngüyle uygular (`getDocumentFiles` → her dosyaya `documentFileStatus`).
  - ✗ **EKSİK METOTLAR:** `getAparments`, `setAparments`, `preparePayment`, `setPayment` — routes/api.php'de tanımlı ama sınıfta YOK (çağrılırsa BadMethodCallException).
- **İlişkiler:** `DocumentServiceProvider` (ana iş mantığı), `PersonsServiceProvider::clientPermInfo`, `EmailServiceProvider`, `PermissionService`; helper'lar `docPermCheck`, `parsePut`, `refreshAllUserPermissions`.
- **Bulgular:**
  - 🔴 4 route hedefi metot eksik (apartments + payment akışı hiç implemente edilmemiş).
  - 🟠 `transaction()` PUT'ta tanımsız `$response` döner (runtime notice/null JSON).
  - 🟡 Kullanılmayan importlar: `ReportServiceProvider`, `Hash`, `Storage`, `App\Upload`; `$logModel` değişkenleri ölü.

## `app/Http/Controllers/ExportController.php` (~419 satır)
- **Amaç:** XLSX export (6 model) + teklif PDF/ZIP indirme.
- **Semboller:**
  - `index(Request, $model, $type=null)` — ✓ `POST /v1/export/{model}/{type?}` (api) ve ✓ `GET /export/{model}/{type?}` (web, name:.export-table). `$type` kullanılmıyor. Modeller: `clients` (cariler), `documents` (belgeler), `offers` (teklifler), `requests` (talepler) → hepsi `Documents::tableList` + `main_attr` JSON flatten; `notificationlogs` → `NotificationLog::tableList`; `users` → `User::tableList`; `userlogs` → `Userlog::tableList`. Her satır için `$rowCallback` formatlaması (tarih, durum çevirisi). Veri boşsa 404. Çıktı: `ExportService::exportExcel($headers, $data, $filename, $rowCallback)`.
  - `offerPdf1(Request)` — route YOK (eski sürüm, ölü kod). Tek PDF indirir (`PDF::loadView('exports.offer')`).
  - `offerPdf(Request)` — ✓ `POST /export/offer` (web, name:.offerPdf). Teklif datası + status history ile PDF üretir; `offer_type` `op-doc-offer-file` değilse PDF'i, ayrıca `offer_otherdocs_file**` alanlarındaki ekleri (path `EncryptionProvider::decrypt(description)` ile çözülür) ZIP'e koyar; `response()->download(...)->deleteFileAfterSend(true)`.
- **İlişkiler:** `ExportService`, `DocumentServiceProvider`, `EncryptionProvider`, `Documents/Document_files/NotificationLog/User/Userlog::tableList`, barryvdh `PDF`, view `exports.offer`.
- **Bulgular:**
  - 🟠 `index()`'de model bazlı İZİN KONTROLÜ YOK — login olabilen herkes `users`/`userlogs` (IP dahil) export edebilir (SystemController::table'daki kontroller burada yok).
  - 🟡 `offerPdf`'te decrypt edilen path doğrudan `storage_path`'e ekleniyor (path traversal riski, veri DB'den geliyor); temp ZIP her istekte yeniden; `offerPdf1` ölü; PhpSpreadsheet importları kullanılmıyor (export ExportService'te).

## `app/Http/Controllers/PersonsController.php` (~429 satır)
- **Amaç:** Kişi/kullanıcı CRUD, rol şablonları, bildirim grupları, şifre sıfırlama, arka plan.
- **Semboller:**
  - `index(Request)` — ✓ `ANY /v1/persons/{id?}`. GET → `PersonsServiceProvider::getPerson`; POST → `per-04-02` yoksa `user_password/user_username/permissions` alanları yasak → `setPerson(0,...)`; PUT → `parsePut()`, izinsiz kullanıcı sadece kendini (`id == session(person_id)`) güncelleyebilir ve `permissions/user_role/user_status/status/main_status/type_key` alanları silinir → `setPerson(id,...)`. Aktivasyon akışı: `user_status=1` + reseller + carisi yoksa `setClientToPerson` (cari kaydı otomatik); aktivasyonda `sendapproveMails`. DELETE → `removeContent`.
  - `uindex(Request)` — ✓ `ANY /v1/users/{id?}`. GET dışı + `per-04-02` yoksa sadece PUT'a izin, sonra `index()`'e delege.
  - `changeBackground(Request)` — ✓ `ANY /v1/setbackground`. Kendi person_id'sine dosya yükler (`setPerson` files ile).
  - `rolesTemplate(Request, $id=null)` — ✓ `GET|POST /v1/roles/templates`, ✓ `DELETE /v1/roles/templates/{id}`. Hepsi `per-04-03` ister. GET → `roleTemplateTrans('get')`; POST → rolleri normalize edip mevcutla diff'ler (added/updated), değişen rol için `updateUserPermissions` propagasyonu, `roleTemplateTrans('save')`, `UserLog` (`log-role-update`); DELETE → `roleTemplateTrans('delete', id)` + log.
  - `rolesItems()` — ✓ `GET /v1/roles/items` (**api.php'de iki kez tanımlı — duplicate route**). `per-04-03`; `RoleTemplateService::getPermissionCatalogs`.
  - `notificationGroups()` — ✓ `GET /v1/notification/groups`. `RoleTemplateService::getNotificationTypes`. (İzin kontrolü yok.)
  - `saveNotificationGroups(Request)` — ✓ `POST /v1/set-notification-groups`. `per-00-01` ister; `updateUserNotificationGroups`; `UserLog`. (Validasyon kullanımdan sonra yapılıyor; TODO yorumu kalmış.)
  - `getNotificationUsers()` — ✓ `GET /v1/notification-users`. `PersonsServiceProvider::getNotificationUsers`.
  - `resetUserCradentals(Request, $id)` — ⚠ `POST /v1/auth/resetusercradentals/{id}` — **route auth:sanctum grubu DIŞINDA (public)**. `per-04-02` kontrolü var ama `$authUser` null olabilir (PermissionService'in null davranışına bağımlı). Rastgele 16 hex şifre üretir, `user_needs_refresh=1`, `setPerson`, `forceLogoutPerson`, `UserLog`, `sendresetMail` ile yeni şifreyi mail atar.
  - ✗ **EKSİK:** `adminRefreshPermissions` (route: `POST /v1/admin/refresh-perms/{personId}`) sınıfta YOK.
- **İlişkiler:** `PersonsServiceProvider`, `PermissionService`, `RoleTemplateService`, `EmailServiceProvider`; `UserLog`, `Sys_options`.
- **Bulgular:**
  - 🔴 `resetUserCradentals` route'u kimlik doğrulamasız erişilebilir — PermissionService null-user'da fail-closed değilse herhangi bir kullanıcının şifresi sıfırlanabilir (yetki devralma saldırısı vektörü).
  - 🟠 `adminRefreshPermissions` eksik; `/v1/roles/items` duplicate tanım.
  - 🟡 `notificationGroups` izin kontrolsüz; `saveNotificationGroups`'ta doğrulama sırası bozuk; kullanılmayan importlar (`Hash`, `Storage`, `Upload`, `Validator`).

## `app/Http/Controllers/ReportController.php` (~18 satır)
- **Amaç:** Dashboard verisi proxy'si.
- **Semboller:** `dashboard(Request, $type, $period=null)` — ✓ `ANY /v1/dashboard/{type}/{period?}` → `ReportServiceProvider::dashboardInfo($type,$period)`.
- **Bulgular:** 🟡 Kullanılmayan importlar (`Hash`, `Storage`, `Upload`, `Validator`); izin kontrolü yok (servis katmanına devredilmiş olabilir).

## `app/Http/Controllers/SystemController.php` (~100 satır)
- **Amaç:** Generik tablo listeleme + bildirim merkezi.
- **Semboller:**
  - `table($model, Request)` — ✓ `POST /v1/table/{model}`. Sayfalama/sort parametrelerini `tableReq` JSON'una paketler; `user` → `per-04`+`per-04-01`, `document_files` → `per-07`+`per-07-01` kontrolü (diğer modeller kontrolsüz); `App\Models\{Model}::tableList` dinamik çağrı (`Userlog→UserLog`, `Notificationlog→NotificationLog` düzeltmeli).
  - `retriggerNotification($id)` — ✓ `POST /v1/notificationlog/{id}/retrigger`. `NotificationLog` tipine göre `MailService::retryNotificationLog` veya `SmsService::retryNotificationLog`.
  - `getNotifications()` — ✓ `GET /v1/notifications`. `ReportServiceProvider::getAdminNotifications('notif-00'…'notif-03')` + `getUserNotifications('offer-revision-request')`; herhangi biri doluysa `blink=1` (canlı bildirim rozeti).
- **Bulgular:** 🟠 `table()` whitelist'siz dinamik model çağrısı — izin case'leri dışındaki her `App\Models\*::tableList` çağrılabilir; 🟡 `retriggerNotification` izin kontrolsüz (herkes başkasının bildirimini yeniden tetikleyebilir).

## `app/Http/Controllers/Api/V1/User/MeController.php` (~15 satır)
- **Amaç:** Login kullanıcıyı döner.
- **Semboller:** `__invoke(Request)` — ✓ `GET /v1/me` (middleware `auth`) → `new UserResource($request->user())`.
- **Bulgular:** —

## `app/Http/Resources/UserResource.php` (~27 satır)
- **Amaç:** User JSON dönüşümü.
- **Semboller:** `toArray()` → id, name, email, email_verified_at, created_at, updated_at, `two_factor` (`hasEnabledTwoFactorAuthentication()` — Fortify; Vue tarafında 2FA UI için).
- **Bulgular:** —

## `app/Http/Middleware/CheckPermissionVersion.php` (~91 satır)
- **Amaç:** Her istekte oturumun izin versiyonunu ve force-logout bayrağını denetler (tek-oturum + izin yenileme altyapısı). api.php/web.php auth gruplarına kayıtlı.
- **Semboller:** `handle()` — bearer token'dan `token_id` veya `session_id` ile `ActiveSession` bulur; `force_logout` ise token siler, web logout + session invalidate, ajax'a 401 JSON / browser'a login redirect. `permission_version` mismatch'te `PermissionService::loadPermissionsToSession` ile soft-refresh yapıp `ActiveSession`'ı günceller; her durumda `last_seen` günceller.
- **İlişkiler:** `ActiveSession`, `PermissionService::getCachedUserPermissionVersion/loadPermissionsToSession`.
- **Bulgular:** 🟡 `$isRefreshEndpoint` hesaplanıp hiç kullanılmıyor (ölü kod); her istekte `ActiveSession` UPDATE (yazma yükü).

## `app/Http/Middleware/CspMiddleware.php` (~74 satır)
- **Amaç:** CSP + Permissions-Policy header'ları; rastgele nonce üretip HTML'deki `<script`/`<style` taglarına enjekte eder. Host listesi: hardcoded çift domain + `CSP_ADDITIONAL_HOSTS`/`ASSET_URL`/`APP_URL`.
- **Bulgular:** 🟠 `style-src 'unsafe-inline'` nonce'u etkisizleştirir; `str_replace` içerik üzerinde çalışır (JS string'i içindeki "<script" de bozulabilir); 🟡 `IS_TEST` env set ise CSP hiç basılmaz; hardcoded domain listesi kodda.

## `app/Http/Middleware/ParsePutMultipart.php` (~54 satır)
- **Amaç:** PUT/PATCH `multipart/form-data` body'sini `request_parse_body()` (PHP 8.4) ile parse edip request'e merge etmek.
- **Bulgular:** 🔴 **Çalışmaz:** `UploadedFile` ve `FileBag` sınıfları import edilmemiş (`App\Http\Middleware\UploadedFile` çözümlenir → sınıf yok) ve `UploadedFile` constructor argüman sırası yanlış (Symfony imzası: path, name, mimeType, error, test — burada size 4., error 5. parametre). Bu branch yürürse fatal error; DocumentController zaten `parsePut()` fallback'ine güveniyor.

## `app/Http/Middleware/TrustProxies.php` (~26 satır)
- **Amaç:** Proxy güveni.
- **Semboller:** `$proxies = '*'`; X-Forwarded-For/Host/Port/Proto header'ları.
- **Bulgular:** 🟠 `'*'` tüm proxy'lere güvenir — doğrudan erişilebilir sunucuda IP spoofing'e açık.

---

## Alan Özeti
- HTTP katmanı ince: controller'lar neredeyse tüm işi `PersonsServiceProvider`, `DocumentServiceProvider`, `ReportServiceProvider`, `EmailServiceProvider` ve `App\Services\*`'e devreder; kendileri yetki (`PermissionService::has`, `docPermCheck`) + validasyon + response şekillendirme yapar.
- Auth akışı: `loginUser` (reCAPTCHA + cache-kilit) → 2FA kodu **düz metin dosyaya** yazılır, SMS (`SmsService`) ve/veya mail (`MailService`) ile gider → `checkCode` dosyadan doğrular, Sanctum token + `ActiveSession` kaydı açar, eski oturumları `forceLogoutPerson` ile kapatır (tek oturum). `CheckPermissionVersion` middleware'i her istekte bu tabloyu denetler.
- Belge akışı: `DocumentController@index` metot-dağıtıcı; teklif (`op-doc-offer`) durum makinesi (`doc_trans_*`) + revizyon/aktivasyon mailleri; cari (`op-doc-client`) güncellemesi kişi-cari bağlarını ve session `currentStatus`'u tetikler.
- **Kırık route'lar:** `getAparments`, `setAparments`, `preparePayment`, `setPayment`, `adminRefreshPermissions` (api.php) ve `setapartment`, `closeapartment` (web.php) hedef metotları mevcut değil — ödeme/apartman akışı hiç yazılmamış.
- **Kritik bulgular:** public `resetusercradentals` route'u, `DEV_ADMIN` 2FA backdoor'u (`111111`), 2FA kodlarının düz metin dosya+log sızıntısı, `ExportController`'da izinsiz users/userlogs export'u, çalışmayan `ParsePutMultipart`, `TrustProxies '*'`.
