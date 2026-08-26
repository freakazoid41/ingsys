# Servisler / Sınıflar / Helper'lar / Rules / Policies — Dosya Haritası

> Kapsam: 14 dosya · Tamamı okundu (Services 5, Classes 3, Helpers 4, Rules 1, Policies 1).

## `app/Services/ExportService.php` (~117 satır)
- **Amaç:** PhpSpreadsheet ile generic Excel (XLSX) export üretir; stream download döner.
- **Semboller:**
  - `exportExcel(array $headers, array $data, string $filename = 'export.xlsx', ?callable $valueMapper = null): StreamedResponse` — `$headers` assoc (key=>label) veya düz liste olabilir; key'ler veri satırındaki alan adı olarak kullanılır. `$valueMapper($row, $key, $label, $rowIndex, $columnIndex)` opsiyonel özel değer çözümleyici. Uzantı `.xlsx` değilse ekler. Başlık satırı 1. satıra, veri 2. satırdan itibaren yazılır; tüm kolonlar AutoSize. `response()->streamDownload` ile `php://output`'a yazar.
  - `normalizeValue($value): string` (protected) — bool→'1'/'0', null→'', array/object→`json_encode(JSON_UNESCAPED_UNICODE)`, diğer→string cast.
- **İlişkiler:** Rapor/export endpoint'leri tarafından çağrılır (HttpRead dilimindeki controller'lar). PDF üretimi bu sınıfta YOK — sadece Excel. PDF tarafı `DocumentHelpers::signDocument` (FPDI) üzerinden yürüyor.
- **Bulgular:** Spreadsheet'e `addSheet($worksheet, 0)` ile manuel sheet ekleniyor; varsayılan boş sheet kalabilir (küçük). Başka sorun yok.

## `app/Services/MailService.php` (~394 satır)
- **Amaç:** E-posta gönderimi (doğrudan veya relay SMTP üzerinden), HTML şablon render, NotificationLog ile durum takibi, e-posta+SMS birleşik doğrulama kodu gönderimi.
- **Semboller:**
  - `sendMail(array $data, ?NotificationLog $existingLog = null): array` — Ana metot. Validasyon: `to` zorunlu email, `subject` zorunlu, `body` veya `html`'dan en az biri zorunlu. **Relay mantığı:** `$data['use_relay'] ?? config('mail.use_relay') ?? env('MAIL_USE_RELAY')` bool'a çevrilir; true ise relay host/port/encryption/username/password `$data` → `env('MAIL_RELAY_*')` (varsayılan host `intmail.aydemenerji.com.tr:25`) sırasıyla alınır ve `config([...])` ile runtime'da `mail.mailers.smtp.*` override edilir, `Mail::mailer('smtp')` kullanılır. Relay aktifken ve ayrıca her durumda Symfony transport stream'inde **TLS doğrulaması kapatılır** (`allow_self_signed`, `verify_peer=false`, `verify_peer_name=false`). From çözümleme: payload `from` (array|string) / `from_email` / `from_name` → geçersizse `MAIL_FROM_ADDRESS{SYS_CODE}` env → son çare `config('mail.from.*')`; kaynak `payload|sysCode|config` olarak loglanır. Ekler: string path, `['path','name','mime']` veya `['data','name','mime']` (attachData) destekler; mime `detectMimeType` ile finfo üzerinden bulunur. `html` varsa `$mailerDriver->html()`, yoksa `->raw()`. Başarı/hata `NotificationLog`'a `status/attempts/sent_at/error_message/detail` olarak yazılır; `mail.send.attempt/success/exception` log kanallarına `used_mail_info` düşülür.
  - `createNotificationLog(array $data): NotificationLog` (protected) — type=email, status=pending, payload=tüm data ile kayıt açar.
  - `renderHtmlMessage(array $data): string` — `emails.layout` view'ını title/header/intro/content/ctaUrl/ctaText/subtext/footerText + sysCode ile render eder.
  - `retryNotificationLog(NotificationLog $log): array` — log payload'ından sendMail'i tekrar çağırır.
  - `detectMimeType(string $path): ?string` (protected) — finfo → mime_content_type fallback.
  - `sendSms(array $data): array` — Adı yanıltıcı: e-posta + SMS birleşik gönderim. `email` varsa sendMail (konu varsayılan 'Doğrulama Kodu'), `sms_to|sms|phone|phone_number` varsa `SmsService::sendSms($target, $text, originatorId, validityPeriod=1440, clientId)` çağırır. İkisi de yoksa/içerik yoksa hata döner.
- **İlişkiler:** `App\Models\NotificationLog`, `App\Services\SmsService`, `emails.layout` view; bildirim controller/job'ları tarafından kullanılır. `$GLOBALS['SYS_CODE']` global'ine bağımlı.
- **Bulgular:**
  - 🔴 TLS sertifika doğrulaması her gönderimde kapatılıyor (MITM riski) — kurum içi relay için bilinçli olabilir ama kodda zorunlu.
  - 🔴 `'relay_password_masked' => $relayPassword ?? '***'` — parola varsa **maskelenmeden açık yazılıyor** (`used_mail_info` → log + NotificationLog.detail sızıntısı).
  - 🔴 `sendSms` içinde yorum satırı backdoor: `//if($email == '$email') $smsTarget = '5438826976';` ("kontent back door").
  - 🟠 `catch (Exception $e)` — `Exception` import edilmemiş (`App\Services\Exception` çözümlenir, asla yakalanmaz); `Throwable` olmalıydı. SmsService bloğundaki hatalar uncaught gider.
  - 🟠 config override (`config([...])`) request-scoped global state değiştiriyor; uzun yaşayan worker'larda relay ayarı sonraki gönderimlere sızabilir.

## `app/Services/PermissionService.php` (~208 satır)
- **Amaç:** Kullanıcı yetkilerinin cache + session tabanlı çözümlemesi, versiyonlama ile invalidation, zorla oturum kapatma.
- **Semboller:**
  - `__construct(PersonsServiceProvider $personProvider = null)` — cache store `config('permissions.cache_store', env('PERMISSIONS_CACHE_STORE','file'))`.
  - `getCacheStore(): string`, `getCache()` — Cache::store erişimi.
  - `getUserPermissionCacheKey($personId)` → `permissions.user.{id}`; `getUserPermissionVersionCacheKey($personId)` → `permissions.user.version.{id}`.
  - `cacheUserPermissions($personId, array $permissions): string` — 30 gün TTL; versiyon = `time()`.
  - `getCachedUserPermissions($personId): array` — miss'te `refreshUserPermissionCache` tetikler.
  - `getCachedUserPermissionVersion($personId): string` — miss'te refresh.
  - `refreshUserPermissionCache($personId)` — `personProvider->getUserPermissionsByPersonId` ile DB'den çekip cache'ler; `['permissions','version']` döner.
  - `bumpUserPermissionVersion($personId, $newCurrentStatus = null): string` — yeni versiyon yazar, perm cache'i siler, ilgili user'ların tüm `ActiveSession` kayıtlarında `permission_version` (ve opsiyonel `current_status`) günceller; hata yutulur.
  - `forceLogoutPerson($personId, string $reason = null): bool` — persone ait user'ların ActiveSession'larına `force_logout=true` + sebep yazar, UserLog'a kayıt atar.
  - `invalidateUserPermissionCache($personId): void` — iki cache key'i de siler.
  - `has($user, string $permissionKey): bool` — **Yetki çözümleme kalbi.** Sıra: user/person_id yoksa false → `$permissionKey === 'all'` ve email `env('DEV_ADMIN')` ise true → `ensureSessionFreshness` → session `sper-{key}` true ise true → cache'li permission dizisinde `in_array` strict → DEV_ADMIN fallback true → false.
  - `loadPermissionsToSession($user)` — eski `sper-*` session key'lerini temizler, `perms` + `permission_version` + her perm için `sper-{perm}=true` yazar.
  - `ensureSessionFreshness($user)` — session'daki `permission_version` cache'teki ile uyuşmuyorsa session'ı yeniden yükler.
- **İlişkiler:** `App\Providers\PersonsServiceProvider` (yetki kaynağı), `App\Models\ActiveSession`, `users` tablosu. `PermissionHelpers::checkPerm` üzerinden tüm uygulamaya açılır.
- **Bulgular:**
  - 🔴 `forceLogoutPerson` içinde `UserLog` ve `Sys_options` **import edilmemiş** → `App\Services\UserLog` çözümlenir, ModelNotFound/fatal; try-catch yuttuğu için sessizce `false` döner (log asla yazılmaz). Gizli bug.
  - 🟠 DEV_ADMIN backdoor'u: email `env('DEV_ADMIN')` olan kullanıcı her yetkiye sahip (hem 'all' hem genel fallback).
  - 🟠 `cacheUserPermissions` versiyonu `time()` string'i — aynı saniyede iki bump çakışabilir.
  - 🟡 Session'a perm başına ayrı key (`sper-*`) yazmak session boyutunu şişirir.

## `app/Services/RoleTemplateService.php` (~291 satır)
- **Amaç:** Rol şablonları, yetki kataloğu ve bildirim tiplerinin DB + file-cache (1 saat TTL) ile yönetimi; PersonsServiceProvider'ın eski dosya I/O'sunun yerini alır.
- **Semboller:**
  - Sabitler: `CACHE_TTL=3600`, `CACHE_KEY_ROLES/PERMISSIONS/NOTIFICATIONS`.
  - `getRoleTemplates(): array` — file cache'ten `SysRoleTemplate::all()->toJsonFormat()`.
  - `saveRoleTemplates(array $roles): bool` — her rol için id → op_key → name sırasıyla eşleşme bulur, `updateOrCreate`; op_key yoksa mevcut korunur ya da `'role-'.date('Ymdhi')` üretilir. Her değişiklik `SysRoleTemplateAudit::logChange` ile audit'lenir, sonunda cache invalidate.
  - `deleteRoleTemplate($id): ?array` — siler, audit'e `deleted` + old_data yazar, kalan rolleri döner.
  - `getPermissionCatalogs(): array` — cache'li hiyerarşik ağaç (`buildPermissionTree`).
  - `buildPermissionTree(): array` (private) — `SysPermissionCatalog` flat kayıtlarından `metadata.parent_code` boş olanları kök yapar; node: `parent_id,title,ttitle,ctitle,group_key,op_key,childs`.
  - `getPermissionChildren(string $parentCode, array $allPerms): array` (private) — rekürsif çocuk çözümleme.
  - `getNotificationTypes(): array` / `buildNotificationTree(): array` — `SysNotificationType` düz liste (childs her zaman boş).
  - `getRoleTemplate($id)`, `getPermission($code)`, `getNotificationType($code)` — tekil getirme (cache'siz).
  - `invalidateCaches(): void`, `getCacheStats(): array`.
- **İlişkiler:** `SysRoleTemplate`, `SysPermissionCatalog`, `SysNotificationType`, `SysRoleTemplateAudit` modelleri; yetki/rol yönetim ekranlarının backend'i.
- **Bulgular:**
  - 🟡 `buildPermissionTree`/`getPermissionChildren` O(n²) (her düğüm için tüm liste taranıyor) — katalog küçükse sorun değil.
  - 🟡 Cache hep `file` store'a hardcode; PermissionService'in konfigüre edilebilir store'u ile tutarsız.
  - 🟡 `saveRoleTemplates` partial failure'da false döner ama o ana kadarki update'ler commitlenmiş kalır (transaction yok).

## `app/Services/SmsService.php` (~380 satır)
- **Amaç:** İletişim Makinesi (iletisimmakinesi.com) UserGatewayWS/SMSGatewayWS REST entegrasyonu: token, originator, servis izinleri, SMS gönderimi + NotificationLog takibi.
- **Semboller:**
  - `__construct()` — tüm kimlik bilgileri `config('services.iletisimmakinesi.*')`'den (base_url varsayılan `https://live.iletisimmakinesi.com/api/UserGatewayWS/functions`, service_id '7'); son satırda `getToken()` çağrılır (her instance'da config doğrulaması zorunlu).
  - `validateConfig(): void` (protected) — username/password/vendorId/apiKey/customerCode boşsa Exception.
  - `getToken(): string` — **Token çözümleme:** `Cache::remember('sms::iletisimmakinesi::token', 60*24)` (dakika → 24 saat? Laravel 12'de remember saniye alır; 1440 saniye = 24 dk). `/authenticate` GET (userName/userPass/customerCode/apiKey/vendorCode urlencode). Yanıt JSON ise `token|Token|response.token|tokenCode`; XML ise `//AUTHORIZATION_WITH_TOKEN/TOKEN_NO` xpath; son çare regex `<TOKEN_NO>(.*?)</TOKEN_NO>`. Bulunamazsa Exception.
  - `getServicePermissions(): array` — `/getServicePermissions` GET; XML `//PERMISSIONS/PERMISSION` → service_id/service_name/plan_type/plan_type_id; JSON fallback.
  - `createNotificationLog(...)` (protected) — type=sms pending log.
  - `retryNotificationLog(NotificationLog $log): array` — payload'dan sendSms retry.
  - `getOriginators(): array` — `/getOriginators` GET (token + serviceId); XML `//ORIGINATORS/ORIGINATOR` → id/service_id/paymentProfile_id/value.
  - `sendSms(string $to, string $message, ?string $originatorId = null, int $validityPeriod = 1440, ?string $clientId = null, ?NotificationLog $existingLog = null): array` — **Gönderim akışı:** originator yoksa `getOriginators()`'tan ilkini al; clientId = param → config → vendorId; URL'de `UserGatewayWS`→`SMSGatewayWS` replace + `/sendSMS`; `clientTransactionId = clientId-microtime-rand`; form POST (token, phoneNumbers=JSON dizi, templateText, originatorId, isUTF8Allowed=false, isNLSSAllowed=false, validityPeriod, clientTransactionId, serviceId). Yanıt: XML `<HERMES_RESPONSE>` ise `STATUS->CODE === '0'` başarı; JSON ise `status === 'success' || status === 0`. Her dalda NotificationLog güncellenir (sent/error, attempts++, detail).
- **İlişkiler:** `NotificationLog`, `MailService::sendSms` (birleşik gönderim), config/services.php `iletisimmakinesi` bloğu.
- **Bulgular:**
  - 🟠 `Cache::remember(..., 60*24)` — süre birimi belirsiz/yanlış olabilir (24 dk vs 24 saat); token erken expire olursa eski token ile istek atılır (yenileme yok, 401'de cache bust edilmiyor).
  - 🟠 Kimlik bilgileri query string ile GET gönderiliyor (url loglarına/erişim loglarına sızabilir); `Log::debug('SMS token body')` token içeren body'siyle logluyor.
  - 🟡 Token cache key'i sabit — config değişse bile eski token kullanılır.
  - 🟡 Hata mesajlarında response body'sinin ilk 400 char'ı dışarı dönüyor (bilgi sızıntısı sınırda).

## `app/Classes/Utils.php` (~15 satır)
- **Amaç:** Cron log yardımcısı minik sınıf.
- **Semboller:** `infoPrint($message)` — `Log::channel('cron')->info($message)` + stdout'a tarih ve `print_r` çıktısı.
- **İlişkiler:** `TCMB` extends eder.
- **Bulgular:** `print_r(date('Y-m-d H:i').' => '.PHP_EOL)` — tarih+ok ayrı satırda basılır (kozmetik tuhaflık).

## `app/Classes/Currencies/TCMB.php` (~70 satır)
- **Amaç:** TCMB `today.xml`'den günlük kurları çekip `currencies` tablosunu yeniden doldurur (cron).
- **Semboller:**
  - `__construct($mainCur)` — simplexml/curl uzantı kontrolü; `$this->mainCur` (dinamik property, PHP 8.2 deprecated); infoPrint ile başlangıç logu.
  - `fetchCur()` — **Döviz çekme akışı:** cURL ile `https://www.tcmb.gov.tr/kurlar/today.xml` → `$this->curData`; `Currencies::truncate()`; `env('SYS_CUR_INFO')` virgüllü hedef kurlar için her biri: main/target `Sys_options` id çözümle, `amount = curConverter(main, c, 1)`, kaydet + logla.
  - `curConverter($from='TRY', $to='USD', $val=1)` — XML'de `CurrencyCode` eşleşen `BanknoteSelling` değerlerini bulur; `round(($to / $from) * $val, 10)` döner.
- **İlişkiler:** `App\Models\Currencies`, `App\Models\Sys_options`; cron/schedule'dan tetiklenir (InfraRead dilimi).
- **Bulgular:**
  - 🔴 `truncate()` transaction'sız — cURL/parse sonrası hata olursa tablo boş kalır (veri kaybı penceresi). Üstelik curl hatası kontrol edilmiyor (`curl_exec` false olabilir).
  - 🔴 `catch (Exception $e)` import'suz → `App\Classes\Currencies\Exception` asla yakalanmaz; catch bloğundaki `$this->infoPrint('cron')->info(...)` zaten bozuk (infoPrint void döner).
  - 🟠 Kur matematiği ters görünüyor: `(to/from)*val` — main=TRY, target=USD için USD satış/1 beklenirken bölme yönü `to/from` (USD/TRY=~0.03) veriyor; `from=TRY` XML'de hiç eşleşmediği için from=1 kalıp sonuç = USD BanknoteSelling oluyor. TRY bazlı tabloda `target->amount` = 1 TRY'nin hedef karşılığı ise doğru; 1 birim hedef = X TRY ise ters. İş kuralıyla doğrulanmalı [INFERENCE].
  - 🟡 `Sys_options::where('code',$c)->first()->id` — null'da fatal (TRY gibi XML'de olmayan kodlar dahil).
  - 🟡 `Other.php` muadili constructor'da `die` ediyor — strateji sınıfı yarım bırakılmış.

## `app/Classes/Currencies/Other.php` (~15 satır)
- **Amaç:** TCMB dışı kur kaynağı için planlanmış strateji sınıfı — implemente edilmemiş.
- **Semboller:** `__construct()` — `'Other Çalıştı'` basıp `die`.
- **Bulgular:** Ölü/tehlikeli kod: instantiate edilirse request'i öldürür. Silinmeli veya doldurulmalı.

## `app/Helpers/DocumentHelpers.php` (~777 satır)
- **Amaç:** Global fonksiyon koleksiyonu: belge indirme/şifre çözme, PDF imzalama, PUT parse, dosya upload/DB kaydı, resim sıkıştırma, tarih yardımcıları, SQL "temizleme", SEO slug.
- **Semboller (hepsi global, `function_exists` korumalı):**
  - `signDocument($path, $data, $ftrans='download', $fname='-')` — Onay metnini ("Bu Belge 'X' Tarafından 'tarih' Tarihinde Onaylanmıştır", `$data`'yı `-**-` ile split ederek [2]=kişi, [1]=tarih) belgeye basar. PDF ise FPDI: her sayfayı import edip kırmızı Arial 15 ile alta yazar (iso-8859-9 iconv), `Output('D')` veya inline, **`die`** ile çıkar. Görsel ise Intervention Image ile Roboto-Medium 50px #ee2e31 metin basıp webp encode döner. Exception code 267 ise ghostscript (`gs -sDEVICE=pdfwrite -dCompatibilityLevel=1.4`) ile PDF'i düşürüp **rekürsif** tekrar dener.
  - `decryptFile($doc, $ftrans='download')` — **Belge indirme çekirdeği.** `$doc` UUID formatındaysa `Document_files.qnid` ile satır bulunur (`description` = şifreli dosya adı). `EncryptionProvider->decrypt($doc)` → `storage/app/public/documents/{ad}`; dosya varsa `File::get` + mime + `getExtension($mime)` ile isim üretip download/inline `Response` döner, yoksa 404.
  - `getExtension($mime_type)` — ~200 satırlık mime→uzantı map'i; bilinmeyen mime'da **undefined key warning** (`application/octet-stream`→pdf dikkat çekici).
  - `parsePut()` — legacy ham `php://input` PUT/multipart parser; `$_FILES`'a Symfony UploadedFile enjekte eder, sonucu `$GLOBALS['_PUT']`'a yazar.
  - `slugify($text, $divider='-')` — translit + temiz slug ('n-a' fallback).
  - `uploadFile($file)` — ≤42MB ve jpg/png/jpeg/pdf/xls/xlsx kontrolü; `time().Str::random(5).slugify(orijinal).ext` adıyla `storage public/documents`'a yazar; `EncryptionProvider->encrypt($filename,'pickle')` döner (`data`=şifreli ad, `rsp`, `success`).
  - `removeFile($fileId)` — `Document_files` satırından şifreli adı çözüp `unlink`; DB satırını silmez!
  - `addFileToDb($f, $tag, $rowId=0, $reletion='-', $reletion_id='0', $logMessage='')` — uploadFile + `Sys_options` op_key→type_id çözümle + `Document_files` kaydı (description=şifreli ad) + UserLog (`log-file-added`) + Transactions (`doc_file_waiting`). `$rowId != 0` ise eski dosya pasife alınır (`status=0`, `replaced_id`), `doc_file_refreshed` transaction'ı atılır ve `Sys_con_entities` kayıtları yeni dosyaya kopyalanır.
  - `compressAndEncodeImage($imageUrl, $quality=75)` — URL'den resmi çekip GD ile yeniden encode (jpeg/png/webp), `data:$mime;base64,...` döner.
  - `displayDates($date1,$date2,$format='Y-m-d',$step='+1 day')` — iki tarih arası dizi; `Y-m`/`Y'`da assoc key.
  - `getDates($month,$year)` — ayın günleri Y-m-d listesi (0 padding).
  - `isWeekend($date)` — Cmt/Paz kontrolü.
  - `noInject($kelime)` — string replace ile naïve SQLi "temizleyici" (PermissionHelpers'da birebir kopyası var).
  - `generateSeoURL($string, $wordLimit=0)` — SEO slug üretici.
- **İlişkiler:** `EncryptionProvider`, `Document_files`, `Documents`, `Sys_options`, `UserLog`, `Transactions`, `Sys_con_entities`; belge controller'ları bu global fonksiyonları çağırır.
- **Bulgular:**
  - 🔴 **IDOR / yetki kontrolü yok:** `decryptFile` içindeki tüm person tipi + müşteri kodu + `checkPerm` kontrolleri yorum satırına alınmış — qnid/şifreli ad bilinen HER dosya herkesçe indirilebilir. `isApproved` imza akışı da yorumda.
  - 🔴 `noInject` güvenlik sağlamaz (blacklist bypass kolay); kullanıldığı her yerde prepared statement'a geçilmeli.
  - 🟠 `signDocument` çıktı sonrası `die`; ghostscript fallback'i `exec` ile path interpolasyonu (path injection yüzeyi) ve sınırsız rekürsiyon (lowered.pdf yine 267 verirse sonsuz döngü).
  - 🟠 `removeFile` DB kaydını silmeden dosyayı siler → yetim kayıtlar.
  - 🟡 `parsePut` ölü legacy (Laravel PUT'u zaten parse eder); `getExtension` map dışı mime'da PHP warning.
  - 🟡 `compressAndEncodeImage` hata durumunda "Hata: ..." string'i döner — çağıran taraf tip kontrolü yapmak zorunda.

## `app/Helpers/NotificationHelpers.php` (~62 satır)
- **Amaç:** İSG (İş Sağlığı Güvenliği) evrak eksikliği olan ihaleleri bulan rapor sorgusu.
- **Semboller:** `checkTenderIsg($user = 0)` — 9 İSG op_key grubunu (`op-doc-ih-isg-*`) çeker; PostgreSQL CTE ile her `documents` kaydı için `file_list` dizisi (document_con_ops → document_files zinciri) ve `users` dizisi (persons→users, client_id eşleşmesi) çıkarır; `ARRAY[...] <@ file_list = false` (eksik evrak) + geçen ay filtresi uygular. `$user != 0` ise `array['$user'] <@ users` ekler.
- **İlişkiler:** sys_options, documents, document_con_ops, document_files, persons, users tabloları (raw SQL); bildirim/rapor tarafından çağrılır.
- **Bulgular:**
  - 🟠 `$user` doğrudan SQL'e interpole ediliyor — int bekleniyor ama doğrulanmıyor (SQLi yüzeyi).
  - 🟡 PostgreSQL-specific (array cast, `<@`); MySQL'e taşınmaz.

## `app/Helpers/PermissionHelpers.php` (~173 satır)
- **Amaç:** Yetki kontrolü için global kısayollar + string yardımcıları.
- **Semboller (global):**
  - `checkPerm($key)` — `auth()->user()` yoksa false; `PermissionService::has($user, $key)` delege.
  - `loadUserPermissionsToSession($user)` — PermissionService delege.
  - `ensurePermissionSessionFreshness()` — PermissionService delege.
  - `docPermCheck($type, $job)` — sabit map: `op-doc-request`→edit/status per-05-02, read per-05-01; `op-doc-client`→per-06-02/06-01; `op-doc-offer`→edit per-08-02, read per-08-01, **status per-05-02** (talep yetkisine düşüyor — kasıtlı mı?). Map dışı type/job → false.
  - `refreshAllUserPermissions()` — tüm user'ların perm cache'ini silip global invalidation timestamp'i yazar, mevcut kullanıcıyı yeniden yükler.
  - `preUp($str)` — Türkçe-aware büyük harf çevirici (i→İ, ı→I, ö, o, ü, u).
  - `noInject($kelime)` — DocumentHelpers ile birebir aynı naïve temizleyici (kopya).
  - `hasMailPerm($personId, $key)` — raw SQL: document_con_ops + sys_options join'i ile `op-perm`/`$key`/person_id eşleşmesi; `['success'=>bool]` döner.
  - `if(!function_exists('checkPermRoute')){}` — **boş blok**, fonksiyon tanımsız (ölü iskelet).
- **İlişkiler:** `PermissionService` (ana delege); tüm controller/view'larda `checkPerm` ile kullanılır.
- **Bulgular:**
  - 🔴 `hasMailPerm` `$key` ve `$personId`'yi doğrudan SQL'e gömüyor — **gerçek SQL injection**.
  - 🟠 `refreshAllUserPermissions` yanlış cache key'leri siliyor: `user_permissions_{id}` / `user_permissions_version_{id}` ama PermissionService `permissions.user.{id}` / `permissions.user.version.{id}` kullanıyor → **invalidation fiilen çalışmıyor** (sadece global timestamp + session reload etkili).
  - 🟡 `noInject` kopyası (DRY ihlali) + `checkPermRoute` boş blok + sondaki `?>` kapanışı.

## `app/Helpers/ReportHelpers.php` (~7 satır)
- **Amaç:** Rapor ekranları için ay isimleri.
- **Semboller:** `getMonths()` — Türkçe 12 ay dizisi.
- **Bulgular:** —

## `app/Rules/Recaptcha.php` (~42 satır)
- **Amaç:** Google reCAPTCHA siteverify doğrulama rule'u.
- **Semboller:** `passes($attribute, $value)` — `RECAPTCHA_VERIFY_URL` (varsayılan google siteverify) + `RECAPTCHA_SECRET_KEY`/`services.recaptcha.secret` ile form POST (secret, response, remoteip=istek IP'si); `success` alanını döner. `message()` — Türkçe hata mesajı.
- **İlişkiler:** Login/form request validasyonlarında kullanılır.
- **Bulgular:** 🟡 HTTP hatası/timeout'ta `$response->json()` null döner → sessizce false (kullanıcıya genel mesaj; DoS'da kilitlenme yok). Skor bazlı (v3) kontrol yok.

## `app/Policies/TeamPolicy.php` (~76 satır)
- **Amaç:** Laravel Jetstream standart Team yetki policy'si.
- **Semboller:** `viewAny`→true; `view`→`belongsToTeam`; `create`→true; `update/addTeamMember/updateTeamMember/removeTeamMember/delete`→`ownsTeam`.
- **İlişkiler:** `App\Models\Team`, `User` (Jetstream trait'leri).
- **Bulgular:** 🟡 Jetstream kalıntısı — proje team kullanmıyorsa ölü kod; custom PermissionService ile hiç entegre değil (iki paralel yetki sistemi).

---

## Alan Özeti
- **Bildirim omurgası:** `MailService` + `SmsService` ikilisi tüm dış iletişimi yürütür; her gönderim `NotificationLog`'a pending→sent/error yaşam döngüsüyle yazılır, `retryNotificationLog` ile yeniden denenebilir. `MailService::sendSms` e-posta+SMS'i tek kapıdan birleştirir.
- **Yetki omurgası:** `PermissionService` (cache+session+versiyon) → `PermissionHelpers::checkPerm` global'i → controller/view. Rol/perm katalog verisi `RoleTemplateService` üzerinden DB+file-cache ile beslenir; `TeamPolicy` bu sisteme bağlı olmayan Jetstream kalıntısıdır.
- **Belge akışı:** `uploadFile/addFileToDb` (EncryptionProvider ile şifreli ad + UserLog + Transactions) → saklama; `decryptFile` ile geri okuma — ancak yetki kontrolleri yorumda bırakılmış (IDOR riski).
- **Dış entegrasyonlar:** SmsService İletişim Makinesi UserGatewayWS/SMSGatewayWS (token + XML/JSON çift format parse), TCMB cURL+SimpleXML günlük kur çekme, Recaptcha siteverify, ExportService PhpSpreadsheet XLSX stream.
- **Kritik bulgular:** MailService'te açık parola loglama + TLS doğrulama kapalı + backdoor yorumu; PermissionService'te import'suz UserLog/Sys_options (force logout logu sessizce düşüyor); hasMailPerm SQL injection; refreshAllUserPermissions yanlış cache key; decryptFile yetkisiz indirme; TCMB transaction'sız truncate + ters kur matematiği şüphesi.
