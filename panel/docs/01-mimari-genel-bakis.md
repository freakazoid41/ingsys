# 01 — Mimari Genel Bakış

## Sistemin amacı

KomurTedarik, kömür tedarik (satınalma/ihale) süreçlerini yöneten bir web uygulamasıdır.
İki rol ekseninde çalışır:

- **Admin (sistem kullanıcısı, `op-pert-admin`):** Müşteri (tedarikçi firma) kaydı açar,
  belgeleri onaylar/reddeder, kömür talebi (ihale) oluşturur, gelen teklifleri
  onaylar/reddeder/revizyon ister, kullanıcı/rol/bildirim yönetimi yapar, logları izler.
- **Tedarikçi (müşteri kullanıcısı, `op-pert-reseller`):** Firma bilgi formunu ve zorunlu
  belgeleri (imza sirküleri vb.) yükler; belgeleri onaylanınca açık taleplere teklif verir,
  teklifini revize eder, durumunu izler.

İki alan adı üzerinden çok-kiracılı (multi-tenant) çalışır:
`komurtedarik.cates.com.tr` (CATES) ve `komurtedarik.yatagantermik.com.tr` (YATAGAN).
Kiracı, `public/index.php` içinde HTTP Host başlığına göre `$GLOBALS['SYS_CODE']`
değişkenine yazılır ve log/kayıtlara `sys_code`/`grp_code` olarak işlenir.

## Teknoloji yığını

| Katman | Teknoloji |
|---|---|
| Backend | Laravel 12 (PHP 8.2+), Sanctum (token + session), PostgreSQL |
| Frontend | Vue 3 SPA (`/coalpanel`), Vite 6, Pinia, vue-router 4, Tailwind 3 |
| Kimlik | Session + Sanctum token; SMS/e-posta ile 2FA (İletişim Makinesi API) |
| Bildirim | SMTP (+relay) e-posta, İletişim Makinesi SMS; `notification_logs` kuyruğu |
| Export | barryvdh/laravel-dompdf + mpdf (PDF), PhpSpreadsheet (Excel) |
| Auth sayfaları | Blade (coallogin, loginSms, passwordReset, register) + derlenmiş `/front/pages/*/page.js` |
| CI | Chipper CI (`.chipperci.yml`) |

## Dizin haritası

```
app/
  Http/Controllers/   → AuthController (giriş/2FA), DocumentController (belge+talep+teklif),
                        PersonsController (kullanıcı/rol/bildirim), ReportController (dashboard),
                        ExportController (PDF/Excel), SystemController (tablo+bildirim)
  Providers/          → ⚠ İsimleri yanıltıcı: AppServiceProvider hariç hepsi aslında
                        DOMAIN SERVİSİ. new ile çağrılırlar:
                        DocumentServiceProvider (belge EAV CRUD), PersonsServiceProvider
                        (kişi/kullanıcı/yetki), ReportServiceProvider (dashboard),
                        EmailServiceProvider (mail dispatch), EncryptionProvider (AES)
  Services/           → MailService, SmsService, PermissionService, RoleTemplateService, ExportService
  Helpers/            → Global fonksiyonlar (composer files autoload): DocumentHelpers,
                        PermissionHelpers, ReportHelpers (NotificationHelpers autoload DEĞİL)
  Models/             → 16 Eloquent model (aşağıda EAV mimarisi)
  Console/Commands/   → 9 artisan komutu (2'si zamanlanmış)
  Jobs/               → 4 kuyruklu mail/bildirim job'ı (QUEUE_CONNECTION=database)
resources/js/         → Vue SPA: router (17 route), 5 Pinia store, pages/, components/,
                        lib/pickle.js (fetch tabanlı HTTP istemcisi + form toolkit)
resources/views/      → Auth blade'leri, coalapp.blade.php (SPA kabuğu), mail + export şablonları
database/             → 24 migration (26 tablo), 5 seeder
storage/entities/     → Rol/yetki/bildirim seed JSON'ları (SysRoleTemplateSeeder okur)
public/front/pages/   → Auth sayfalarının derlenmiş JS paketleri (kaynak repoda YOK)
public/coaltheme/     → Tema asset'leri + eski .falanml mail şablonları (ölü)
docs/                 → Bu dokümantasyon
documentation/        → Geliştiricinin bıraktığı 5 eski analiz dokümanı (doğrulandı)
```

## Veri mimarisi: EAV + evrensel sözlük

Sistemin kalbi klasik ilişkisel şema DEĞİL, üç tabloluk EAV (Entity-Attribute-Value)
yapısıdır:

- **`sys_options`** — evrensel sözlük. Tüm tipler (belge tipi, kişi tipi, durum, log tipi,
  dosya tipi, form tipi) `op_key` + `group_key` ile burada. `ttitle` hangi tablonun tipi
  olduğunu, `ctitle` hangi kolonun tipi olduğunu söyler. Örn: `op-doc-request`,
  `op-pert-admin`, `doc_trans_offer_approved`, `log-login`.
- **`documents`** — ana varlık (talep, teklif, müşteri formu...). `type_id → sys_options`.
  `qnid` (UUID benzeri) dış dünyaya açılan kimlik.
- **`sys_con_ops`** — bir ana kayda (documents/persons) bağlı form bölümleri.
  `main_id` → ana kayıt, `type_id` → form tipi, `conn_id` → alt bağlantı.
- **`sys_con_entities`** — gerçek alan değerleri: `conn_id → sys_con_ops`,
  `entity_tag` (alan adı, `**` ayraçlı), `entity_value` (değer; dosya ise
  `table_tag='document_files'` + dosya id'si).

Sonuç: form şemaları veritabanına migration ile değil, `sys_options` + form motoru
(`coalparts/Form.vue`) ile tanımlanır. Esnektir ama FK yoktur, bütünlük uygulama
koduna emanettir; sorgular çoğunlukla raw SQL'dir.

Diğer çekirdek tablolar: `persons` (1-1 `users`), `transactions` (durum geçmişi +
ödeme), `document_files` (şifreli dosya yolu `description` kolonunda), `user_logs`
(her işlemin before/after JSON'u), `active_sessions` (tek-oturum + izin versiyonu),
`notification_logs`, `sys_role_templates` + `sys_permission_catalogs` +
`sys_notification_types` + `sys_role_template_audit`.

## Kimlik doğrulama akışı (2FA)

1. `GET /` → `coallogin` blade (reCAPTCHA'lı form, `/front/pages/coallogin/page.js`).
2. `POST /api/v1/auth/login` → `AuthController@loginUser`: reCAPTCHA + e-posta/parola;
   5 başarısız denemede 15 dk cache kilidi; başarısızlıklar `user_logs`'a yazılır.
3. `generateAndSendTwoFactorCode`: 6 haneli kod **düz metin** olarak
   `storage/app/{token}-{personId}-login.txt` dosyasına yazılır; kişinin kontaklarına
   (`contmail*`/`contphone*`) MailService + SmsService ile gönderilir.
4. `POST /api/auth/checkcode` → `checkCode`: dosyadan okur, 120 sn TTL, tek kullanımlık.
5. Başarıda: `Auth::login` → `loadUserPermissionsToSession` → Sanctum token →
   `active_sessions` kaydı → önceki oturumlara `force_logout` (tek oturum kuralı).
6. İlk girişte (`needs_refresh=1` veya hiç log yoksa) şifre değiştirme zorunlu.
7. Sonrasında SPA `/coalpanel`'e girer; blade `$coalAuth` closure'ı `type_key` +
   `2f_success` session değerlerini ister.
8. Her API isteğinde `CheckPermissionVersion` middleware'i: izin versiyonu eskimişse
   session'ı tazeler; `force_logout` işaretliyse çıkış yaptırır. Frontend 30 sn'de bir
   `GET /api/v1/getpermissions` heartbeat'i atar.

## İş akışı: talep → teklif → onay

1. Admin `RForm` ile talep (ihale) açar (`op-doc-request`, durum `doc_trans_created`).
   `RequestAutoclose` komutu (her gece 01:00) bitiş tarihi geçen talebi kapatır.
2. Tedarikçi `RForm`'da talebi görür, teklif verir (`op-doc-offer`,
   `doc_trans_offer_sended`). Teklif verebilmesi için `clientPermInfo`:
   firma formu dolu + imza dosyası `doc_file_accepted` olmalı (`canResponse`).
3. Admin teklifi açınca durum `doc_trans_offer_review` olur (OForm otomatik yazar);
   sonra `doc_trans_offer_approved` / `doc_trans_offer_rejected` /
   `doc_trans_offer_revision` (revizyon iste) seçer. Her durum değişimi
   `transactions` + `user_logs`'a yazılır ve ilgili tarafa mail gider.
4. Tedarikçi revize edip kaydedince durum otomatik `doc_trans_offer_revised` olur.
5. Durum diyagramı: `created → sended → review → (approved | rejected | revision → revised → review …)`.

## Bildirim sistemi

- Olaylar (teklif geldi, durum değişti, dosya reddedildi, müşteri bilgisi değişti, kayıt
  oldu) `EmailServiceProvider` üzerinden Job'lara (`SendNotificationMailJob` vb.)
  dispatch edilir; `notification_logs`'a `pending → sent/error` olarak yazılır.
- `notification:retry --queue` komutu hatalıları `RetryNotificationSendJob` ile tekrar dener.
- Kullanıcılar bildirim gruplarına (`notif-*`) NSettings ekranından atanır; kime gideceği
  `PersonsServiceProvider::getNotificationUsers` ile çözülür.
- Panel içi bildirimler: `GET /api/v1/notifications` (Header zili + dashboard kartları).

## Yetki sistemi

- İzinler katalog tablosunda (`sys_permission_catalogs`, `per-XX-YY` kodları) ve kişiye
  JSON dizisi olarak `sys_con_entities`'te tutulur (`op-doc-user-permission-form`).
- Rol şablonları (`sys_role_templates`, 5 immutable + özel) toplu izin setidir;
  `Roles.vue` ekranından yönetilir, değişince `updateUserPermissions` ile roldeki tüm
  kullanıcılara yayılır ve `bumpUserPermissionVersion` ile oturumlar tazelenir.
- Kontrol noktaları: `checkPerm()` (global helper) → `PermissionService::has()` →
  file cache (`permissions.user.{personId}`) + session (`sper-{kod}`).
  Belge bazında `docPermCheck($typeKey, read|edit|status)` haritası kullanılır.
- Frontend izinleri `GET /api/v1/getpermissions`'tan çeker; menü/buton görünürlüğü buna göre.

## Önemli operasyonel gerçekler

- Oturum/cache/queue: **database** sürücüleri; izin cache'i **file** store.
- Zamanlanmış görevler: `request:autoclose` 01:00, `active-sessions:clean` 02:00
  (`app/Console/Kernel.php`). `currency:cron` routes/console.php'de **yorum satırı** (kapalı).
- Dosyalar `storage/app/public/documents/` altında, adı `EncryptionProvider`
  (AES-128-CBC, PBKDF2) ile şifrelenerek `document_files.description`'a yazılır;
  `/order-file/{qnid|encrypted}` route'u `decryptFile()` ile servis eder.
- `prdTest` / `prjBuildLive` kök scriptleri: ilki mail/sms test, ikincisi
  `migrate:fresh` + seed + **belgeleri silme** içerir — canlıda ÇALIŞTIRMAYIN.
- `vendor/` ve `node_modules/` repoya commit'lidir (.gitignore eksik) — klon büyük gelir.
