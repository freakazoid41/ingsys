# Views / i18n / Mail Şablonları — Dosya Haritası
> Kapsam: 31 dosya (27 metin + 4 png listeleme) · Tamamı okundu.

## `resources/views/auth/coallogin.blade.php` (~202 satır)
- **Amaç:** Aktif ana giriş ekranı. Metronic tabanlı tema (`coaltheme`), kullanıcı adı + şifre formu, opsiyonel reCAPTCHA.
- **Semboller:** Form `route('login-user','admin')`'e POST eder; session flash'ları: `login-error`, `auth-forgot`, `sms-fail`, `sms-firstlogin`, `sms-success`; logo `$GLOBALS['SYS_CODE'].svg`; sayfa JS'i `pageScript` (`/front/pages/coallogin/page.js`) modül importu.
- **İlişkiler:** `routes/web.php` → `GET /` → `AuthController@coallogin` (`name('login')`). POST: `AuthController@loginUser`. Başarılı SMS sonrası `login-sms` route'una yönlenir; `sms-firstlogin` durumunda şifre güncelleme akışına girer. Kayıt linki `route('register')`.
- **Bulgular:** reCAPTCHA site key hem `config('services.recaptcha.site_key')` hem `env()` fallback ile okunuyor (çift kaynak). Cache-bust `?v=date('YmdHi')` her render'da dakikalık üretilir — CDN cache davranışını bozabilir. Session flash mesajları `{!! !!}` ile escape edilmeden basılıyor (controller tarafı sabit string'ler ürettiği için pratikte XSS riski düşük).

## `resources/views/auth/loginSms.blade.php` (~138 satır)
- **Amaç:** "Mail Doğrulama" başlıklı 6 haneli güvenlik kodu giriş ekranı (2FA adımı; metinde SMS denmesine rağmen başlık mail diyor).
- **Semboller:** 6 adet tek karakterlik `code_N` input (`@for` ile üretiliyor), form action `/api/auth/checkcode`, geri sayım `#countdown` (25 sn), "Tekrar Gönder" `#btn-send-code`.
- **İlişkiler:** `GET /smscallback` → `AuthController@loginSms` (`name('login-sms')`). Kod kontrolü `AuthController@checkCode` (API), CSRF token meta'sı yok — `@csrf` form içinde.
- **Bulgular:** Başlık/açıklama tutarsız: ekran "Mail Doğrulama" derken kod akışı SMS kodu üzerinden işliyor; telefon maske div'i (`.phoneMask`) kullanılıyor ama mail akışında anlamsız. Büyük inline SVG ikon gömülü.

## `resources/views/auth/passwordReset.blade.php` (~133 satır)
- **Amaç:** İlk giriş / şifre sıfırlama sonrası yeni şifre belirleme ekranı (şifre + şifre tekrar).
- **Semboller:** Form `/auth/passchange`'e POST; `password`, `password-check` inputları; hata blokları `#err-password`, `.message`; submit `#kt_sign_in_submit`.
- **İlişkiler:** `GET /auth/passwordreset/{code}` → `AuthController@passwordReset` (sanctum oturumu varsa bu view render edilir; yoksa storage'daki `{code}-refreshmail.txt` üzerinden SMS akışına düşer). POST `/auth/passchange` → `AuthController@passChange`.
- **Bulgular:** Şifre politikası sadece client-side metin olarak var; asıl doğrulama JS (`/front/pages/passwordReset/page.js`) ve sunucuda. CSRF token input'u formda görünmüyor (ajax ile header'dan gidiyor olabilir).

## `resources/views/auth/register.blade.php` (~191 satır)
- **Amaç:** Yeni kullanıcı kayıt ekranı: e-posta, telefon, şifre + tekrar (göz ikonu ile göster/gizle), opsiyonel reCAPTCHA.
- **Semboller:** Form `route('register-user')`'a POST; session flash `register-error`; `.password-toggle` span'leri inline SVG göz ikonları.
- **İlişkiler:** `GET /register` → `AuthController@register` (`name('register')`). POST → `AuthController@registerUser` (`name('register-user')`). Başarılı kayıt sonrası `login`'e redirect.
- **Bulgular:** Başlıkta yazım hatası: "Kayıl Ol" (Kayıt Ol olmalı). `password` ve `password-check` inputlarının ikisi de `name="password"` — tekrar alanı sunucuya ayrı değişkenle gitmiyor, eşleşme kontrolü yalnızca client-side JS'te.

## `resources/views/coalapp.blade.php` (~33 satır)
- **Amaç:** Vue 3 SPA kabuğu (shell). `#app` div'i boş; tüm panel arayüzü `resources/js/app.js` ile mount edilir.
- **Semboller:** `@vite` ile tema css + `resources/js/app.js`; hidden input'lar `header` (session `grp_title` ya da `APP_NAME`), `SYS_CUR`, `SYS_CODE` ($GLOBALS) — front-end bunları DOM'dan okur.
- **İlişkiler:** `routes/web.php` auth grubu: `GET /coalpanel` ve `GET /coalpanel/{any}` → closure `$coalAuth` (session `type_key` + `2f_success` zorunlu, yoksa 403). `type` parametresi admin/client ayrımı için view'e geçiliyor ama blade bunu kullanmıyor.
- **Bulgular:** Favicon yolu `/public/css/favicon.ico` — `public` prefix'i yanlış/şüpheli (`public/` kökü zaten webroot). View'e geçilen `type` değişkeni hiç kullanılmıyor (ölü parametre).

## `resources/views/login.blade.php` (~136 satır)
- **Amaç:** Eski Pickle/apartman yönetim sisteminden kalma alternatif giriş ekranı (koyu tema, `data-sa-theme="2"`).
- **Semboller:** Form `route('login-user','admin')`'e POST; session `login-success` ile apiKey hidden input → JS `localStorage.token`'a yazıp `/panel/apartments`'a yönlenir; cookie'den email/password okuma kodu.
- **İlişkiler:** `AuthController@login()` render eder ama route'ta yorum satırı (`//Route::get('/', [AuthController::class, 'login'])`) — şu an erişilemez durumda.
- **Bulgular:** **Kritik/ölü kod + güvenlik:** `IS_TEST` env'i true ise email `admin@picklecan.me` ve şifre `Pickle412.` blade'e hardcoded basılıyor. Eski sistemin `/panel/apartments` yönlendirmesi ve `resources/views` altında aktif route'u olmayan miras dosya. CSS değişkenleriyle gömülü koyu tema override'ı.

## `resources/views/emails/layout.blade.php` (~69 satır)
- **Amaç:** Aktif e-posta HTML layout'u. 600px kart, logo, başlık, intro, içerik, opsiyonel CTA butonu, alt metin ve footer.
- **Semboller:** Değişkenler: `$title`, `$header`, `$intro`, `$content` (raw `{!! !!}`), `$ctaUrl`, `$ctaText`, `$subtext`, `$footerText`, `$sysCode`. Logo: `$sysCode == 'CATES' ? cates.jpg : yatagan.jpg` (cates.com.tr storage URL'leri — hardcoded).
- **İlişkiler:** `MailService::renderHtmlMessage()` (`app/Services/MailService.php:270-277`) bu view'i render edip `sendMail`'e `html` olarak verir; `NotificationLog`'a yazılır. `emails.verify-email` de `@include` ile bunu kullanır.
- **Bulgular:** Eski base64-gömme logo kodu yorumda bırakılmış; aktif kod dış URL + hardcoded CATES/yatagan ayrımı içeriyor (yeni sistem kodu eklenirse logo yanlış çıkar). `$content` escape'siz basılıyor — içerik üreten kod güvenilir olmalı.

## `resources/views/emails/verify-email.blade.php` (~14 satır)
- **Amaç:** Hesap aktivasyon/onay e-postası içeriği; `emails.layout`'ı `@include` eder.
- **Semboller:** Sabit `$content` HTML'i; `ctaUrl` parametre ile, varsayılan `config('app.url')`.
- **İlişkiler:** `SendNotificationMailJob` (`app/Jobs/SendNotificationMailJob.php:308-312`) `view('emails.verify-email', [...])->render()` ile kullanır; konu "Hesap Aktivasyonu".
- **Bulgular:** `Merhaba ,` — isim değişkeni view'e geçiliyor (`name`) ama içerikte kullanılmıyor (boşluklu virgül).

## `resources/views/exports/icmal.blade.php` (~142 satır)
- **Amaç:** Dönemsel icmal (bilanço) raporu HTML'i: hesap bakiyeleri, giderler, gelirler, kira/aidat borçluları tabloları. DejaVu Sans fontu dompdf uyumu için.
- **Semboller:** Girdi değişkenleri: `$dates` (dönem aralığı "DD/MM/YYYY - DD/MM/YYYY"), `$accounts`, `$outcomes`, `$incomes`. İçeride `main_attr`/`main_info`/`acc_info` JSON parse, `doc_acc_dept` op_key'ine göre borç gruplama.
- **İlişkiler:** **Yetim view:** kod tabanında `exports.icmal` referansı yok (eski Pickle apartman sisteminden miras; aktif export `exports.offer`). Muhtemelen eski `PDF::loadView('exports.icmal')` çağrısı kaldırılmış.
- **Bulgular:** Ölü kod (aktif çağıran yok). Değişken isimlendirme tutarsız (`$depts` = debts için). `pagebreak` sınıfı tanımlı ama kullanılmıyor.

## `resources/views/exports/offer.blade.php` (~206 satır)
- **Amaç:** Teklif formu PDF şablonu (dompdf, A4 portrait). Başlık (firma/santral/talep kodu + belge no/tarih), Temel Bilgiler, Kömür Özellikleri, Fiyatlandırma, Prim/Penalite, Teslim Bilgileri, Ek Açıklama, Durum Güncellemeleri bölümleri.
- **Semboller:** Değişkenler: `$form` (tüm form alanları: clititle, target_type, request_id, buyer, seller, coal_type, calory, humidity, ash_content, sulfur, unit_price, shipping_included, fuel_price_impact, tiufe_price_impact, prime_condition_is(_bellow), amount, payment_periods, payment_due, start_date, desc...), `$document` (qnid, op_key), `$latestStatus`, `$statusHistory` (array/obje polimorfik render + Carbon ile `d.m.Y`).
- **İlişkiler:** `ExportController@offerPdf` (`app/Http/Controllers/ExportController.php:345-347`) → `PDF::loadView('exports.offer')` → `offer-{qnid}.pdf` download. Route: `POST /export/offer` (`web.php:62`, name `.offerPdf`). Ayrıca `ExportController.php:395` sipariş dosyasına ek PDF üretiminde de kullanılıyor (teklif tipi `op-doc-offer-file` değilse).
- **Bulgular:** `class="meta"` hücresinde çift `class` attribute'u (biri eziliyor). `prime_condition_is_bellow` yazım hatası (below olmalı) ama form alan adıyla uyumlu olmak zorunda.

## `resources/markdown/policy.md` (~3 satır)
- **Amaç:** Gizlilik politikası — Laravel Jetstream/Fortify scaffold placeholder'ı, hiç düzenlenmemiş ("Edit this file...").
- **Bulgular:** İçerik yok; giriş ekranlarındaki "Gizlilik Politikası" linkleri `href="#"` ile zaten boşa çıkıyor.

## `resources/markdown/terms.md` (~3 satır)
- **Amaç:** Kullanım şartları — aynı şekilde scaffold placeholder'ı, düzenlenmemiş.
- **Bulgular:** İçerik yok.

## `resources/css/app.css` (1 satır / 1 byte)
- **Amaç:** Fiilen boş (tek newline). Vite girişlerinde de kullanılmıyor — tema CSS'i `public/coaltheme/css/*.css`'ten yükleniyor.
- **Bulgular:** Ölü dosya.

## `resources/css/Pickle.md` (~52 satır)
- **Amaç:** Lorem-ipsum benzeri otomatik üretilmiş anlamsız İngilizce metin ("The so-called Pickle...") — muhtemelen eski sistemin test/dummy dosyası, CSS klasöründe duran markdown.
- **Bulgular:** Tamamen gereksiz dosya; yanlış dizinde, silinebilir.

## `lang/en.json` (~68 satır)
- **Amaç:** İngilizce çeviri anahtarları (düz JSON, `grup.anahtar` formatı). Kapsam: menüler (transactions, projects, meetings, periods, flats, targets, users, calendar, documentfiles, contacts, inventory), dashboard kartları, transaction tablo başlıkları.
- **İlişkiler:** `resources/js/app.js:27-30` `laravel-vue-i18n` + `import.meta.glob('../../lang/*.json')` ile eager yükler; bileşenler `wTrans` ile kullanır.
- **Bulgular:** İçerik eski apartman yönetimi domain'inden (Flats, Meetings, Supervisor) — kömür tedarik domain'iyle kısmen uyumsuz miras. `Meetting List` yazım hatası.

## `lang/tr.json` (~69 satır)
- **Amaç:** `en.json` ile birebir aynı anahtarların Türkçe karşılıkları (Kasalar, Daireler, Dönemler, İcmal Raporu vb.).
- **İlişkiler:** Aynı `laravel-vue-i18n` glob mekanizması.
- **Bulgular:** en.json ile anahtar paritesi tam; ikisi de eski domain terminolojisi taşıyor (daire/aidat → kömür tedarikte kullanılmayan kavramlar olabilir).

## `public/coaltheme/mail/template.falanml` (~23 satır)
- **Amaç:** Eski mail sistemi için HTML iskeleti: Bootstrap 4 + Cabin font CDN'leri ve gövdede tek `{*template*}` placeholder'ı — diğer `.falanml` parçaları bu gövdeye gömülecek şekilde tasarlanmış.
- **Bulgular:** Aşağıya bakınız (falanml formatı ortak notu).

## `public/coaltheme/mail/file-added.falanml` (~22 satır)
- **Amaç:** İhaleye yüklenici tarafından dosya eklendi bildirimi. Placeholder'lar: `{*domain*}`, `{*system*}`, `{*prs-title*}`, `{*order-no*}`, `{*start-at*}`, `{*end-at*}`, `{*target-at*}`, `{*link*}`.

## `public/coaltheme/mail/file-rejected.falanml` (~24 satır)
- **Amaç:** Yüklenen dosyanın reddedildiği bildirimi; ek olarak `{*type*}`, `{*description*}` (ret nedeni) placeholder'ları.

## `public/coaltheme/mail/forgot-pass.falanml` (~29 satır)
- **Amaç:** Şifre yenileme maili; tek kullanımlık link `{*pass-url*}` (http:// prefix'i hardcoded). Logo `mail-logo-adm.png` sabit.

## `public/coaltheme/mail/message.falanml` (~22 satır)
- **Amaç:** Sipariş durum değişikliği bildirimi: `{*order-no*}`, `{*status*}`.

## `public/coaltheme/mail/new-order.falanml` (~22 satır)
- **Amaç:** Yeni ihalenin firmaya atandığı bildirimi: `{*order-no*}`, `{*start-at*}`, `{*end-at*}`.

## `public/coaltheme/mail/order-filled.falanml` (~22 satır)
- **Amaç:** İhalenin yüklenici tarafından doldurulduğu/düzenlendiği bildirimi: `{*order-no*}`, `{*start-at*}`, `{*end-at*}`, `{*target-at*}`, `{*link*}`.

## `public/coaltheme/mail/test-document-added.falanml` (~76 satır)
- **Amaç:** Sipariş kalemi için test dokümanı yüklendi bildirimi; ürün tablosu (`{*prd-title*}`, `{*prd-unit*}`, `{*prd-quantity*}`, `{*add-prd*}` çoklu satır), `{*order-no*}`, `{*buying-no*}`, `{*doc-type*}`.
- **Bulgular:** Logo src'si bozuk: `mail-logo.png-{*system*}` (uzantı/placeholder sırası ters). İndirme linki yorum satırına alınmış.

## `public/coaltheme/mail/test-document-approved.falanml` (~90 satır)
- **Amaç:** Test dokümanı onay bildirimi; `thumbs-up.png` ikonu + onaylanan doküman/ürün tablosu. Aynı placeholder seti.

## `public/coaltheme/mail/test-document-rejected.falanml` (~95 satır)
- **Amaç:** Test dokümanı ret bildirimi; `thumbs-down.png` + ret notu `{*trs-desc*}` + ürün tablosu. Aynı placeholder seti.

## `public/coaltheme/mail/register_notification.html` (~31 satır)
- **Amaç:** Admin'e giden yeni kullanıcı kaydı bildirimi; statik HTML + `{{name}}`, `{{id}}`, `{{email}}`, `{{phone}}`, `{{date}}` mustache-benzeri placeholder'lar.
- **Bulgular:** Kod tabanında bu dosyayı okuyan/replace eden bir çağrı yok — yetim şablon (placeholder syntax'ı Blade ile çakışacağı için doğrudan view olarak da kullanılamaz).

### `.falanml` formatı — ortak değerlendirme
- `{*anahtar*}` placeholder syntax'lı düz HTML tablo e-posta şablonları; `template.falanml` sarmalayıcı gövde (`{*template*}` içine parça gömülür).
- **Hiçbir PHP/JS kodu `.falanml` uzantısını referans almıyor** (tam repo taraması; sadece dokümantasyon checklist'inde geçiyor). `MailService` aktif olarak `emails.layout` blade'ini kullanıyor; `.falanml`'ler önceki nesil (muhtemelen eski "Pickle"/GDZ sistemi) mail motorunun kalıntısıdır ve şu an **ölü asset** durumundalar. `{*prs-title*}` vb. alanlar eski kişi/ihale modeline işaret ediyor.
- PNG'ler (listeleme): `mail-logo-adm.png` (~12 KB), `mail-logo-gdz.png` (~3,2 KB), `thumbs-up.png` (~0,6 KB), `thumbs-down.png` (~0,6 KB) — falanml şablonlarının referans verdiği logo/ikon asset'leri.

## Alan Özeti
- **Render zinciri:** `routes/web.php` → `AuthController` (coallogin/loginSms/register/passwordReset) → `resources/views/auth/*.blade.php`; her auth sayfası kendi `/front/pages/<ad>/page.js` modülünü `pageScript` üzerinden yükler. SPA kabuğu `coalapp.blade.php` yalnızca `2f_success` + `type_key` session'ı varken açılır.
- **Mail akışı (aktif):** `MailService::renderHtmlMessage()` → `emails/layout.blade.php` (+`verify-email` include'ı, `SendNotificationMailJob`) → `sendMail()` → `NotificationLog`. `public/coaltheme/mail/*.falanml` ve `register_notification.html` bu zincire bağlı değil; eski sistemin ölü şablonları.
- **PDF akışı:** `POST /export/offer` → `ExportController@offerPdf` → dompdf `exports/offer.blade.php` (aktif); `exports/icmal.blade.php` çağıransız miras.
- **i18n:** `lang/{en,tr}.json` → `laravel-vue-i18n` eager glob (app.js) → `wTrans()`; anahtarlar eski apartman-yönetim domain'inden kalma.
- **Kritik bulgular:** (1) `login.blade.php` içinde `IS_TEST` ile hardcoded admin kimlik bilgisi basılıyor; (2) `register.blade.php`'de iki şifre input'u da `name="password"`; (3) `resources/markdown/policy|terms.md` ve `css/Pickle.md`/`app.css` tamamen boş/gereksiz; (4) `.falanml` setinin tamamı yetim.
