# Çeşitli (Testler, Scriptler, Build/CI, Kök Yapılandırma) — Dosya Haritası
> Kapsam: 26 dosya · Tamamı okundu.

## tests/

### `tests/TestCase.php` (~10 satır)
- **Amaç:** Tüm testlerin türediği soyut taban sınıf; Laravel `BaseTestCase`'i olduğu gibi genişletir.
- **Semboller:** `Tests\TestCase` (abstract).
- **İlişkiler:** `Illuminate\Foundation\Testing\TestCase`; `RecaptchaRuleTest` ve `SmsServiceTest` bunu extend eder.
- **Bulgular:** Gövde tamamen boş; Laravel 12'de `CreatesApplication` trait'i kaldırıldığı için sorun değil ama ortak test altyapısı (seed, refresh DB) hiç tanımlanmamış.

### `tests/Unit/ExampleTest.php` (~16 satır)
- **Amaç:** PHPUnit iskeletinden kalan placeholder test (`assertTrue(true)`).
- **Semboller:** `ExampleTest::test_that_true_is_true`.
- **İlişkiler:** Doğrudan `PHPUnit\Framework\TestCase` extend eder (Laravel boot etmez).
- **Bulgular:** Ölü/şablon kod; silinebilir.

### `tests/Unit/RecaptchaRuleTest.php` (~48 satır)
- **Amaç:** `App\Rules\Recaptcha` doğrulama kuralını HTTP fake ile test eder: Google `success:true` → geçer, `success:false` → kalır; hata mesajının Türkçe ve birebir beklenen metin olduğunu doğrular.
- **Semboller:** `test_recaptcha_rule_passes_when_google_returns_success`, `test_recaptcha_rule_fails_when_google_returns_failure`, `test_recaptcha_rule_message_is_translatable_and_user_facing`.
- **İlişkiler:** `App\Rules\Recaptcha`, `Http::fake`.
- **Bulgular:** — (iyi kapsamlı, mesaj metni sabit string ile kırılgan şekilde eşleşiyor).

### `tests/Unit/SmsServiceTest.php` (~93 satır)
- **Amaç:** `App\Services\SmsService`'i iki modda test eder: (1) mock config + URL-bazlı `Http::fake` ile `/authenticate`, `/getOriginators`, `/sendSMS` uçlarını taklit edip `sendSms` başarısını doğrular; (2) `SMS_REAL_TEST=1` env'i varsa `.env`'den gerçek İletişim Makinesi kimlik bilgilerini yükleyip gerçek SMS gönderir.
- **Semboller:** `test_it_sends_sms_with_valid_phone_number`, `test_it_sends_sms_with_real_service_when_enabled`.
- **İlişkiler:** `App\Services\SmsService`, `services.iletisimmakinesi.*` config'i, `Dotenv\Dotenv` (manuel `.env` yükleme).
- **Bulgular:** Gerçek telefon numarası (`5438826976`) kaynak koda hardcoded. Gerçek-servis testi unit testi içinde yaşayan bir entegrasyon testi; `SMS_REAL_TEST`/`SMS_REAL_PHONE` env'leri belgelenmemiş. Test config anahtarları SmsService'in okuduğu `services.iletisimmakinesi` şemasıyla birebir eşleşiyor.

## scripts/

### `scripts/send_test_mail.php` (~46 satır)
- **Amaç:** CLI'dan framework'ü boot edip `App\Services\MailService::sendMail()` ile test e-postası gönderir; `--use-relay` bayrağı veya `MAIL_USE_RELAY` env'i ile relay mailer seçilir. Başarısızlıkta exit 2.
- **Semboller:** yok (prosedürel script).
- **İlişkiler:** `bootstrap/app.php`, `App\Services\MailService`, `MAIL_USE_RELAY` env.
- **Bulgular:** Manuel operasyon aracı; `prdTest` scripti aynı işi artisan komutu (`mail:test`) üzerinden yapıyor — iki paralel yol var.

### `scripts/send_test_sms.php` (~31 satır)
- **Amaç:** CLI'dan framework'ü boot edip `App\Services\SmsService::sendSms()` ile test SMS'i gönderir; argüman yoksa varsayılan numara/mesaj kullanır. Başarısızlıkta exit 2.
- **Semboller:** yok (prosedürel script).
- **İlişkiler:** `bootstrap/app.php`, `App\Services\SmsService`.
- **Bulgular:** Varsayılan olarak gerçek telefon numarası (`5438826976`) hardcoded.

## storage/entities/ (legacy JSON tanım dosyaları)

Üç dosya da sistem tanımlarının **eski JSON-tabanlı kaynağı**; artık veritabanı modelleri (`SysRoleTemplate`, `SysPermissionCatalog`, `SysNotificationType` — her birinin docblock'unda "Replaces JSON file storage at storage/entities/…" yazar) bunların yerini almış. Çalışma zamanında hiçbir kod bu JSON'ları okumuyor; tek okuyucu **`database/seeders/SysRoleTemplateSeeder.php`** (`seedRoleTemplates`, `seedPermissionCatalog`, `seedNotificationTypes` metotları `storage_path('entities/...')` üzerinden okuyup DB'ye aktarır). Yani rol: tek seferlik seed kaynağı / migration köprüsü.

### `storage/entities/coal_roles_templates.json` (~42 satır)
- **Amaç:** 5 sabit rol şablonu: Tedarikçi, Satınalma Personeli, Satınalma KeyUser, Admin, Super Admin; her biri `permissions` dizisinde `per-XX-YY` kodları taşır.
- **Yapı:** `[{id, name, description, permissions[], created_at}]`; id'ler `immutable-*` önekli, `created_at` ISO-8601 (2026-03-26).
- **Okuyucu:** `SysRoleTemplateSeeder::seedRoleTemplates()` → `sys_role_templates` tablosu.
- **Bulgular:** Satınalma Personeli ve KeyUser şablonlarında `per-06-01` iki kez tekrar ediyor; Personeli ile KeyUser izin listeleri birebir aynı (rol ayrımı JSON'da anlamsız). Tedarikçi şablonunda üst grup `per-06`/`per-05` kodları eksik, sadece alt kodlar var.

### `storage/entities/notification_details.json` (~26 satır)
- **Amaç:** 4 bildirim tipi tanımı: Tedarikçi Kayıt Başvurusu, Tedarikçi bilgi değişikliği, Yeni teklif, Teklif revize (`notif-00`..`notif-03`).
- **Yapı:** `[{parent_id, title, group_key:"op-notif", op_key, childs[]}]`; `childs` hepsinde boş.
- **Okuyucu:** `SysRoleTemplateSeeder::seedNotificationTypes()` → `sys_notification_types` tablosu.
- **Bulgular:** "Teklif revize edildi " başlığında sonda boşluk var (veri hijyeni).

### `storage/entities/role_details.json` (~86 satır)
- **Amaç:** İzin kataloğu (permission tree): 6 ana grup (Bildirimler per-00, Kontrol Paneli per-04, Talep Yönetimi per-05, Firma Yönetimi per-06, Dökümanlar per-07, Teklifler per-08) ve alt izinleri (`childs`).
- **Yapı:** `[{parent_id, title, ttitle:"Perm_con_ops", ctitle:"type_id", group_key:"op-perm", op_key, childs:[{...op_key}]}]`.
- **Okuyucu:** `SysRoleTemplateSeeder::seedPermissionCatalog()` → `sys_permission_catalog` tablosu; çalışma zamanında yetki kontrolü `checkPerm()` helper'ı + session üzerinden bu kodlarla yapılır.
- **Bulgular:** `ttitle`/`ctitle` alanları eski sys_con_ops şemasından kalma artık meta. `per-02`/`per-03` gibi bazı numaralar atlanmış (eski modüller silinmiş olabilir).

## bin/ ve kök scriptler

### `bin/install.sh` (~14 satır)
- **Amaç:** Sıfırdan kurulum: `.env.example`→`.env`, composer install, key:generate, sqlite dosyası oluştur, migrate+seed, npm install+build.
- **Bulgular:** sqlite touch ediyor ama projenin gerçek `.env.testing`'i pgsql kullanıyor — kurulum sonrası `.env` elle düzeltilmezse sqlite'a migrate eder; proje gerçekte pgsql/sqlsrv hedefliyor. `--no-interaction` dışında hata kontrolü yok (`set -e` yok).

### `prdTest` (~3 satır)
- **Amaç:** Prod ortamında mail + SMS altyapısını hızlıca doğrulamak için artisan komutlarını çalıştıran kısayol: `php artisan mail:test … --use-relay` ve `php artisan sms:test …`.
- **İlişkiler:** `mail:test` ve `sms:test` artisan komutları (app/Console); gerçek alıcı `kadir@kontent.com.tr` ve numara `5438826976` hardcoded.
- **Bulgular:** İlk satır `!/usr/bin/bash` — `#` eksik, shebang olarak geçersiz; doğrudan `./prdTest` çalışmaz, `bash prdTest` gerekir. Kişisel e-posta/telefon repoda.

### `prjBuildLive` (~16 satır)
- **Amaç:** Ortamı sıfırdan kuran "build" scripti: `migrate:fresh` + 4 seeder (SysSeeder, SysRoleTemplateSeeder, UserSeeder, DataSeeder) + `storage/app/public/documents/*` silme; sonda yorumlanmış serve/build/dev komutları.
- **İlişkiler:** `SysRoleTemplateSeeder` burada çağrılıyor → storage/entities JSON'larının DB'ye aktarım yolu bu scriptten geçiyor.
- **Bulgular:** Shebang yine bozuk (`!/usr/bin/bash`). Adı "Live" ama `migrate:fresh` ve belge klasörünü silme içeriyor — canlıda çalıştırılırsa tüm veriyi ve yüklenen dosyaları yok eder; tehlikeli isimlendirme. Son satır `#php artisan serve.` (sonda nokta) da kırık yorum.

## public/

### `public/index.php` (~30 satır)
- **Amaç:** Front controller. Standart Laravel akışına ek olarak: `X-Powered-By`/`Server` header'larını siler; `HTTP_HOST` içinde "yatagantermik" geçiyorsa `$GLOBALS['SYS_CODE']='YATAGAN'`, yoksa `'CATES'` yapar (çift-kiracılı yapı); `$GLOBALS['CSP_ADDITIONAL_HOSTS']`'a host'u atar; maintenance ve bootstrap'ı çalıştırır.
- **Semboller:** `$GLOBALS['SYS_CODE']`, `$GLOBALS['CSP_ADDITIONAL_HOSTS']`, `LARAVEL_START`.
- **İlişkiler:** `bootstrap/app.php`; SYS_CODE global'i muhtemelen config/mail (`MAIL_FROM_ADDRESSYATAGAN`/`CATES` env'leri) ve CSP middleware'i tarafından okunuyor.
- **Bulgular:** Tenant seçimi global mutable state + host substring eşleşmesiyle yapılıyor; `HTTP_HOST` kullanıcı kontrollüdür (Host header injection ile tenant spoofing teorik olarak mümkün — trusted-host middleware'i yoksa).

### `public/.htaccess` (~21 satır)
- **Amaç:** Standart Laravel Apache yapılandırması: MultiViews/Indexes kapalı, Authorization header passthrough, trailing-slash yönlendirmesi, front controller rewrite.
- **Bulgular:** —

### `public/manifest.json` (~7 satır)
- **Amaç:** PWA manifest taslağı (name "Vue SPA", standalone).
- **Bulgular:** `lang: de-DE` — Türkçe uygulamada Almanca; scaffold artığı. `name`/`short_name` hâlâ "Vue SPA". İkon tanımı yok; fiilen kullanılmayan yarım manifest.

## CI / Build yapılandırması

### `.chipperci.yml` (~23 satır)
- **Amaç:** Chipper CI pipeline'ı: php 8.3 + node 16 ortamında Setup (`.env.testing`→`.env`, composer install, key:generate, sqlite oluştur, migrate) → Compile Assets (npm install/build) → Run Tests (phpunit).
- **Bulgular:**
  - **node 16 çok eski** — proje Vite kullanıyor (Vite 5+ Node 18+ ister); `npm run build` bu ortamda büyük olasılıkla kırılır.
  - Setup sqlite dosyası oluşturuyor ama `.env.testing` pgsql (127.0.0.1:5431, b2x/b2x) tanımlı → CI'da migrate, dış Postgres servisi olmadan bağlanamaz; pipeline yapılandırması ile env tutarsız.
  - `.env.testing` içindeki gerçek SMTP/SMS kimlik bilgileri CI ortamına da taşınıyor.

### `vite.config.js` (~49 satır)
- **Amaç:** Vite yapılandırması: `@`→`/resources/js` alias, sourcemap açık, laravel-vite-plugin (input: `resources/js/app.js`, `coal-swal.js` + `public/coaltheme/css/` altında 4 CSS dosyası, refresh:true), Vue plugin (transformAssetUrls: base null, includeAbsolute false), `laravel-vue-i18n` plugin'i.
- **İlişkiler:** `resources/js/app.js`, `public/coaltheme/css/*`; i18n plugin'i `lang/` dosyalarını derler.
- **Bulgular:** `public/` altındaki statik CSS'ler Vite input'u yapılmış — public dosyaları build pipeline'a sokmak alışılmadık; theme asset'lerinin sürümlenmesi için bilinçli tercih olabilir.

### `tailwind.config.js` (~20 satır)
- **Amaç:** Tailwind: content `resources/**/*.{blade,js,vue}`; tek özelleştirme sans fontuna `Figtree` eklemek; plugin yok.
- **Bulgular:** Proje asıl olarak `coaltheme` CSS'i kullanıyor; Tailwind minimal/yardımcı rolde.

### `postcss.config.js` (~6 satır)
- **Amaç:** PostCSS: tailwindcss + autoprefixer.
- **Bulgular:** —

### `jsconfig.json` (~13 satır)
- **Amaç:** IDE desteği: `@/*`→`resources/js/*` path mapping; `node_modules` ve `public` exclude.
- **Bulgular:** —

## Kök yapılandırma / meta

### `pint.json` (~6 satır)
- **Amaç:** Laravel Pint: `laravel` preset + `simplified_null_return` ve `braces` kuralları açık.
- **Bulgular:** —

### `.editorconfig` (~18 satır)
- **Amaç:** UTF-8, LF, 4 boşluk; md'de trailing-whitespace korunur; yml/yaml/vue/js 2 boşluk; docker-compose.yml 4 boşluk.
- **Bulgular:** —

### `.gitignore` (~17 satır)
- **Amaç:** .DS_Store, phpunit cache, `storage/*.key`, `.env*`, Homestead, auth.json, npm/yarn log, IDE klasörleri.
- **Bulgular:** **`/vendor`, `/node_modules`, `/public/build`, `/public/hot`, `/storage` (genel), `.phpunit.result.cache` dışı log'lar gibi standart Laravel girdileri eksik** — yanlışlıkla vendor/node_modules commit'lenebilir. `.env.testing` ignore edilmiyor (bilinçli: CI onu kullanıyor, ama içinde gerçek secret'lar var).

### `.gitattributes` (~11 satır)
- **Amaç:** LF normalizasyonu; blade/css/html/md/php için diff sürücüleri; `.github`, `CHANGELOG.md`, `.styleci.yml` export-ignore.
- **Bulgular:** —

### `.env.testing` (~142 satır)
- **Amaç:** Test/CI ortam değişkenleri; `.chipperci.yml` bunu `.env` olarak kopyalar. Uygulama adı "SYS", locale tr, pgsql (port 5431, b2x), session/cache/queue database, Gmail SMTP, relay mailer ayarları, reCAPTCHA **test** anahtarları, Ollama model ayarları (llama3.1, qwen3-embedding), İletişim Makinesi SMS API, çift-domain CSP/CORS host listesi, `MAIL_FROM_ADDRESSYATAGAN`/`CATES` (index.php'deki SYS_CODE ile eşleşen tenant-specific env isimleri).
- **Bulgular:**
  - **KRİTİK: gerçek secret'lar repoda düz metin** — Gmail app password (`MAIL_PASSWORD`), İletişim Makinesi kullanıcı/şifre/api_key/customer_code (`ILETISIM_*`), DB şifresi, sabit `APP_KEY`. `.env.testing` gitignore'da olmadığı için commit'lenmiş durumda.
  - `APP_DEBUG=true`, `APP_ENV=local` — test için makul ama secret'larla birleşince riskli.
  - Yorum satırlarında alternatif sqlsrv/pgsql bağlantıları ve canlı relay bilgileri (intmail.aydemenerji.com.tr) görünüyor — altyapı bilgisi ifşası.

### `composer.json` (~78 satır)
- **Amaç:** PHP bağımlılık tanımı. Require: php ^8.2, laravel/framework ^12, sanctum ^4.1, tinker, barryvdh/laravel-dompdf, mpdf/mpdf, phpoffice/phpspreadsheet. Dev: faker, pint, sail, mockery, collision, phpunit ^11, spatie/laravel-ignition.
- **Semboller:** autoload `App\`→`app/`; **`files` autoload ile 3 helper global yüklenir:** `app/Helpers/DocumentHelpers.php`, `ReportHelpers.php`, `PermissionHelpers.php`; `Tests\`→`tests/`.
- **İlişkiler:** Helper'ların global fonksiyonları (checkPerm vb.) tüm uygulamada kullanılır; composer script'leri standart Laravel hook'ları.
- **Bulgular:** Hem dompdf hem mpdf birlikte (iki PDF motoru — muhtemelen farklı raporlar için). `pestphp/pest-plugin` allow-plugins'ta izinli ama Pest kurulu değil. Minimum-stability stable.

## Alan Özeti

- **Test kapsamı çok dar:** toplam 2 gerçek test sınıfı (Recaptcha kuralı, SmsService) + 1 placeholder; Feature testi hiç yok, TestCase boş. CI (`chipperci`) phpunit çalıştırıyor ama pipeline (node 16 + sqlite) ile `.env.testing` (pgsql + gerçek servisler) birbiriyle tutarsız — CI'ın yeşil çalışması şüpheli.
- **storage/entities JSON'ları tek yönlü köprü:** yeni DB tabanlı modeller (`SysRoleTemplate`, `SysPermissionCatalog`, `SysNotificationType`) onların yerini almış; JSON'lar sadece `SysRoleTemplateSeeder` (ve onu çağıran `prjBuildLive`) üzerinden DB'ye seed ediliyor, çalışma zamanında okunmuyor.
- **En kritik bulgu secret hijyeni:** `.env.testing` içinde gerçek Gmail uygulama şifresi, SMS API kimlik bilgileri ve DB şifresi repoda; `.gitignore` ise vendor/node_modules gibi temel girdileri bile içermiyor.
- **Çift kiracı (CATES/YATAGAN) mimarisi bu dilimde görünür:** `public/index.php` Host header'ından `$GLOBALS['SYS_CODE']` türetiyor, `.env.testing`'de tenant'a özel `MAIL_FROM_ADDRESS*` değişkenleri var — tenant seçimi global state + substring eşleşmesiyle, kırılgan ve Host spoofing'e açık bir desen.
- **Operasyon scriptleri el yapımı ve riskli:** `prdTest`/`prjBuildLive` shebang'leri bozuk; `prjBuildLive` "live" adına rağmen `migrate:fresh` + belge silme içeriyor; `scripts/send_test_*` ile artisan `mail:test`/`sms:test` komutları aynı işi yapan paralel yollar.
