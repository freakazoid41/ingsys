# 02 — Bulgular ve Doğrulama

> Bulgular 11 paralel okuma agent'ı + ana hat manuel incelemesiyle çıkarıldı.
> Her bulgu kod satırıyla **çapraz kontrol edildi**. Durum sütunu:
> ✅ = satır düzeyinde doğrulandı · 📝 = agent raporu (referanslı, doğrulama notuyla).

Şiddet: 🔴 kritik/güvenlik · 🟠 yüksek · 🟡 orta/teknik borç · ⚪ bilgi

---

## A. Güvenlik

### A1 🔴 SQL Injection (çok noktalı) ✅
Raw SQL'e kullanıcı girdisi doğrudan gömülüyor (`DB::select` string birleştirme):

| Konum | Kod |
|---|---|
| `app/Providers/DocumentServiceProvider.php` getFormData | `d.qnid = '".$id."'` (2 sorgu) |
| aynı dosya removeContent | `sce.entity_value = '$id'` |
| aynı dosya getDocumentFiles | `d.qnid = '$documentId'` |
| aynı dosya getRejectedClientFiles | `d.qnid in ('".implode("','",$list)."')` |
| aynı dosya updatePersonClients | `sce.entity_value = '$documentId'` |
| `app/Providers/PersonsServiceProvider.php` getPerson | `where i.qnid = '".$id."'` |
| aynı dosya clientPermInfo | `p.qnid = '$personQnId'` |
| aynı dosya getNotificationUsers | `like '%$opKey%'`, `p.qnid = '$personId'` |
| `app/Helpers/PermissionHelpers.php` hasMailPerm | `so2.op_key = '".$key."' and u.person_id = '".$personId."'` |

`$id`/`$personQnId` değerleri route parametresinden (`/api/v1/document/{id?}` vb.) gelir.
Koruma olarak sadece naïve `noInject()` blacklist fonksiyonu var (2 kopya:
DocumentHelpers + PermissionHelpers) ve çoğu çağrıda kullanılmıyor.
Ek: hasMailPerm ayrıca **var olmayan** `document_con_ops` tablosunu sorgular ve
hiçbir yerden çağrılmaz (ölü + kırık).

### A2 🔴 Gizli geliştirici arka kapısı (DEV_ADMIN) ✅
`env('DEV_ADMIN')` (`.env.example`: `kadir@kontent.com.tr`) olan hesap için:
- 2FA kodu her zaman `111111` (`AuthController::generateAndSendTwoFactorCode`).
- `firstLogin` baypas (`checkCode` içinde `$user->email == env('DEV_ADMIN')`).
- `PermissionService::has()`: `'all'` sorgusunda ve her izin kontrolünün sonunda
  email eşleşirse `true`.
- `AuthController::getPermissions`: `has('all')` ise sabit yazılmış tam yetki listesi
  (per-00 … per-08-02) döner.
- Kod gönderilemezse yedek SMS **hardcoded** `5438826976` numarasına gider.
- `User::tableList`'te bu adres listeden gizlenir.

### A3 🔴 Public route: şifre sıfırlama ✅
`routes/api.php`: `POST /v1/auth/resetusercradentals/{id}` →
`PersonsController@resetUserCradentals`, `auth:sanctum` grubunun **dışında** tanımlı.
Metot içi `per-04-02` kontrolü null-user senaryosuna bağımlı; route seviyesinde koruma yok.

### A4 🔴 2FA kodları düz metin + log sızıntısı ✅
- Kod `storage/app/{token}-{personId}-login.txt` dosyasına düz metin yazılır
  (`generateAndSendTwoFactorCode`).
- Yanlış denemede **beklenen kod dahil** `user_logs`'a yazılır:
  `checkCode` → `'code_entered'`, `'code_expected'` alanları.
- `localhost:8000`'da kod API yanıtında `debug_code` olarak döner.
- Şifre sıfırlama mail'i linkini oluşturan key de düz metin dosyada.

### A5 🔴 Repoda canlı secret'lar ✅
Git-takipli dosyalarda gerçek değerler:
- `.env.example`, `.env.testing`, `.enve`: `APP_KEY`, prod DB parolası (`coaltest`),
  Gmail app password, İletişim Makinesi SMS kullanıcı/parola/API key.
- `database/seeders/UserSeeder.php`: 9 admin için düz metin parola + gerçek telefonlar.
- `resources/views/login.blade.php:38,42`: `IS_TEST=true` iken form
  `admin@picklecan.me` / `Pickle412.` ile dolu gelir.
- `app/Services/MailService.php:158`: `'relay_password_masked' => $relayPassword ?? '***'`
  — maskeleme adı altında **açık parola** log ve `notification_logs.detail`'e yazılır.
- `.env.example`'daki reCAPTCHA anahtarları Google'ın herkese açık test anahtarları.

### A6 🔴 CSRF global kapalı + geniş güven ✅
`bootstrap/app.php`: `validateCsrfTokens(except: ['*'])` — tüm route'lar muaf.
Üstüne `statefulApi()` + `trustProxies('*')` + session cookie'li auth: klasik CSRF
yüzeyi açık. `config/cors.php` scheme'siz host tanımlı.

### A7 🟠 Tek oturum / izin tazeleme zincirindeki kırıklar ✅
- `PermissionService::forceLogoutPerson`: `UserLog` ve `Sys_options` import'suz
  (App\Services namespace'inde çözülemez) → `\Throwable` yakalanıp `false` dönüyor;
  **DB'deki force_logout işareti atılıyor ama log hiç yazılmıyor** ve çağıran hep
  başarısız sanıyor. (`app/Services/PermissionService.php` import bloğu vs. gövde)
- `PermissionHelpers::refreshAllUserPermissions`: `user_permissions_{id}` key'lerini
  siliyor; PermissionService ise `permissions.user.{id}` kullanıyor → toplu
  invalidation fiilen çalışmıyor (sadece anlık kullanıcının session'ı tazeleniyor).

### A8 🟠 ParsePutMultipart middleware'i çalışmıyor ✅
`app/Http/Middleware/ParsePutMultipart.php`: `UploadedFile` ve `FileBag` import'suz
→ her PUT multipart isteğinde exception → catch'te loglanıp sessizce geçiliyor.
Sistem `DocumentController` içindeki `parsePut()` helper fallback'i ile ayakta.

### A9 🟠 CSP ve test bayrakları ✅
- `CspMiddleware`: `IS_TEST` true ise CSP header **hiç basılmıyor**; politika
  `style-src 'unsafe-inline'` içeriyor. `.env.example`'da `IS_TEST=true`.
- `public/index.php`: kiracı seçimi `HTTP_HOST` substring ile — trusted-host kontrolü yok.
- Frontend: `pickle.js` JSON parse hatasında sunucu HTML'ini escape etmeden Swal'e basıyor;
  Dashboard/Header bileşenlerinde kullanıcı verisi (ünvan, kullanıcı adı) Swal html'ine
  escape'siz gömülüyor (XSS yüzeyi). 📝

### A10 🟠 Şifreleme zayıflıkları ✅
`EncryptionProvider`: AES-128-CBC + PBKDF2(999 iterasyon, düşük), varsayılan anahtar
**hardcoded `'pickle'`**, MAC yok (CBC malleable). Dosya yolları bu anahtarla şifreli;
`/order-file/{doc}` indirirken belge-bazlı yetki kontrolleri yorum satırı (IDOR yüzeyi —
qnid bilinen dosya, giriş yapmış her kullanıcıca indirilebilir). ✅ (decryptFile içindeki
yorum blokları bizzat görüldü)

### A11 🟡 Diğer
- `SmsService`: token body loglanıyor; kredi sorgusu GET query string'de. 📝
- `MailService:359-360`: `//kontent back door` yorumu altında hardcoded SMS numarası
  — **yorum satırı** (aktif değil; agent raporundaki ifade düzeltildi). ✅
- `routes/console.php` `fix:users`: iki hardcoded e-postanın kaydını silen komut canlıda duruyor. ✅
- `SendResetMailJob`/`EmailServiceProvider`: yeni şifre düz metin e-posta + queue payload'unda. 📝

---

## B. İşlevsel hatalar (kırık akışlar)

### B1 🔴 Kırık route'lar (metot yok) ✅
Çağrılırsa `BadMethodCallException`:
- `api.php`: `getAparments`, `setAparments`, `preparePayment`, `setPayment`
  (DocumentController'da yok), `adminRefreshPermissions` (PersonsController'da yok).
- `web.php`: `setapartment`, `closeapartment` (AuthController'da yok).
- Eski apartman-yönetim sisteminden miras; ödeme/apartman akışı hiç yazılmamış.

### B2 🟠 Teklif "Kabul" butonu yanlış durum kodu gönderiyor ✅
Seeder'da sadece `doc_trans_offer_approved` var; ama
`resources/js/components/Offer/OfferRequestTable.vue:106` ve
`resources/js/components/Dashboard/Default.vue:310` `doc_trans_offer_accepted` gönderiyor
→ `Sys_options` bulunamaz → setStatus hata verir. (OList/OForm doğru kodu kullanıyor.)

### B3 🟠 Dashboard endpoint uyumsuzlukları ✅
- `ReportServiceProvider.php:108` `dashboardClientTopStatus()` çağırıyor, metot
  tanımlı değil → `clienttopstatus` isteği fatal (UI çağırmıyor ama API açık).
- `resources/js/stores/events.js` `/api/v1/dashboard/getOngoingTasks` ve
  `monthlyEvents/{period}` çağırıyor; backend `dashboardInfo` switch'inde karşılıkları
  yok → takvim/etkinlik verisi boş döner (eski sistemden kalma).

### B4 🟠 DocumentController::transaction ✅
PUT case'i tamamen yorum satırı; `$response` tanımsız → PUT isteğinde PHP Error.
Yorumlu `checkPermRoute` ve `hasMailPerm` gibi `checkPermRoute` de boş tanımlı.

### B5 🟡 Bildirim/zamanlama
- SMS token cache: `Cache::remember(..., 60 * 24, ...)` = **24 dakika** (saniye TTL);
  kod yorumu/doküman "24 saat" diyor (`SmsService.php:50`). ✅
- `CurrencyCron::clearOld`: yorum "15 günden eski" derken `delete from currencies`
  (tüm tablo); komut schedule'da **yorum satırı** (zaten çalışmıyor). ✅
- `RequestAutoclose`: sadece ilk (`$data[0]`) biten talebi kapatıyor. 📝
- SMS bildirim yolu stub — sadece loglanıyor, gönderim yok. 📝

### B6 🟡 Frontend kırıkları 📝
- `OForm`: admin sayfayı açınca otomatik `doc_trans_offer_review` yazıyor (görüntüleme veri yazıyor).
- `Roles.vue`: tüm şablon listesini tek POST ile kaydediyor (lost-update riski).
- `OList.giveOffer`: template'te `SYS_CODE` input'u yok → bağımsız teklifte `target_type` undefined riski.
- `register.blade.php`: şifre tekrar input'u da `name="password"` → sunucuya tekrar gitmiyor; "Kayıl Ol" yazım hatası.
- `NotFound.vue` route'suz (catch-all yorum satırında) — bilinmeyen URL boş sayfa.
- `Example/*`, `treeTest.vue`: demo/kırık (import'suz Transactions bileşeni).

---

## C. Teknik borç / hijyen

| # | Bulgu | Durum |
|---|---|---|
| C1 | `vendor/` (10.769 dosya), `node_modules/` (7.710), `public/build/` repoda commit'li; `.gitignore`'da `/vendor`, `/node_modules`, `/public/build`, `/public/hot` eksik | ✅ |
| C2 | `prjBuildLive`: adı "live" ama `migrate:fresh` + seed + `storage/app/public/documents/*` SİLME içeriyor; her iki script'te shebang bozuk (`!/usr/bin/bash`) | ✅ |
| C3 | "Provider" adlı 5 sınıf aslında domain servisi (new ile çağrılıyor): Document/Persons/Report/Email/Encryption ServiceProvider | ✅ |
| C4 | Ölü kod: `SendInfoMailJob`, `OfferTable.vue`, `hasMailPerm`, `parsePut` (legacy), `getExportData` (apartman), `exports/icmal.blade.php`, `public/coaltheme/mail/*.falanml` (hiçbir referans yok), `resources/markdown/*`, `resources/css/Pickle.md`, Jetstream teams mirası + `TeamPolicy`/`TeamFactory`, `DataSeeder` (apartman), `routes/console.php fix:users` | ✅/📝 |
| C5 | `.chipperci.yml`: node 16 (Vite 6 için yetersiz), sqlite touch ediliyor ama `.env.testing` pgsql; phpunit sqlite in-memory yorum satırı → testler gerçek DB'ye bağlanır | ✅/📝 |
| C6 | Test kapsamı: 2 gerçek unit testi (Recaptcha, SmsService) + placeholder; Feature testi yok | ✅ |
| C7 | DB: FK neredeyse hiç yok (EAV mantıksal bağlar); `sys_con_entities.conn_id` indexsiz; `sys_options.op_key` unique değil; `transactions.amount` scalesiz decimal; `persons.balance` float | 📝 |
| C8 | ~2.900 satırlık `coalparts/Form.vue` imperative DOM monoliti; `Math.random()` ile şifre üretimi; VKN algoritması yorum satırında | 📝 |
| C9 | `manifest.json` `lang=de-DE` + "Vue SPA" (scaffold artığı); lang dosyaları apartman terminolojisi taşıyor | ✅/📝 |
| C10 | Mükerrer: `api.php`'de `/v1/roles/items` iki kez; `UList.vue`'da searchTable duplicate; LogTimeline component'leri ~%80 aynı; `coal_roles_templates.json`'da per-06-01 mükerrer | ✅/📝 |

---

## D. Doğrulanan mimari gerçekler (hata değil, bilmek gerekir)

1. Multi-tenant: `public/index.php` Host → `$GLOBALS['SYS_CODE']` (CATES|YATAGAN);
   model `creating` hook'ları `qnid`+`grp_code` basar.
2. Session/cache/queue: database; izin cache'i file store, 30 gün TTL, versiyon = `time()`.
3. Zamanlanan görevler sadece 2 adet: `request:autoclose` 01:00, `active-sessions:clean` 02:00.
4. `DatabaseSeeder` zinciri orchestrate ETMEZ; kurulum sırası `prjBuildLive`'da:
   SysSeeder → SysRoleTemplateSeeder → UserSeeder → DataSeeder.
5. `storage/entities/*.json` sadece `SysRoleTemplateSeeder` tarafından okunur (legacy seed kaynağı).
6. Auth sayfalarının JS'i `public/front/pages/*/page.js` — **kaynak kodu repoda yok**,
   sadece derlenmiş hali var (eski build pipeline).
