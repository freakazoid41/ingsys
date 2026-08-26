# Mevcut Doküman İncelemesi — Dosya Haritası
> Kapsam: 5 doküman (documentation/) · Tamamı okundu · İddialar kod ile çapraz kontrol edildi.
> Doğrulama durumları: ✅ doğru · ⚠️ kısmen doğru · ❌ güncel değil / hatalı

---

## `documentation/export-system.md` (~147 satır) — Genel durum: ✅ DOĞRU

- **Amaç:** Excel export alt sistemini anlatır: `ExportController::index()` + `ExportService::exportExcel()` + `pickle.js openTab()` ile POST-tabanlı `.xlsx` indirme.
- **Özet:** 7 model (clients, documents, offers, requests, notificationlogs, users, userlogs) için case-branch yapısı; her model `$headers`, `$filename`, `tableList()` verisi ve `$rowCallback` formatlayıcı tanımlar; boş veride 404 JSON döner; `ExportService` PhpSpreadsheet ile "Export" sheet'i yazar, auto-size uygular, `streamDownload` döner; `normalizeValue()` bool→1/0, null→'', array/object→JSON (UNESCAPED_UNICODE).

### Doğrulama tablosu

| İddia | Durum | Kanıt |
|---|---|---|
| `ExportController::index(Request, $model, $type=null)` giriş noktası | ✅ | app/Http/Controllers/ExportController.php:21 |
| 7 desteklenen model case'i | ✅ | ExportController.php:33,67,109,157,208,238,267 |
| Veri kaynakları: Documents/Document_files/NotificationLog/User/Userlog `tableList()` | ✅ | ExportController.php:41,76,122,172,220,249,281 (not: model dosyası `app/Models/UserLog.php`, kod `\App\Models\Userlog` olarak çağırıyor — PHP sınıf çözümü case-insensitive çalışır, case-sensitive FS + PSR-4'te potansiyel risk) |
| Boş veri → 404 `{'success':false,'msg':'No export data found.'}` | ✅ | ExportController.php:297-298 |
| `normalizeValue()` bool/null/array davranışı | ✅ | app/Services/ExportService.php:99-115 |
| Worksheet adı "Export", header satır 1, auto-size, streamDownload + doğru content-type | ✅ | ExportService.php:38,58-60,83-85,90-95 |
| Tarih formatları `d.m.Y` / `d.m.Y H:i:s`, status lokalizasyonu | ✅ | ExportController.php:61,103,150,229,258,261 |
| `pickle.js openTab()` gizli form ile POST submit | ✅ | resources/js/lib/pickle.js:606-609 |
| 6 frontend sayfası openTab kullanır | ✅ | CList.vue:99, NList.vue:70, DList.vue:82, RList.vue:89, UList.vue:243, LList.vue:71 |
| Route: `POST /v1/export/{model}/{type?}` auth middleware grubunda | ✅ | routes/api.php:40 (grup: api.php:30 `auth:sanctum`+`CheckPermissionVersion`) |
| Route kaydı | ⚠️ | Doküman sadece API POST route'unu anlatıyor; `routes/web.php:62-63` altında `GET /export/{model}/{type?}` ve `POST /export/offer` (offerPdf) route'ları da var — dokümanda eksik |

---

## `documentation/notification-sending-system.md` (~189 satır) — Genel durum: ⚠️ BÜYÜK ÖLÇÜDE DOĞRU, küçük hatalar var

- **Amaç:** E-posta (MailService) + SMS (SmsService) gönderim sistemi, kuyruklu job'lar, `notification_logs` audit tablosu ve retrigger akışı.
- **Özet:** MailService relay SMTP desteği (runtime config override, peer verification kapalı), SmsService iletisimmakinesi gateway'i, EmailServiceProvider event→SendNotificationMailJob dispatch, NotificationLog ile her denemenin kalıcı logu, artisan + API + UI üzerinden yeniden gönderim.

### Doğrulama tablosu

| İddia | Durum | Kanıt |
|---|---|---|
| MailService: payload validasyonu, relay, runtime mail config override | ✅ | app/Services/MailService.php:52-70 |
| Relay'de `verify_peer=false`, `allow_self_signed=true` | ✅ | MailService.php:74-76, 94-96 |
| Her gönderimde `NotificationLog::create` | ✅ | MailService.php:248, SmsService.php:159 |
| `retryNotificationLog()` her iki serviste | ✅ | MailService.php:280, SmsService.php:175 |
| SmsService config'i `services.iletisimmakinesi` + 9 env anahtarı | ✅ | app/Services/SmsService.php:26-34; config/services.php:41-51 (ILETISIM_* anahtarları birebir) |
| SMS token **24 saat** cache'lenir | ⚠️ | SmsService.php:50 → `Cache::remember(key, 60*24, ...)`. Laravel ≥5.8'de TTL saniyedir: 1440 sn = **24 dakika**, 24 saat değil. Kod ile doküman çelişiyor (muhtemelen amaç 24 saatti, kod 24 dk yapıyor) |
| EmailServiceProvider event'leri: register, offer, offer status, activation, client update, client file status | ✅ | app/Providers/EmailServiceProvider.php:20,36,47,56,70,85 |
| SendNotificationMailJob handler'ları (clientRegister, clientOfferGive, clientActivation vb.) | ✅ | app/Jobs/SendNotificationMailJob.php:42-63,121,133,305,326,407,439 |
| SendResetMailJob / SendInfoMailJob MailService kullanır | ✅ | app/Jobs/SendResetMailJob.php:47-48, app/Jobs/SendInfoMailJob.php:41-42 |
| `notification_logs` alanları (type,to,subject,body,status,error_message,detail,payload,attempts,last_attempt_at,sent_at) | ✅ | database/migrations/2026_04_15_000000_create_notification_logs_table.php:13-24 — birebir |
| `tableList()` `id` + `row_id` döner | ✅ | app/Models/NotificationLog.php:38-39 |
| `php artisan notification:retry {id}` | ✅ | app/Console/Commands/RetryNotificationSend.php:13 (dokümanda belirtilmeyen `{--queue}` opsiyonu da var) |
| `POST /api/v1/notificationlog/{id}/retrigger` → SystemController | ✅ | routes/api.php:45; app/Http/Controllers/SystemController.php:56-58 |
| NList.vue `Yeniden Gönder` butonu, row_id ile fetch, bekletme/başarı durumu | ✅ | resources/js/pages/coalsystem/NotificationLogs/NList.vue:182,219-265 |
| Dosya listesi: `panel/app/Console/Commands/RetryNotificationSendJob.php` | ❌ | Dosya `app/Jobs/RetryNotificationSendJob.php` altında, Commands altında değil |
| Tüm dosya yollarında `panel/` öneki | ❌ | Repoda `panel/` dizini yok; repo kökü = panel. Dokümandaki 12 dosya referansının tümü `panel/...` diye başlıyor |
| `config/mail.php` MAIL_RELAY_* seçeneklerini içerir | ⚠️ | config/mail.php'de sadece `use_relay` (mail.php:131) var; MAIL_RELAY_HOST/PORT/ENCRYPTION/USERNAME doğrudan MailService içinde `env()` ile okunuyor (MailService.php:57-60, default host `intmail.aydemenerji.com.tr`) |

---

## `documentation/permission-system-analysis.md` (~797 satır) — Genel durum: ⚠️ KISMEN DOĞRU (2 kritik güncel-değil iddia)

- **Amaç:** Custom yetki sisteminin tam analizi: per-XX kodları, rol şablonları, sys_* PostgreSQL tabloları, RoleTemplateService, PermissionService, session/cache versiyonlama, active_sessions, frontend Pinia entegrasyonu.
- **Özet:** JSON dosyalarından PostgreSQL'e taşınmış (Nisan 2026) rol/izin/bildirim kataloğu; `PermissionService` merkezi yetki kontrolü; `checkPerm()` uyumluluk katmanı; file-cache versiyonlama ile canlı izin tazeleme; DEV_ADMIN süperkullanıcı.

### Doğrulama tablosu

| İddia | Durum | Kanıt |
|---|---|---|
| 4 tablo: sys_role_templates, sys_permission_catalogs, sys_notification_types, sys_role_template_audit | ✅ | database/migrations/2026_04_11_000001..000004 |
| 4 Eloquent model + casts + toJsonFormat/audits/logChange | ✅ | app/Models/SysRoleTemplate.php:25-46, SysRoleTemplateAudit.php:51, SysPermissionCatalog.php:26, SysNotificationType.php:26 |
| `scopeGetByCode` scope'u | ⚠️ | `getByCode` static metot olarak tanımlı, scope değil (SysPermissionCatalog.php:34) — işlevsel olarak eşdeğer |
| RoleTemplateService metodları + cache anahtarları (sys_role_templates_all vb.) + TTL 3600 | ✅ | app/Services/RoleTemplateService.php:21-24,29-33,272-276 |
| Seeder 3 JSON dosyasını okur | ⚠️ | Seeder `coal_roles_templates.json` + `role_details.json` + **`notification_details.json`** okuyor (database/seeders/SysRoleTemplateSeeder.php:30,66,130). Doküman §4.5 `role_notifications.json` diyor — öyle bir dosya yok |
| "All 18 permissions migrated" + hiyerarşi (§4.2.2) | ⚠️ | role_details.json'da 6 parent + 13 child = **19 kod** var; `per-04-04` ("Sistem Logları") dokümanın hiyerarşi listesinde ve §2.1 kataloğunda YOK |
| 5 immutable rol şablonu | ✅ | storage/entities/coal_roles_templates.json (immutable-reseller, -admin, -super-admin, -satınalma-personeli, -satınalma-keyuser) |
| 4 bildirim tipi (notif-00..03) | ✅ | storage/entities/notification_details.json |
| İzinler `sys_con_entities`'de `{id}**userpermissiongroup**{id}` entity_tag ile | ✅ | app/Providers/PersonsServiceProvider.php:218-224 |
| `updateUserPermissions()` rol→tüm kullanıcılara yayma + cache tazeleme | ✅ | PersonsServiceProvider.php:444-456 |
| PermissionService metodları (has, loadPermissionsToSession, ensureSessionFreshness, refresh/bump/invalidate, forceLogoutPerson) | ✅ | app/Services/PermissionService.php:73,82,104,134,140,168,195 |
| Cache anahtarları `permissions.user.{id}` / `permissions.user.version.{id}` | ✅ | PermissionService.php:31-39 |
| Session anahtarları `perms`, `permission_version`, `sper-*` | ✅ | PermissionService.php:175-187 |
| `permissions.cache_store` / `PERMISSIONS_CACHE_STORE` | ✅ | PermissionService.php:18 — `config('permissions.cache_store', env(...))` (not: `config/permissions.php` dosyası yok, default 'file' fallback'i ile çalışıyor) |
| `checkPerm()` → `PermissionService->has()` delegasyonu | ✅ | app/Helpers/PermissionHelpers.php:9-17 |
| DEV_ADMIN 'all' süperkullanıcı | ✅ | PermissionService.php:146,161 |
| PersonsController per-04-02 / per-04-03 kontrolleri | ✅ | app/Http/Controllers/PersonsController.php:46,68,138,162,313,392 |
| SystemController per-04/per-04-01/per-07/per-07-01 | ✅ | app/Http/Controllers/SystemController.php:38,41 |
| DocumentController per-07-02 | ✅ | app/Http/Controllers/DocumentController.php:254,303 |
| AuthController::getPermissions → ensureSessionFreshness | ✅ | app/Http/Controllers/AuthController.php:825-827 |
| api.php: auth:sanctum + CheckPermissionVersion grubu | ✅ | routes/api.php:30 |
| `/v1/admin/refresh-perms/{personId}` endpoint'i çalışır | ❌ | Route var (api.php:37) ama `PersonsController::adminRefreshPermissions` metodu **kod tabanında yok** (app/ genelinde grep sonuçsuz) → route çağrılırsa hata verir |
| `/v1/admin/force-logout/{personId}` ile admin zorla çıkış (§4.7, §9.6) | ❌ | Route **yorum satırı** (routes/api.php:38); `forceLogoutUser` metodu da yok. Force logout sadece login/status-change/password-reset akışlarından tetikleniyor; admin API'si mevcut değil |
| `permission:create` CLI komutu | ✅ | app/Console/Commands/CreatePermissionCommand.php:16-19 |
| active_sessions alanları + force_logout kolonları | ✅ | database/migrations/2026_04_24_000000 + 2026_04_25_000000 |
| `User::tableList()` is_active 1 dakikalık pencere | ✅ | app/Models/User.php:84 (`interval '1 minutes'`) |
| users tablosu alanları (email,password,person_id,role,status,needs_refresh) | ✅ | database/migrations/0001_01_01_000000_create_users_table.php:16-24 |
| Frontend: auth.js getPermissions → /api/v1/getpermissions, alanlar | ✅ | resources/js/stores/auth.js:18-28; app.js:39 |
| pickle.js 401 permission_changed retry + force_logout yolu | ✅ | resources/js/lib/pickle.js:105,141-156 |

---

## `documentation/single-session-enforcement-system.md` (~178 satır) — Genel durum: ⚠️ ÇOĞUNLUKLA DOĞRU (1 tablo satırı hatalı)

- **Amaç:** Tek-oturum zorlaması: yeni giriş eski oturumları `force_logout=true` işaretler, middleware her istekte kontrol edip eski tarayıcıyı atar.
- **Özet:** `active_sessions` tablosu; `AuthController::checkCode` 3 adımı (eskileri işaretle → token → yeni kayıt); `CheckPermissionVersion` token-id önce, session-id fallback; stale kayıt temizliği (login-time 24s + gece 02:00 cron).

### Doğrulama tablosu

| İddia | Durum | Kanıt |
|---|---|---|
| checkCode: eski oturumları force_logout işaretle → token → yeni ActiveSession | ✅ | app/Http/Controllers/AuthController.php:443-446 (forceLogoutPerson, sebep: "Bu hesaba başka bir cihazdan giriş yapıldı."), 459-462 (ActiveSession::create) |
| Middleware: token ID önce, session ID fallback | ✅ | app/Http/Middleware/CheckPermissionVersion.php:19-32 |
| force_logout'ta: token sil, web logout, session invalidate, kaydı sil, ajax→401 `force_logout` / değilse login redirect | ✅ | CheckPermissionVersion.php:40-66 |
| Middleware hem web hem API route'larında | ✅ | routes/api.php:30, routes/web.php:20 |
| forceLogoutPerson: login / status değişimi / şifre reset | ✅ | AuthController.php:443; PersonsServiceProvider.php:187 (status, "Kullanıcını Durumunuz Değiştirildi..."); PersonsController.php:410 (resetUserCradentals, "Şifreniz Sıfırlandı...") |
| **"Admin rolü değiştirirse → tüm oturumlar ölür"** | ❌ | Rol değişiminde forceLogoutPerson ÇAĞRILMIYOR (PersonsServiceProvider.php:191-196 sadece role update). Bunun yerine `bumpUserPermissionVersion` ile **sessiz izin tazelemesi** yapılıyor (PersonsServiceProvider.php:273). Dokümanın kendi "Bonus" bölümüyle çelişiyor |
| Login-time stale temizlik (24 saat, subDay) | ✅ | AuthController.php:431-436 — kod birebir dokümandaki gibi |
| `active-sessions:clean` komutu + --force-logout-hours / --stale-days | ✅ | app/Console/Commands/CleanActiveSessions.php:11-13 |
| Kernel'de günlük 02:00 schedule | ✅ | app/Console/Kernel.php:36 (`dailyAt('02:00')`) |
| pickle.js: 401 force_logout → auth temizle, sebebi göster, login'e yönlendir | ✅ | resources/js/lib/pickle.js:141-156 (token silinir, `parsed.reason` gösterilir, `willClose` → `window.location.href='/'`) |
| Aynı middleware izin versiyonu uyuşmazlığında sessiz refresh | ✅ | CheckPermissionVersion.php:68-81 |

---

## `documentation/notification-receiving-system.md` (~615 satır) — Genel durum: ⚠️ KISMEN GÜNCEL (backend doğru, frontend "Issues" bölümü eski)

- **Amaç:** Bildirim alma/gösterme sistemi: Header.vue zili → navigationStore → `/api/v1/notifications` (SystemController → ReportServiceProvider); rejectedFiles authStore üzerinden.
- **Özet:** 5 API bildirim kategorisi (awaitingUsers, clientChanges, newOffer, offerRevisionRequests, offerChanges) + clientFile (rejectedFiles); blink göstergesi; SweetAlert modal; router yönlendirmeleri.

### Doğrulama tablosu

| İddia | Durum | Kanıt |
|---|---|---|
| SystemController::getNotifications 5 kategori + blink | ✅ | app/Http/Controllers/SystemController.php:78-99 (kod birebir dokümandaki gibi) |
| ReportServiceProvider: notif-00→getAwaitingUserRequests, notif-01→getAwaitingClientFiles, notif-02/03→getOffers, offer-revision-request→reseller-only | ✅ | app/Providers/ReportServiceProvider.php:17-63 (reseller kontrolü: line 55) |
| getNotificationUsers izin kontrolü | ✅ | app/Providers/PersonsServiceProvider.php:636 |
| Header.vue loadNotifications: rejectedFiles→notifications + navigationStore.getNotifications() | ✅ | resources/js/components/coalparts/Header.vue:74-91 |
| showNotifications 5 kategori + clientFile birleşimi, başlık/mesaj metinleri | ✅ | Header.vue:115-195 (metinler birebir) |
| addNotifications computed | ✅ | Header.vue:47-49 |
| Route'lar: `/api/v1/notifications`, `/api/v1/getpermissions` | ✅ | routes/api.php:44,63 |
| authStore.getPermissions alanları (permissions, currentStatus, typeKey, personId, userName) | ✅ | resources/js/stores/auth.js:18-28; app.js:39'da init çağrısı |
| **Issue 1:** yorum satırı halinde merge (line 62) | ❌ | Yorum satırı kaldırılmış; yerine `mergeNotifications()` metodu + `watch: navigationStore.notifications` eklenmiş (Header.vue:27-46, 92-96) |
| **Issue 2:** reaktivite hook/watcher yok | ❌ | Watcher mevcut (Header.vue:27-46) — sorun giderilmiş |
| **Issue 3:** navigation.js getNotifications'ta try/catch yok | ❌ | try/catch + hata durumunda `{blink:0}` reset mevcut (resources/js/stores/navigation.js:58-68). Header.vue tarafında hâlâ hata state'i yok (kısmen geçerli) |
| Zil ikonu `:hidden="addNotifications?.blink !== 1"` ile kırmızı nokta | ❌ | Artık `v-if="totalNotificationCount > 0"` ile badge gösteriliyor (Header.vue:309-313) |
| **Öneri 4:** bildirim sayısı badge'i eklenmeli | ❌ | Zaten uygulanmış: `totalNotificationCount` computed (Header.vue:50-62) |
| navigation.js state `notifications: []` | ⚠️ | Gerçekte `notifications: {}` (navigation.js:20) — trivial |
| Satır numaraları (loadNotifications 46-64, getNotifications 58-65) | ⚠️ | Gerçek: loadNotifications 74-91, getNotifications 57-69 — kayma var |
| NSettings.vue bildirim grup ayarları | ✅ | resources/js/pages/coalsystem/Notifications/NSettings.vue mevcut |
| (Eksik) auth.js 30 saniyelik heartbeat getPermissions | ⚠️ | Dokümanda yok; auth.js:32-34'te 30sn heartbeat var |

---

## Alan Özeti

- **5 dokümanın genel kalitesi yüksek:** backend akışları (controller/service/middleware/job) neredeyse birebir kodla örtüşüyor; özellikle export-system.md ve single-session-enforcement-system.md satır seviyesinde doğru.
- **En kritik bulgu (permission-system-analysis.md):** Dokümanda "çalışıyor" denilen iki admin endpoint'i gerçekte yok — `/v1/admin/force-logout/{personId}` route'u yorum satırı (api.php:38) ve `adminRefreshPermissions` metodu route'ta tanımlı olmasına rağmen controller'da mevcut değil (kırık route, api.php:37).
- **notification-receiving-system.md'nin "Current Issues" bölümü eski:** Issue 1/2/3 ve Öneri 4 kodda çözülmüş (watcher, try/catch, badge) — doküman frontend'in eski halini anlatıyor.
- **Rol değişimi ≠ force logout:** single-session dokümanının tablosundaki "rol değişince oturumlar ölür" satırı yanlış; gerçekte `bumpUserPermissionVersion` ile sessiz izin tazelemesi yapılıyor (dokümanın kendi bonus bölümüyle çelişki).
- **Küçük sürüklenmeler:** `panel/` yol öneki (repo kökü panel'in kendisi), `role_notifications.json`→`notification_details.json`, SMS token cache süresi (24s iddiası vs 24dk kod), eksik `per-04-04` kodu, RetryNotificationSendJob yolu (Jobs, Commands değil).
