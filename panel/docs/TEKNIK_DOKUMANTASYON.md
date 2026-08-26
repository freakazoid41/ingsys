# KomurTedarik — Teknik Dokümantasyon (Kod Rehberi)

**Sürüm:** 1.0 · **Tarih:** 2026-08-01 · **Kapsam:** Tüm kaynak dosyaların tam incelemesine dayanır (247 dosya)
**Hedef kitle:** Projeyi devralan geliştirici. Hiçbir ön bilgi varsayılmamıştır.

---

## 1. Sistem özeti

KomurTedarik; kömür satınalma taleplerinin (ihale) açılması, tedarikçi firmaların
belge onay süreçleri, teklif toplama/değerlendirme ve bildirim süreçlerini yöneten
bir Laravel 12 + Vue 3 uygulamasıdır. Çift alan adıyla çok-kiracılı çalışır:
`komurtedarik.cates.com.tr` → `SYS_CODE=CATES`, `komurtedarik.yatagantermik.com.tr` →
`SYS_CODE=YATAGAN` (`public/index.php`, Host substring eşleşmesi).

| Katman | Teknoloji |
|---|---|
| Backend | Laravel 12, PHP ^8.2, PostgreSQL, Sanctum 4 |
| Frontend | Vue 3 SPA, Vite 6, Pinia 2, vue-router 4, Tailwind 3 |
| PDF/Excel | dompdf 3, mpdf 8, PhpSpreadsheet 4 |
| SMS | İletişim Makinesi UserGatewayWS/SMSGatewayWS (SOAP-benzeri XML API) |
| Mail | SMTP (Gmail) veya kurum içi relay (`MAIL_USE_RELAY`) |
| Kuyruk/Cache/Session | database driver; izin cache'i file store |

> **Okuma kılavuzu:** Bu doküman sentezdir. Dosya-dosya detay için `docs/mapping/`
> altındaki 11 harita dosyasına, bulgular için `docs/02-bulgular-ve-dogrulama.md`'ye,
> endpoint/ekran çapraz tabloları için `docs/03-iliski-haritasi.md`'ye bakın.

---

## 2. Kurulum ve çalıştırma

### 2.1 Gereksinimler
PHP ≥8.2, Composer, Node ≥20 (Vite 6), PostgreSQL, (ops.) Ghostscript (`signDocument` fallback'i).

### 2.2 Ortam
`.env.example` şablon olarak kullanılır **ancak gerçek secret'lar içerir** — kopyalayıp
değerleri değiştirin (bkz. Güvenlik §11). Kritik değişkenler:

| Değişken | Anlamı |
|---|---|
| `DB_*` | PostgreSQL bağlantısı (örnekte `pgsql`, port 5431) |
| `SYS_CUR`, `SYS_CUR_INFO` | Para birimi (TCMB çekimi) |
| `DEV_ADMIN` | Gizli süper-admin e-postası (arka kapı — §11.2) |
| `IS_TEST` | true: CSP kapalı + login formu dolu gelir + test reCAPTCHA |
| `MAIL_USE_RELAY`, `MAIL_RELAY_*` | Kurum içi relay ayarları |
| `ILETISIM_*` | SMS sağlayıcı kimlikleri |
| `RECAPTCHA_*` | reCAPTCHA v2 anahtarları |
| `CSP_ADDITIONAL_HOSTS`, `CORS_ALLOWED_ORIGINS` | Çift domain izin listeleri |

### 2.3 Kurulum adımları

```bash
composer install          # vendor repoda commit'li ama taze kurulum önerilir
npm install && npm run build
cp .env.example .env      # secret'ları değiştir!
php artisan key:generate  # APP_KEY'i yeniler
php artisan migrate
php artisan db:seed --class=SysSeeder
php artisan db:seed --class=SysRoleTemplateSeeder
php artisan db:seed --class=UserSeeder
# DataSeeder: ölü apartman verisi — ATLAYIN
php artisan serve         # veya vhost → public/
php artisan queue:work    # mail/bildirim job'ları (QUEUE_CONNECTION=database)
php artisan schedule:work # veya cron: * * * * * php artisan schedule:run
```

> **UYARI:** Kökteki `prjBuildLive` script'i `migrate:fresh` + **belge silme** yapar;
> canlıda asla çalıştırmayın. `prdTest` sadece mail/sms test komutudur. İkisinin de
> shebang'i bozuktur (`!/usr/bin/bash`) — `bash prjBuildLive` ile çalışır.

### 2.4 Seeder zinciri
`DatabaseSeeder` zinciri orchestrate **etmez** (sadece örnek kullanıcı). Doğru sıra:
1. `SysRoleTemplateSeeder` — `storage/entities/*.json`'dan 5 immutable rol + izin kataloğu
   (`per-*`) + bildirim tipleri (`notif-*`).
2. `UserSeeder` — 9 super-admin (repoda düz metin parolalar — değiştirin).
3. `SysSeeder` — `sys_options` sözlüğü (idempotent, op_key kontrollü).

### 2.5 Zamanlanmış görevler (`app/Console/Kernel.php`)

| Komut | Zaman | İş |
|---|---|---|
| `request:autoclose` | 01:00 | Bitiş tarihi geçen talebi kapatır (⚠ sadece ilk kaydı) |
| `active-sessions:clean` | 02:00 | Eski/ölü oturum kayıtlarını temizler |
| `currency:cron` | — | routes/console.php'de **yorum satırı** (kapalı) |

Manuel komutlar: `mail:test`, `sms:test`, `notification:retry [--queue]`,
`files:reencrypt-descriptions` (yedekli/dry-run), `permissions:create`,
`recaptcha:verify`, `fix:users` (hardcoded 2 hesabı siler — routes/console.php).

---

## 3. Mimari

### 3.1 İstek yaşam döngüsü
```
HTTP → public/index.php (SYS_CODE seçimi)
 → bootstrap/app.php: ParsePutMultipart(⚠kırık) → CspMiddleware → trustProxies(*)
 → routes: web.php (blade + SPA shell + export + dosya) | api.php (JSON)
 → auth:sanctum + CheckPermissionVersion (izin versiyonu + force_logout)
 → Controller → "Provider" adlı domain servisler (new ile) → Model/raw SQL → PostgreSQL
```

CSRF **tüm route'larda kapalıdır** (`validateCsrfTokens(except:['*'])`) — SPA
`pickle.js` Bearer token (localStorage) + session cookie ile çalışır.

### 3.2 Yanıltıcı isimlendirme — kritik bilgi
`app/Providers/` altındaki sınıflar (AppServiceProvider **hariç**) Laravel service
provider **değildir**; container'a bind edilmezler, `new XServiceProvider()` ile
çağrılan domain servisleridir:

| Sınıf | Gerçek rolü | Satır |
|---|---|---|
| `DocumentServiceProvider` | Belge EAV CRUD, durum, dosya durumu, export verisi | 886 |
| `PersonsServiceProvider` | Kişi/kullanıcı CRUD, yetki kaynağı, bildirim grupları, clientPermInfo | 687 |
| `ReportServiceProvider` | Dashboard veri setleri, bildirim listeleri | 463 |
| `EmailServiceProvider` | Olay bazlı mail dispatch wrapper'ı | 112 |
| `EncryptionProvider` | Özel AES-128-CBC + PBKDF2 şifreleme | 119 |

### 3.3 Veri mimarisi: EAV + evrensel sözlük
Form şemaları kodda değil, veritabanındadır:

```
sys_options  (sözlük: op_key/group_key/ttitle/ctitle — her tip burada)
documents    (ana kayıt: type_id→sys_options, qnid, grp_code, person_id, status)
sys_con_ops  (belgeye bağlı form bölümü: main_id→documents, type_id→form tipi)
sys_con_entities (alan değeri: conn_id→sys_con_ops, entity_tag, entity_value, table_tag)
```

- `entity_tag` biçimi: `{alan}**{grup}**{anahtar}` (örn. `contphone**userfacilitygroup**main-0`).
- Dosya alanlarında `table_tag='document_files'` ve `entity_value` = dosya id'si.
- Okuma: `DocumentServiceProvider::getFormData($qnid)` raw SQL ile `formFormat`
  (form tipi → bölüm → entities) döner.
- Yazma: `registerContent($id, $requestData, $files)` — DB transaction içinde
  `main_*` alanları `documents`'a, `dynamicF` bölümleri EAV'ye, `dynamicFile*`'lar
  dosya sistemine yazılır; öncesi/sonrası `user_logs`'a JSON konur.
- **FK yoktur**; bütünlük uygulamaya emanettir. Silmeler soft (`status=0`).

Aynı desen kişiler için de geçerlidir: `persons` + `sys_con_ops`(type: user-contact/
user-client/user-permission/user-notification form) + `sys_con_entities`.
Kullanıcı izinleri tek entity'de JSON dizisidir (`op-doc-user-permission-form`).

### 3.4 Çok-kiracılık
`$GLOBALS['SYS_CODE']` (CATES|YATAGAN) loglara ve model `creating` hook'larıyla
`grp_code`'a yazılır. Veri ayrımı tam değildir — aynı DB, etiket bazlı ayrım.

---

## 4. Kimlik doğrulama ve oturum

### 4.1 Akış (AuthController)
1. `GET /` → `coallogin.blade.php` (reCAPTCHA v2). Blade'lerin JS'i
   `public/front/pages/*/page.js` — **kaynağı repoda yok**, derlenmiş hali commit'li.
2. `POST /api/v1/auth/login` → `loginUser`:
   - Validator + Recaptcha rule (Google siteverify).
   - `User::where(email, status=1)`; yoksa "Bilgiler Hatalıdır".
   - Kilit: Cache'te `login:attempts:{email}` ≥5 → `login:locked:{email}` 15 dk.
   - `Auth::attempt` + kişi kaydı (`PersonsServiceProvider::getPerson`).
   - Başarısızlık `user_logs` (log-login-failed / log-lock).
   - Session'a `type_key` (op-pert-admin|op-pert-reseller), `person_id`, `ptitle`.
3. `generateAndSendTwoFactorCode`: 6 haneli kod → **düz metin**
   `storage/app/{token}-{personId}-login.txt`; kişinin `contmail*`/`contphone*`
   kontaklarına MailService + SmsService ile gönderim; hiçbiri yoksa user.email'e.
4. `GET /smscallback` → `loginSms.blade.php` → `POST /api/auth/checkcode` → `checkCode`:
   dosyadan kod oku, 120 sn TTL, tek kullanımlık (silinir). Yanlışsa log + red.
5. Başarıda: `Auth::login` → `loadUserPermissionsToSession` → `clientPermInfo`
   (tedarikçi durumu) → Sanctum `createToken` → `ActiveSession` kaydı →
   `forceLogoutPerson` (önceki oturumlar düşer) → ilk girişse şifre ekranı.
6. SPA'ya giriş: `/coalpanel/{any}` closure'ı session `type_key` + `2f_success` ister.

### 4.2 Şifre sıfırlama
`POST /api/auth/sendmail` (throttle 4/dk) → storage'a `{key}-refreshmail.txt` → mail linki
`/auth/passwordreset/{code}` → 2FA SMS → `auth/passwordReset.blade.php` →
`POST /auth/passchange` → bcrypt, `needs_refresh=0`.

### 4.3 Tek oturum & izin versiyonu
- `active_sessions`: user_id, token_id, session_id, ip, ua, current_status,
  permission_version, force_logout(+reason/at), last_seen.
- `CheckPermissionVersion` her korumalı istekte: session'daki versiyon ≠ cache'teki
  versiyon ise izinleri tazeler; `force_logout` işaretliyse 401 + çıkış.
- Frontend 30 sn'de bir `GET /api/v1/getpermissions` heartbeat'i atar (`stores/auth.js`).
- ⚠ `PermissionService::forceLogoutPerson`'da `UserLog`/`Sys_options` import'suz —
  DB işareti atılır ama log asla yazılmaz, çağırana `false` döner (Bulgu A7).

---

## 5. Yetki sistemi

### 5.1 Model
- Katalog: `sys_permission_catalogs` (`per-XX-YY` kod, açıklama, grup).
- Atama: kişi EAV'sinde JSON dizisi (`op-doc-user-permission-form` entity'si).
- Şablon: `sys_role_templates` — 5 immutable rol (storage/entities/coal_roles_templates.json):
  Tedarikçi (5 izin), Satınalma Personeli (6), Satınalma KeyUser (6), Admin (2),
  Super Admin (19) + özel şablonlar; `sys_role_template_audit` değişiklik geçmişi.
- `users.role` string olarak şablon op_key'ine bakar (FK yok).

### 5.2 Çalışma zamanı (PermissionService)
```
has($user, $kod):
  DEV_ADMIN ise true (arka kapı §11.2)
  ensureSessionFreshness (version uyuşmazsa session'ı tazele)
  session('sper-'.$kod) === true → true
  file cache permissions.user.{personId} (30 gün) içinde ara
  DEV_ADMIN ise yine true
```
- `docPermCheck($typeKey, read|edit|status)` (PermissionHelpers): belge tipi → izin kodu
  haritası (request: per-05-01/02, client: per-06-01/02, offer: per-08-01/02 + status per-05-02).
- Rol şablonu güncellenince `PersonsServiceProvider::updateUserPermissions` roldeki tüm
  kullanıcılara yayar + `bumpUserPermissionVersion` → açık oturumlar sonraki istekte tazelenir.
- `refreshAllUserPermissions()` (dosya onay/red sonrası çağrılır) **yanlış cache key**
  siler — toplu invalidation çalışmaz (Bulgu A7).
- Frontend: `getPermissions` yanıtındaki dizi menü/buton görünürlüğünü belirler;
  `has('all')` ise sabit tam liste döner.

### 5.3 Tedarikçi kısıtları (`clientPermInfo`)
`canProceed`: firma formu + imza dosyası (`cont_imza_file**`) yüklenmiş mi.
`canResponse`: imza dosyasının son durumu `doc_file_accepted` mi.
`clientQnidList`: kullanıcının bağlı olduğu firma qnid'leri (kendi firma formunu
GET/PUT edebilmesinin anahtarı).

---

## 6. Belge yaşam döngüsü (talep → teklif)

### 6.1 Belge tipleri (`sys_options`, group `op-doc`)
`op-doc-request` (talep/ihale), `op-doc-offer` (teklif), `op-doc-client` (firma formu),
+ form tipleri (`op-doc-*-form`, group `op-doc-forms`).

### 6.2 Durum makinesi (`doc_trans_*`)
```
Talep:  doc_trans_created → doc_trans_started → doc_trans_finished | doc_trans_cancelled
Teklif: doc_trans_created/draft → doc_trans_offer_sended → doc_trans_offer_review
        → doc_trans_offer_approved | doc_trans_offer_rejected
        → doc_trans_offer_revision → (tedarikçi kaydeder) doc_trans_offer_revised → review…
Dosya:  doc_file_waiting → doc_file_accepted | doc_file_rejected (→ doc_file_refreshed)
```
Her geçiş `transactions` (+`user_logs`) kaydıdır; `setStatus()` tipi `sys_options`'tan
çözer — **tanımsız op_key gönderilirse null hatası** (Bulgu B2: iki component
`doc_trans_offer_accepted` gönderiyor, sözlükte yok).

### 6.3 Controller kuralları (DocumentController@index)
- `docPermCheck($key, read|edit)`; tedarikçi istisnası: `op-doc-client` + kendi
  `clientQnidList`'i + GET/PUT.
- Teklif POST: `currentStatus.canResponse` şart; başarıda `sendOfferGiven` maili.
- Teklif PUT (tedarikçi): son durum sadece `revision|created|draft` ise düzenlenebilir;
  `revision`dan kayıt ⇒ otomatik `revised` + mail.
- Client PUT: `sendClientChanged` maili + `updatePersonClients` (bağlı kullanıcıların
  firma ünvan/kod senkronu) + session `currentStatus` tazeleme.
- DELETE: soft-delete (`status=0`); client silinirse bağlı kullanıcılar pasife alınır.

### 6.4 Dosya servisi
- Yükleme: `uploadFile` — ≤42MB, uzantı whitelist (jpg/png/jpeg/pdf/xls/xlsx),
  ad `time()+random+slugify`; `storage/app/public/documents/` (public disk).
- Kayıt: `document_files.description` = `EncryptionProvider::encrypt($filename)`
  (kompakt base64url format; eski kayıtlar legacy JSON-base64 — `files:reencrypt-descriptions`
  komutu migrasyon yapar).
- İndirme: `GET /order-file/{qnid|encrypted}` → `decryptFile()` — UUID ise qnid'den
  satırı bulur, yolu çözer, mime ile servis eder. ⚠ Belge-bazlı yetki kontrolleri
  yorum satırı (Bulgu A10/IDOR).
- Versiyonlama: aynı alana yeni dosya ⇒ eski `status=0` + `replaced_id` zinciri.

---

## 7. Bildirim sistemi

### 7.1 Gönderim
Olay → `EmailServiceProvider::send*` → Job (queue `default`) → `MailService::sendMail`
ve/veya `SmsService::sendSms` → `NotificationLog` (`pending → sent|error`, detail JSON).
Alıcı çözümü: `PersonsServiceProvider::getNotificationUsers($opKey)` — `notif-*` grubuna
atanmış kullanıcılar (`op-doc-user-notification-form` entity'si).

Job'lar: `SendNotificationMailJob`, `SendInfoMailJob`(ölü), `SendResetMailJob`,
`RetryNotificationSendJob`. Hepsi `ShouldQueue`, database driver.

### 7.2 MailService notları
- `sendMail`: `MAIL_USE_RELAY=true` ise relay host (vars. intmail.aydemenerji.com.tr:25),
  değilse config/mail.php SMTP; TLS peer doğrulaması kapalı.
- `renderHtmlMessage`: `emails/layout.blade.php` sarmalayıcı (CATES/YATAGAN logo ayrımı).
- ⚠ `relay_password_masked` alanına açık parola yazılıyor (Bulgu A5).
- `sendSms` (birleşik kapı): e-posta + SMS birlikte.
- ⚠ SMS token cache `60*24` **saniye** = 24 dk (dokümanlarda 24 saat yazar).

### 7.3 SmsService (İletişim Makinesi)
Token al (cache) → SMSGatewayWS send → XML/JSON çift parse. Kredi sorgusu GET.
`mail:test`/`sms:test` komutları ve `scripts/send_test_*.php` ile uçtan uca denenebilir.

### 7.4 Panel içi bildirimler
`GET /api/v1/notifications` (SystemController → ReportServiceProvider): kullanıcının
bildirim gruplarına göre bekleyen işler (yeni teklif, reddedilen dosya, onay bekleyen
kayıt vb.). Header zili + dashboard kartları tüketir. `NList.vue`'dan log retrigger.

---

## 8. Export

- Excel: `POST /api/v1/export/{model}/{type?}` ve `GET /export/{model}/{type?}` →
  `ExportController@index` → `ExportService::exportExcel` (PhpSpreadsheet).
  Modeller: documents (request/offer/client filtreli), userlog, notificationlog vb.;
  veri kaynağı her modelin `tableList()` metodu (raw SQL + `main_attr` JSON).
  ⚠ izin kontrolü yok (Bulgu, mapping/11).
- Teklif PDF: `POST /export/offer` → `offerPdf` — dompdf `exports/offer.blade.php`
  + decrypt edilmiş ek dosyalarla ZIP.
- `exports/icmal.blade.php` ve `DocumentServiceProvider::getExportData` apartman
  mirasıdır (ölü).

---

## 9. Frontend mimarisi

### 9.1 Kabuk
`coalapp.blade.php` → `resources/js/app.js`: i18n (lang/{tr,en}.json eager) →
`getPermissions` → store init → tedarikçi redirect kontrolü → 30 sn heartbeat → mount.
`layouts/App.vue` (router-view) → `/coalpanel` altında `layouts/CoalPanel.vue`
(Sidebar + Header + Simplebar + preloader). Sidebar menüsü izinlere göre.

### 9.2 Router (17 route, guard YOK)
Tüm route'lar `/coalpanel` altında (tablo: docs/03 §2). Sunucu tarafı `$coalAuth`
closure'ı gerçek kapıdır; router'daki auth guard yorum satırı, catch-all 404 kapalı.

### 9.3 pickle.js (lib) — sistemin ortak istemcisi
`Plib`: fetch wrapper (CSRF meta + Bearer localStorage; 401 `permission_changed` →
sessionStorage tazeleyip retry; `force_logout` → çıkış) + form toolkit
(checkForm/clearElements/validatePassword) + UI (toast/loader/formatMoney/compressImage)
+ `crypFunc` (CryptoJS uyumlu base64 şifreleme — EncryptionProvider ile eş).
⚠ Bazı legacy endpoint'ler (transaction/checkMail) ölü; hata HTML'i escape'siz Swal'e basılır.

### 9.4 Form motoru (coalparts/Form.vue, ~2.900 satır)
5 şema (user/request/client/offer/flat-form) için imperative DOM inşası.
`formData.dynamicF`'te `alan**grup**anahtar` formatında veri toplar, dosyaları
`dynamicFile*` olarak FormData'ya koyar, sayfanın `savecallback`'ine devreder.
CForm/UForm/RForm/OForm/FlatForm'un tamamı bunu kullanır.
RSummary/OfferSummary + RequestLogTimeline/OfferLogTimeline simetrik özet+log çifti.

### 9.5 Store'lar
`auth` (permissions + heartbeat), `permissiondata` (rol şablonları + izin ağacı),
`navigation` (bildirimler, sys_code DOM'dan), `events` (⚠ legacy endpoint — Bulgu B3),
`formdata` (sayfa arası veri taşıyıcı).

---

## 10. Veritabanı referansı

26 tablo (detay: `docs/mapping/14-config-database.md`). Çekirdek:

| Tablo | Rol |
|---|---|
| `sys_options` | Evrensel sözlük (tüm tipler/durumlar/log tipleri) |
| `documents` | Ana belge (talep/teklif/firma) — qnid, type_id, grp_code, status |
| `sys_con_ops` / `sys_con_entities` | EAV form bölümleri ve alan değerleri |
| `persons` / `users` | Kişi + giriş hesabı (1-1, users.person_id) |
| `transactions` | Durum geçmişi (op_id=1 dosya) + ödeme taslağı |
| `document_files` | Dosya kaydı (description=şifreli yol, replaced_id, status) |
| `user_logs` | İşlem logu (before/after JSON, type_id→log-*) |
| `active_sessions` | Tek oturum + izin versiyonu + force_logout |
| `notification_logs` | Bildirim kuyruk kaydı (retry destekli) |
| `sys_role_templates` / `sys_permission_catalogs` / `sys_notification_types` / `sys_role_template_audit` | Rol/izin/bildirim katalogları |
| `currencies` | TCMB kurları |
| Jetstream mirası | teams, team_user, team_invitations, personal_access_tokens (kullanılmıyor) |

---

## 11. Güvenlik envanteri (özet — tamamı docs/02'de)

| # | Konu | Şiddet |
|---|---|---|
| A1 | SQL injection: 9 noktada raw SQL string birleştirme (route parametreleri dahil) | 🔴 |
| A2 | DEV_ADMIN arka kapısı: sabit 2FA `111111`, `has('all')`, yedek SMS numarası | 🔴 |
| A3 | `resetusercradentals` public route | 🔴 |
| A4 | 2FA kodları düz metin dosyada + beklenen kod user_logs'ta | 🔴 |
| A5 | Repoda canlı secret'lar (.env.example/.env.testing/.enve/UserSeeder/MailService log) | 🔴 |
| A6 | CSRF global kapalı + trustProxies(*) + statefulApi | 🔴 |
| A7 | forceLogoutPerson log'suz dönüyor; refreshAllUserPermissions yanlış cache key | 🟠 |
| A8 | ParsePutMultipart import'suz → her PUT'ta sessiz hata | 🟠 |
| A9 | CSP IS_TEST'te kapalı; unsafe-inline; frontend XSS yüzeyleri | 🟠 |
| A10 | EncryptionProvider: hardcoded 'pickle' anahtar, MAC yok; /order-file IDOR | 🟠 |

İşlevsel kırıklar: B1 kırık route'lar (apartman/ödeme/refresh-perms), B2 teklif
"Kabul" yanlış op_key, B3 dashboard endpoint uyumsuzlukları, B4 transaction PUT.
Hijyen: vendor+node_modules+build commit'li, prjBuildLive yıkıcı, ölü kod listesi.

**Devralan için ilk 5 aksiyon:**
1. Tüm secret'ları rotate et (DB, Gmail, SMS, APP_KEY) ve `.env*` dosyalarını repodan temizle.
2. DEV_ADMIN arka kapısını kaldır ya da kurum hesabına çevir.
3. A1 noktalarını prepared statement'a çevir (öncelik: getFormData, getPerson).
4. `resetusercradentals`'ı auth grubuna al.
5. `.gitignore`'a vendor/node_modules/build ekle; repoyu temizle (BFG/filter-repo).

---

## 12. Test ve CI

- `tests/Unit`: `RecaptchaRuleTest`, `SmsServiceTest` (mock'lu), `ExampleTest` (placeholder).
  Feature testi yok. `phpunit.xml` sqlite in-memory **yorum satırı** → testler
  `.env.testing`'deki gerçek pgsql'e bağlanır.
- `.chipperci.yml`: `.env.testing`'i `.env` yapar + phpunit; node 16 (Vite 6 ile uyumsuz) —
  pipeline büyük ihtimalle kırık.
- Kod stili: Laravel Pint (`pint.json`).

## 13. Sözlükçe

| Terim | Anlam |
|---|---|
| qnid | Dışa açık UUID-benzeri kimlik (documents/persons/document_files/transactions) |
| op_key / group_key | sys_options sözlük anahtarı / anahtar grubu |
| EAV | Entity-Attribute-Value: sys_con_ops + sys_con_entities |
| `**` ayracı | entity_tag ve dosya alan adlarında bölüm ayıracı |
| falanml | Eski sistemin ölü mail şablon formatı (public/coaltheme/mail) |
| Plib / pickle.js | Frontend fetch + form toolkit |
| coalpanel | SPA kök yolu (/coalpanel) |
| reseller | Tedarikçi kullanıcı tipi (op-pert-reseller) |
| SYS_CODE | Kiracı etiketi (CATES/YATAGAN) |
