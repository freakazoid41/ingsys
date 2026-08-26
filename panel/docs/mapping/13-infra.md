# Altyapı (Console / Jobs / Providers) — Dosya Haritası
> Kapsam: 20 dosya (1 Kernel + 9 Command + 4 Job + 6 Provider) · Tamamı okundu.

## Zamanlanmış Görevler Tablosu

| Komut | Sınıf | Zamanlama | Tanım yeri | Not |
|---|---|---|---|---|
| `request:autoclose` | `RequestAutoclose` | `dailyAt('01:00')` | `app/Console/Kernel.php::schedule()` | Süresi biten talepleri kapatır |
| `active-sessions:clean` | `CleanActiveSessions` | `dailyAt('02:00')` | `app/Console/Kernel.php::schedule()` | Eski oturum kayıtlarını siler |
| `currency:cron` | `CurrencyCron` | `hourly()` — **YORUM SATIRI** | `routes/console.php` | Şu an zamanlanmıyor; elle çalışır |
| `notification:retry {id}` | `RetryNotificationSend` | zamanlanmıyor | — | Manuel retry |
| `mail:test` / `sms:test` / `recaptcha:test` | `SendTestMail` / `SendTestSms` / `VerifyRecaptcha` | zamanlanmıyor | — | Test/araç komutları |
| `permission:create` | `CreatePermissionCommand` | zamanlanmıyor | — | Manuel yetki oluşturma |
| `files:reencrypt-descriptions` | `ReencryptFileDescriptions` | zamanlanmıyor | — | Bir kerelik şifreleme migrasyonu |

Not: Proje Laravel 12; `bootstrap/app.php` komutları `routes/console.php` üzerinden yükler ve `app/Console/Commands` otomatik keşfedilir. `Console/Kernel.php::$commands` dizisi ve boş `commands()` metodu legacy kalıntısıdır; yine de schedule tanımları Kernel'den okunur.

## `app/Console/Kernel.php` (~50 satır)
- **Amaç:** Artisan komut kaydı ve cron zamanlaması.
- **Semboller:** `$commands` (6 komut), `schedule()`, `commands()` (boş).
- **İlişkiler:** `RequestAutoclose`, `CleanActiveSessions`, `SendTestMail`, `SendTestSms`, `RetryNotificationSend`, `VerifyRecaptcha` import eder. `CreatePermissionCommand`, `CurrencyCron`, `ReencryptFileDescriptions` listede YOK (Laravel 12 auto-discovery'ye güveniyor).
- **Bulgular:** `commands()` metodu boş — legacy Kernel ile Laravel 12 `routes/console.php` yaklaşımı karışık; iki ayrı schedule kaynağı var (Kernel + console.php'de yorumlu currency cron).

## `app/Console/Commands/CleanActiveSessions.php` (~33 satır)
- **Amaç:** `active_sessions` tablosundaki bayat kayıtları temizler.
- **Semboller:** `handle()` — `force_logout=true` ve `force_logout_at < now-24s` kayıtları + `last_seen < now-7g` kayıtları siler (opsiyonlarla ayarlanabilir).
- **İlişkiler:** `App\Models\ActiveSession`; Kernel'de `dailyAt('02:00')`.
- **Bulgular:** —

## `app/Console/Commands/CreatePermissionCommand.php` (~81 satır)
- **Amaç:** `sys_permission_catalog` tablosuna etkileşimli/argümanlı yeni yetki kodu ekler.
- **Semboller:** `handle()` — op_key benzersizlik ve parent varlık doğrulaması; metadata `group_key='op-perm'` sabit.
- **İlişkiler:** `App\Models\SysPermissionCatalog`; `file` cache store'dan `sys_permission_catalogs_all` ve `sys_notification_types_all` anahtarlarını temizler (PermissionService cache sözleşmesiyle uyumlu).
- **Bulgular:** —

## `app/Console/Commands/CurrencyCron.php` (~56 satır)
- **Amaç:** Döviz kurlarını TCMB'den çekip `currencies` tablosunu yeniler.
- **Semboller:** `handle()`, `clearOld()`.
- **İlişkiler:** `App\Classes\Currencies\TCMB`, `App\Classes\DataSources\Navision` (kullanılmıyor), `System_settings` (kullanılmıyor). `env('SYS_CUR')` ana para birimi.
- **Bulgular:** ⚠️ `clearOld()` yorumda "15 günden eski kayıtları siliyoruz" dese de `delete from currencies` ile **TÜM tabloyu siler**. ⚠️ Ayar-tabanlı TCMB/NAVISION switch'i tamamen yorum satırı; sabit TCMB kullanıyor. ⚠️ `routes/console.php`'de schedule yorumlu — cron fiilen çalışmıyor. `System_settings` ve `Navision` importları ölü.

## `app/Console/Commands/ReencryptFileDescriptions.php` (~118 satır)
- **Amaç:** `document_files.description` alanındaki şifreli dosya yollarını eski JSON formatından kompakt base64url formatına migrasyon eder.
- **Semboller:** `handle()`, `rollback(string $path)`.
- **İlişkiler:** `App\Providers\EncryptionProvider`; `document_files` tablosu (DB facade). Yedek: `storage/app/reencrypt-backup-<ts>.json`.
- **Akış:** Her satır için: eski format mı (base64→JSON, `salt` anahtarı) kontrol → decrypt → yeniden encrypt → doğrulama (decrypt(new)==plain) → önce yedek dosyası yazılır (yedek yazılamazsa hiçbir güncelleme yapılmaz) → update. `--dry-run` ve `--rollback=<yedek>` destekler.
- **Bulgular:** İyi mühendislik: yedek-önce, dry-run, rollback, round-trip doğrulama. Küçük not: yeni format zaten kompakt olan kayıtlar "salt yok" diye atlanır (idempotent).

## `app/Console/Commands/RequestAutoclose.php` (~61 satır)
- **Amaç:** `contract_end_date` bugün biten talepleri `doc_trans_request_end` statüsüne çeker.
- **Semboller:** `handle()`.
- **İlişkiler:** `Documents::tableList` (form-type=op-doc-request-form, type=op-doc-request, today-ended filtresi), `DocumentServiceProvider::setStatus`. Kernel'de `dailyAt('01:00')`.
- **Bulgular:** ⚠️ Sadece `$data[0]` işlenir — aynı gün biten birden fazla talep varsa geri kalanı kapanmaz. ⚠️ Kodda "TODO: implement actual autoclose logic" yorumu duruyor. `main_attr` filtresi yorumlu. `today-ended` filtresi `d/m/Y` formatıyla string-eşleşme yapıyor (kırılgan).

## `app/Console/Commands/RetryNotificationSend.php` (~52 satır)
- **Amaç:** Başarısız SMS/e-posta bildirimini log ID'siyle yeniden gönderir.
- **Semboller:** `handle()` — `--queue` ile `RetryNotificationSendJob::dispatch`, yoksa senkron `MailService/SmsService::retryNotificationLog`.
- **İlişkiler:** `App\Models\NotificationLog`, `App\Jobs\RetryNotificationSendJob`, `MailService`, `SmsService`.
- **Bulgular:** —

## `app/Console/Commands/SendTestMail.php` (~73 satır)
- **Amaç:** `mail:test` — MailService üzerinden test e-postası; `--use-relay` veya `MAIL_USE_RELAY` env ile relay modu.
- **Semboller:** `handle(MailService $mailService)` (DI).
- **İlişkiler:** `App\Services\MailService::sendMail`, `renderHtmlMessage`; `config('mail.to.address')` / `MAIL_TO_ADDRESS`.
- **Bulgular:** Dönen `used_mail_info`'yu terminale basar — SMTP host/kullanıcı bilgisi loga düşebilir (düşük risk, CLI aracı).

## `app/Console/Commands/SendTestSms.php` (~45 satır)
- **Amaç:** `sms:test` — SmsService ile test SMS.
- **İlişkiler:** `App\Services\SmsService::sendSms`.
- **Bulgular:** ⚠️ Varsayılan alıcı gerçek bir numaraya (`5438826976`) hardcode edilmiş.

## `app/Console/Commands/VerifyRecaptcha.php` (~71 satır)
- **Amaç:** `recaptcha:test` — reCAPTCHA token'ını yapılandırılmış verify URL'sine karşı doğrular.
- **Semboller:** `handle()` — `Http::asForm()->post(verify_url, {secret, response, remoteip?})`.
- **İlişkiler:** `config('services.recaptcha.*')`, `RECAPTCHA_TEST_TOKEN` env.
- **Bulgular:** —

## `app/Jobs/RetryNotificationSendJob.php` (~52 satır)
- **Amaç:** Bildirim retry'nin kuyruklu hali.
- **Semboller:** `__construct(int $notificationLogId)`, `handle()`.
- **Kuyruk:** `ShouldQueue`; `notification:retry --queue` ile dispatch edilir, `onQueue` çağrılmaz → varsayılan kuyruk.
- **İlişkiler:** `NotificationLog`, `MailService::retryNotificationLog` (type=email), `SmsService::retryNotificationLog` (type=sms). Sonuç sadece Log'a yazılır.
- **Bulgular:** `$tries`/`$backoff` tanımsız; log bulunamazsa sessizce biter.

## `app/Jobs/SendInfoMailJob.php` (~65 satır)
- **Amaç:** Genel bilgilendirme e-postası (başlık + serbest HTML body).
- **Semboller:** `__construct($email,$header,$body,$sysCode)`, `handle()`.
- **Kuyruk:** `ShouldQueue`; `EmailServiceProvider::sendinfoMail` üzerinden `->onQueue('default')` ile tetiklenir — ancak **`sendinfoMail`'in uygulama içinde çağıranı yok** (ölü yol olabilir).
- **İlişkiler:** `MailService::renderHtmlMessage` + `sendMail`; `$GLOBALS['SYS_CODE']` fallback.
- **Bulgular:** Tüm hatalar try/catch ile yutulup loglanıyor → kuyruk worker'ı retry yapmaz, hata sessizleşir. Body HTML'i sanitize edilmeden şablona gömülür (içerik sistem kaynaklıysa kabul edilebilir).

## `app/Jobs/SendResetMailJob.php` (~69 satır)
- **Amaç:** Şifre sıfırlama e-postası ("Şifreniz Sıfırlandı", yeni şifre plaintext gövdede).
- **Semboller:** `__construct($email,$password,$sysCode)`, `handle()`.
- **Kuyruk:** `ShouldQueue`; `EmailServiceProvider::sendresetMail` → `->onQueue('default')`. Tetikleyici: `PersonsController` (şifre reset akışı, satır ~424).
- **Bulgular:** ⚠️ **Yeni şifre düz metin olarak e-postayla gönderiliyor ve kuyruk payload'ında (SeriaizesModels değil ama queue payload'u) + Log satırlarında açıkça geçiyor** (`sendresetMail` Log::info'ya `password` alanını da koyuyor). Ciddi güvenlik bulgusu: şifreler log dosyalarında ve queue backend'inde (ör. Redis/DB) düz metin saklanır. ⚠️ E-posta içeriği `e($this->password)` ile escape ediliyor (iyi).

## `app/Jobs/SendNotificationMailJob.php` (~502 satır)
- **Amaç:** Sistemin ana bildirim işi — payload `type`'ına göre 7 senaryo dağıtır.
- **Semboller:** `handle()` (switch), `clientRegister`, `clientOfferGive($payload,$isUpdate)`, `clientOfferStatus`, `clientActivation`, `clientChanged`, `clientFileStatus`, `informSystemUsers($subject,$html,$opKey,$attachments)`, `buildOfferRevisionHtml`, `formatOfferFieldLabel` (~35 alan etiketi TR), `formatOfferFieldValue`, `isFilePayloadValue`, `renderEmailHtml`, `log`, `failed`.
- **Kuyruk:** `ShouldQueue`; tüm tetiklemeler `EmailServiceProvider` üzerinden `->onQueue('default')`.
- **Type dağılımı:** `offerRevision`→clientOfferGive(update), `offerGiven`→clientOfferGive, `offerStatus`→clientOfferStatus, `register`→clientRegister, `activation`→clientActivation, `clientUpdate`→clientChanged, `cliFileStatus`→clientFileStatus; bilinmeyen type loglanıp düşer.
- **İlişkiler:** `PersonsServiceProvider::getNotificationUsers($opKey)` + `getPerson` (yetkili kullanıcıları bulma, `notif-00/01/02/03` op key'leri), `DocumentServiceProvider::getFormData` (clientOfferStatus'ta müşteri kontakları), `MailService::sendMail`, `EncryptionProvider` (teklif ek dosyalarının şifreli yolunu çözme), `view('emails.verify-email')` (aktivasyon).
- **Akış:** Sistem kullanıcılarına `informSystemUsers` ile (contacts JSON'unda `contmail`/`contphone` aranır, yoksa `person->email`), müşteri kontaklarına `cont_email`/`cont_phone` anahtarlarıyla mail gider. `sendSms` bayrağı var ama **SMS sadece loglanıyor, gerçek gönderim yok**.
- **Bulgular:** ⚠️ SMS yolu stub — `smsEnabled` true olsa bile sadece "Sending notification SMS" logu atılır. ⚠️ `clientOfferGive` içinde `$offer["offer_type"]`'ın `**` içerdiği ve explode'un 2 eleman döndürdüğü varsayılır — veri bozuksa undefined offset. ⚠️ `informSystemUsers` her kullanıcı için ayrı `getPerson` + contact döngüsü + N×sendMail; büyük gruplarda yavaş. ⚠️ CLI'de çalışınca her log satırını `echo` ile basar (log helper). ⚠️ `clientActivation` sonucu `$result`'e atanıp kontrol edilmeden "completed" loglanır. `# code...` artık yorumlar mevcut.

## `app/Providers/AppServiceProvider.php` (~46 satır)
- **Amaç:** Tek gerçek Laravel provider — container kaydı.
- **Bind:** `register()` içinde `Fruitcake\Cors\CorsService` **singleton**; `config('cors')` değerlerini recursive resolver'dan geçirir (callable option'ları çağırır).
- **Boot:** boş.
- **Bulgular:** ⚠️ `fruitcake/laravel-cors` Laravel 10+ ile deprecated (Laravel'in native `HandleCors` middleware'i var) — teknik borç.

## `app/Providers/DocumentServiceProvider.php` (~886 satır)
- **Amaç:** Belge (document) alanının çekirdek servis katmanı — kayıt, okuma, silme, statü, export, dosya durumları. (Laravel provider değil; `__construct` boş, `register/boot` yok, her yerde `new DocumentServiceProvider()` ile kullanılıyor.)
- **Semboller:**
  - `registerContent($id,$requestData,$files)` — belge create/update; DB transaction; `main_*` alanları `documents` tablosuna, dinamik alanlar `sys_con_ops`+`sys_con_entities` EAV yapısına, dosyalar `addFileToDb` helper'ı ile `document_files`'a; `op-doc-request/offer` için `req_no` sayacı ve `rev_date`; `target_type`'tan Türkçe-karakter-normalize `grp_code` üretir; before/after `UserLog` yazar; `doc_trans_created` transaction'ı açar.
  - `getFormData($id)` — belge + dinamik form verisi (PostgreSQL `json_build_object`/`json_agg` ile dosya son-statüsü dahil).
  - `removeContent($id)` — soft-delete (`status=0`); `op-doc-client` ise bağlı reseller kullanıcıları pasife çeker.
  - `removeTransaction($id)`, `getExportData($type)` (flats/accounts/meetings — legacy apartman yönetimi kalıntısı!), `setStatus($id,$statusKey,$note)`, `getTransExportData($id)`, `documentFileStatus($id,$statusKey,$note)`, `getDocumentFiles($documentId)`, `getRejectedClientFiles($list)` (CTE + DISTINCT ON), `getAwaitingClientFiles()`, `updatePersonClients($documentId,$clientData)`.
- **İlişkiler:** Models: `Sys_options`, `Documents`, `Document_files`, `Sys_con_entities`, `Sys_con_ops`, `UserLog`, `User`, `Transactions`. Çağıranlar: `RequestAutoclose`, `SendNotificationMailJob`, `PersonsServiceProvider`, `ReportServiceProvider`, `DocumentController`, helper'lar.
- **Bulgular:** ⚠️ **SQL injection:** `getFormData`, `removeContent`, `getDocumentFiles`, `getRejectedClientFiles`, `updatePersonClients` raw SQL'e `$id`/`$documentId`/`implode($list)` değerlerini tırnak içinde doğrudan gömüyor (hiçbiri binding değil). qnid'ler dışarıdan geliyorsa kritik. ⚠️ `setStatus` catch bloğunda `catch(Exception $e)` — namespace'de `Exception` import edilmemiş → `App\Providers\Exception` aranır, **hiçbir exception yakalanmaz** (fatal error yol açar). ⚠️ `getExportData`'daki flats/accounts/meetings case'leri kömür projesinde ölü kod (önceki projeden miras). ⚠️ `registerContent`'te `Sys_options::where(...)->first()->id` null-safe değil (birkaç yerde `?? 0` var, hepsinde yok). ⚠️ `removeContent` içinde büyük yorumlanmış hard-delete bloğu duruyor.

## `app/Providers/EmailServiceProvider.php` (~112 satır)
- **Amaç:** Bildirim e-postaları için ince dispatch katmanı (facade-benzeri; provider değil).
- **Semboller:** `sendregisterMails` (type=register), `sendOfferGiven` (offerGiven/offerRevision), `sendOfferStatus` (offerStatus), `sendapproveMails` (activation), `sendClientChanged` (clientUpdate), `sendClientFileStatus` (payload olduğu gibi), `sendresetMail` → `SendResetMailJob`, `sendinfoMail` → `SendInfoMailJob`.
- **Kuyruk:** Tüm dispatch'ler `->onQueue('default')`; her metotta `$GLOBALS['SYS_CODE']` payload'a eklenir; dispatch hataları try/catch ile loglanır.
- **Tetikleyiciler:** `AuthController` (kayıt → sendregisterMails ×2 yer), `DocumentController` (teklif verme/revizyon → sendOfferGiven; durum → sendOfferStatus; dosya durumu → sendClientFileStatus; müşteri güncelleme → sendClientChanged), `PersonsController` (onay → sendapproveMails; şifre sıfırlama → sendresetMail). `sendinfoMail`'in çağıranı yok.
- **Bulgular:** ⚠️ `sendresetMail` log satırına **düz metin şifreyi** yazıyor (`['email' => ..., 'password' => $password]`) — hem info hem error branch'te. Kritik. ⚠️ `sendinfoMail` ölü yol.

## `app/Providers/EncryptionProvider.php` (~119 satır) — ÖZEL ŞİFRELEME
- **Amaç:** `document_files.description` içinde saklanan dosya yollarını şifrelemek için CryptoJS-uyumlu, elle yazılmış simetrik şifreleme. Laravel'in `Crypt`'i KULLANILMIYOR.
- **Algoritma:** AES-128-CBC (`$encryptMethod`, `setCipherMethod` ile değişebilir). Anahtar türetme: `hash_pbkdf2('sha512', $key, $salt, 999, 128)` → 128 hex karakter (64 byte) → `hex2bin` → 512-bit; AES-128 ilk 16 byte'ı kullanır (fazlası yok sayılır). IV: 16 byte random. Varsayılan anahtar: **`'pickle'` (hardcoded)**.
- **İki format (encrypt hep kompakt yazar, decrypt ikisini de okur):**
  1. **Eski/legacy:** `base64(JSON{ciphertext(b64), iv(hex), salt(hex), iterations})` — CryptoJS uyumlu (StackOverflow referanslı). Iterations ≤0 ise 999'a düşer.
  2. **Kompakt (yeni):** `base64url(salt[16] . iv[16] . ciphertext)` — URL-safe, `+/`→`-_`, padding `=` kırpılır. Decrypt'te salt ilk 16, iv ikinci 16 byte.
- **Kullanım:** `DocumentHelpers` (dosya indirme/silme/encrypt satır 79, 494, 518), `Document_files` model (booted delete hook), `SendNotificationMailJob` (ek dosya path çözme), `ExportController`, `ReencryptFileDescriptions` (format migrasyonu). `ReencryptFileDescriptions` tam da eski→kompakt migrasyonu için yazılmış.
- **Bulgular:** ⚠️ **Sabit anahtar `'pickle'` kaynak kodda** — repo'ya erişen herkes tüm dosya yollarını çözer; key env'den gelmiyor. ⚠️ PBKDF2 999 iterasyon düşük (öneri ≥100k). ⚠️ `encryptMethodLength()` metodu tanımlı ama hiç kullanılmıyor (ölü). ⚠️ decrypt'te `catch (Exception $e)` import'suz → asla yakalanmaz; PHP 8'de `hex2bin` zaten exception atmaz (warning + false döner → `openssl_decrypt` false dönebilir, çağıran null/false kontrolü yapmak zorunda). ⚠️ Ciphertext'te **MAC yok** — CBC malleable; bütünlük doğrulanmıyor. ⚠️ Sadece dosya *yolu* şifreleniyor; dosya içeriği diskte açık.

## `app/Providers/PersonsServiceProvider.php` (~687 satır)
- **Amaç:** Personel/kullanıcı alanının servis katmanı — kişi CRUD, kullanıcı hesabı, yetki/bildirim grupları, müşteri bağlantıları.
- **Semboller:** `getPermissionCacheStore`, `getPersonTypes`, `getUserPermissionsByPersonId` (parameterized ✓), `upsertConnectionEntity` (EAV upsert yardımcısı), `setPerson` (büyük: kişi + user + yetki + tesis + müşteri bağları, transaction, `PermissionService::forceLogoutPerson`/`refreshUserPermissionCache`/`bumpUserPermissionVersion`), `getPerson`, `getPersonsExportData`, `removeContent` (soft-delete), `roleTemplateTrans` (RoleTemplateService proxy), `updateUserPermissions($role,$permissions)` (rol bazlı toplu yetki + cache refresh), `updateUserNotificationGroups`, `setClientToPerson` (müşteri kaydı + bağ kurma), `clientPermInfo` (canProceed/canResponse — imza dosyası onay kontrolü), `getNotificationUsers($opKey,$personId)`.
- **İlişkiler:** Models `Sys_options/Sys_con_ops/Sys_con_entities/Persons/User/UserLog`; `RoleTemplateService`, `PermissionService`, `DocumentServiceProvider`, `EmailServiceProvider` (import edilmiş ama doğrudan kullanılmıyor). Çağıranlar: `SendNotificationMailJob`, `ReportServiceProvider`, `PersonsController`.
- **Bulgular:** ⚠️ **SQL injection:** `getPerson` (`$id`/`$search` string gömme), `clientPermInfo` (`$personQnId` gömme), `getNotificationUsers` (`$opKey` LIKE'a ve `$personId` gömme). ⚠️ `setPerson` şifreyi `Hash::make` ile kaydediyor (iyi) ama `$user['password']` request'ten geliyor, loglanmıyor (iyi). ⚠️ `setClientToPerson` catch'te `print_r` ile hata basıyor (üretimde output sızıntısı). ⚠️ `getPersonsExportData`'da contacts JSON tamir zinciri (`str_replace` ile `{"{`→`[{`) — veri bütünlüğü kokusu. ⚠️ `removeContent`'te soft-delete status `'0'` string; User status ile aynı kolon tipi tutarlılığı belirsiz.

## `app/Providers/ReportServiceProvider.php` (~463 satır)
- **Amaç:** Admin bildirim listeleri + dashboard istatistikleri.
- **Semboller:** `getAdminNotifications($notifKey)` (notif-00→bekleyen kullanıcı başvuruları, notif-01→bekleyen müşteri dosyaları, notif-02→teklifler, notif-03→revize teklifler), `getUserNotifications` (offer-revision-request, sadece reseller), `getAwaitingUserRequests` (`user_status=-1`), `getAwaitingClientFiles`, `getOffers($type)`, `dashboardInfo($type,$addional)` dispatcher, `dashboardTopInfo`, `dashboardMonthlyOffers` (status grupları + renk haritası, draft/boş→sended merge), `dashboardMonthlyDistribution` (Çates/Yatağan/Her İkisi bazında dağılım), `dashboardImportantInfo` (sözleşme/sevkiyat başlangıç-bitiş takvimi).
- **İlişkiler:** `PersonsServiceProvider::getNotificationUsers` (yetki kontrolü — kullanıcı grupta değilse boş döner), `DocumentServiceProvider::getAwaitingClientFiles`, `Documents::tableList`, `User::tableList`, `Sys_options`. Çağıran: rapor/dashboard controller'ları.
- **Bulgular:** ⚠️ `dashboardInfo('clienttopstatus')` → `dashboardClientTopStatus()` çağırıyor ama **metot sınıfta tanımlı değil** — bu type istenirse fatal Error. ⚠️ `dashboardImportantInfo`'da `$docNo` döngü içinde koşullu tanımlanıyor; bulunamazsa bir önceki satırın değeri sızabilir (null başlatılmamış). ⚠️ `dashboardMonthlyDistribution`'da aynı string-arama bloğu iki kez tekrarlanmış (kopyala-yapıştır). ⚠️ Aylık filtre `created_at` string'inde `'-m-'` araması — `tableList`'in 'monthly' filter'ına bağlı, kırılgan format.

## Alan Özeti
- **Rol:** Bu dilim uygulamanın "arka plan motoru": zamanlanmış bakım (oturum temizliği, talep kapatma), kuyruklu bildirim altyapısı (e-posta/SMS), ve UI'dan bağımsız üç büyük domain servisi (Document/Persons/Report).
- **Bildirim akışı:** Controller → `EmailServiceProvider` (ince wrapper, `onQueue('default')`) → `SendNotificationMailJob`/`SendResetMailJob`/`SendInfoMailJob` → `MailService`; alıcılar `PersonsServiceProvider::getNotificationUsers` + `PermissionService`'in `notif-XX` gruplarından çözülür. Retry: `notification:retry` komutu veya `RetryNotificationSendJob`.
- **Şifreleme:** `EncryptionProvider` Laravel Crypt dışı, AES-128-CBC+PBKDF2, hardcoded 'pickle' anahtarlı özel bir yapı; sadece `document_files.description` (dosya yolları) için kullanılır; `ReencryptFileDescriptions` komutu eski→kompakt format migrasyonunu yedekli/rollback'li yapar.
- **En kritik bulgular:** (1) şifre plaintext log + e-posta (`SendResetMailJob`/`EmailServiceProvider`), (2) yaygın raw-SQL string interpolation (Document/Persons provider'ları), (3) hardcoded şifreleme anahtarı + MAC'siz CBC, (4) tanımsız `dashboardClientTopStatus` metodu, (5) `CurrencyCron::clearOld` tüm tabloyu siliyor ve cron yorumlu, (6) SMS bildirim yolu stub.
- **Mimari koku:** "ServiceProvider" adı altındaki sınıflar Laravel provider'ı değil (`register/boot` yok, `new` ile instantiate ediliyor); `AppServiceProvider` tek gerçek provider (CORS singleton). Bu sınıflar fiilen domain servis katmanıdır.
