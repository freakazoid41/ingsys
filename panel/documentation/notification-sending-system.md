# CoalApp Notification Sending System

## 1. System overview

CoalApp has a custom notification delivery system for email and SMS built on top of Laravel and a PostgreSQL-backed audit/log model. The system supports:

- direct email delivery via `App\Services\MailService`
- direct SMS delivery via `App\Services\SmsService`
- queued notification dispatch via `App\Jobs\SendNotificationMailJob` and `App\Jobs\SendResetMailJob`
- persistent delivery logging in `notification_logs`
- direct retriggering via API and UI controls

## 2. Core services

### 2.1 MailService

`panel/app/Services/MailService.php` is the central email sender. It:

- validates message payloads (`to`, `subject`, `body` or `html`)
- supports attachments as files or binary data
- optionally uses a relay SMTP server when `use_relay` is true
- temporarily overrides Laravel mail configuration at runtime for relay delivery
- writes detailed log metadata for each send attempt
- creates a `NotificationLog` record for every email send attempt
- supports retrying a failed email using `retryNotificationLog()`

### 2.2 SmsService

`panel/app/Services/SmsService.php` is the SMS gateway integration service. It:

- reads gateway credentials from `config/services.php` and `.env`
- authenticates with the gateway and caches the token for 24 hours
- sends SMS through the configured gateway endpoint
- parses JSON or XML responses from the gateway
- logs request/response details for debugging
- creates a `NotificationLog` record for every SMS send attempt
- supports retrying a failed SMS using `retryNotificationLog()`

## 3. Notification job flow

### 3.1 Email notification provider

`panel/app/Providers/EmailServiceProvider.php` dispatches notification jobs for TEDARIK 7 + legacy:

- `tedarik-01` Sipariş Sisteme Geldi (SAP) → `sendTedarikOrderImported()` → `tedarikOrderImported()` **BUKRS-gated**
- `tedarik-02` Sipariş Onaya Gönderildi → `sendTedarikOrderSent()` → `tedarikOrderSent()` **BUKRS-gated 2026-09-06 night**
- `tedarik-03` İnceleme Bekleyen Dosyalar → `sendTedarikFileWaiting()` → `tedarikFileWaiting()` **BUKRS-gated 2026-09-06 night**
- `tedarik-04` Dosya Onaylandı → `sendTedarikFileApproved()` **dual BUKRS/LIFNR 2026-09-06 night**
- `tedarik-05` Dosya Yeniden Talep → `sendTedarikFileRejected()` **dual BUKRS/LIFNR 2026-09-06 night**
- `tedarik-06` Kalite Onayı → `sendTedarikOrderApproved()` **dual BUKRS/LIFNR 2026-09-06 night**
- `tedarik-07` Sipariş Reddedildi → `sendTedarikOrderRejected()` **dual BUKRS/LIFNR 2026-09-06 night**
- `tedarik-06` Kalite Onayı → `sendTedarikOrderApproved()`
- `tedarik-07` Sipariş Reddedildi → `sendTedarikOrderRejected()`
- legacy `register / offerGiven / offerStatus / clientUpdate / cliFileStatus` → remapped to `tedarik-01..03` (deprecated)

It uses `SendNotificationMailJob` to carry the payload and perform actual email sending via `informSystemUsers(opKey)` → `PersonsServiceProvider::getNotificationUsers()`. **BUKRS gate:** `tedarik-01` (`SyncOrdersCommand:151` payload `bukrs/sys_code/BUKRS`) and `tedarik-02`/`tedarik-03` (`DocumentController:179` same moment after `processOrderTransfer` success, payload `sys_code/bukrs` via `getFormData(clone_qnid ?? id)` entity `sys_code`, `fileTitle='Transfer dosyaları'` for `03`) both filter recipients in `SendNotificationMailJob:386,452,506` (`bukrsToSystem()` `4000/GDZ`/`5000/ADM`/`BOTH`, fetch `users.grp_code` via `persons.qnid` → only `BOTH` or `==bukrsSys` pass; `tedarik-03` uses order's sys_code via file→order resolution). **Dual gate `tedarik-04`/`05`/`06`/`07`:** `SendNotificationMailJob:558,654,741,848` — (A) assigned non-tedarik `BOTH/==sys_code` (skip `op-pert-reseller` in assigned), (B) matching resellers `persons p + sp op-pert-reseller + users` → per-person `clientQnidList` (`cliid**` → `lifnr`) → if `order spec_code` in lifnrs AND `users.grp_code BOTH/==sys_code` → add; merge deduplicated. Trigger `DocumentController:498` `setFileStatus` `doc_file_accepted`/`rejected` + `318` `setStatus` `approved/rejected/files_rejected` resolves file→order (`relation_id → parent fallback`) → `getFormData` `spec_code/sys_code` → `sendTedarikFileApproved`/`sendTedarikFileRejected`.

### 3.2 Notification job

`panel/app/Jobs/SendNotificationMailJob.php`:

- inspects payload `type` (`tedarikOrderImported|tedarikOrderSent|tedarikFileWaiting|tedarikFileApproved|tedarikFileRejected|tedarikOrderApproved|tedarikOrderRejected` + legacy)
- routes to TEDARIK handler `tedarikOrderImported()` etc. → `informSystemUsers(subject, html, 'tedarik-0X')` (**`tedarik-01:397`, `tedarik-02:452`, `tedarik-03:506`, `tedarik-04:558`, `tedarik-05:654`, `tedarik-06:741`, `tedarik-07:848` bypass it with BUKRS/LIFNR-filtered manual `MailService::sendMail` loops; others use unfiltered `informSystemUsers:710`**)
- builds email content and recipients via `MailService`
- logs job execution progress and failures; `informSystemUsers` now guards `empty($permittedUsers[$opKey])` return; BUKRS handlers log `Skipped user due BUKRS mismatch` + `Filtered ... by BUKRS`; `tedarik-02`/`tedarik-03` triggered together (`DocumentController:179` sends both with same `bukrs/sys_code`), `tedarik-04`/`05` dual (assigned non-tedarik + lifnr-matching reseller via `clientQnid→lifnr==spec_code` + `BOTH/==sys_code`)

### 3.3 Password reset / info email jobs

- `panel/app/Jobs/SendResetMailJob.php` sends reset password emails
- `panel/app/Jobs/SendInfoMailJob.php` sends arbitrary info emails

Both jobs use `MailService` and update logs on success/failure.

## 4. Persistent notification logging

### 4.1 Log model

`panel/app/Models/NotificationLog.php` is the database model for notification audit data.

Fields include:

- `type`: `email` or `sms`
- `to`: recipient email or phone number
- `subject`: email subject or `SMS`
- `body`: raw body or message text
- `status`: `pending`, `sent`, or `error`
- `error_message`: gateway or exception details
- `detail`: JSON metadata such as response body or configuration used
- `payload`: original send payload
- `attempts`: retry count
- `last_attempt_at`: timestamp of the last attempt
- `sent_at`: timestamp when delivery succeeded

### 4.2 Migration

`panel/database/migrations/2026_04_15_000000_create_notification_logs_table.php` creates the `notification_logs` table with the fields above.

### 4.3 Table query support

`NotificationLog::tableList()` returns structured rows for the UI table, including `id` and `row_id` fields to ensure the front-end gets a stable numeric DB ID.

## 5. Retriggering notifications

### 5.1 Retry command

A console command is available to retry a notification immediately:

```bash
php artisan notification:retry {id}
```

It supports both email and SMS and updates the original log record with a new attempt status.

### 5.2 API endpoint

A direct API endpoint has been added for retriggering without queueing:

```http
POST /api/v1/notificationlog/{id}/retrigger
```

This endpoint:

- validates the notification log exists
- retries the message immediately based on its `type`
- returns JSON with `success` and status details

### 5.3 UI integration

The notification log table UI at `panel/resources/js/pages/coalsystem/NotificationLogs/NList.vue` now includes a `Yeniden Gönder` button inside the SweetAlert detail modal.

When clicked, it:

- calls the `/api/v1/notificationlog/{id}/retrigger` endpoint
- shows waiting state while the retry executes
- displays success or error feedback
- refreshes the table after success

## 6. Configuration

### 6.1 Mail configuration

`panel/config/mail.php` contains the default Laravel mail configuration. Important runtime options include:

- `MAIL_MAILER`
- `MAIL_HOST`
- `MAIL_PORT`
- `MAIL_USERNAME`
- `MAIL_PASSWORD`
- `MAIL_USE_RELAY`
- `MAIL_RELAY_HOST`
- `MAIL_RELAY_PORT`
- `MAIL_RELAY_ENCRYPTION`
- `MAIL_RELAY_USERNAME`
- `MAIL_RELAY_PASSWORD`

### 6.2 SMS gateway configuration

`panel/config/services.php` contains the `iletisimmakinesi` gateway configuration.

Important env values:

- `ILETISIM_BASE_URL`
- `ILETISIM_USERNAME`
- `ILETISIM_PASSWORD`
- `ILETISIM_API_KEY`
- `ILETISIM_VENDOR_ID`
- `ILETISIM_CUSTOMER_CODE`
- `ILETISIM_SERVICE_ID`
- `ILETISIM_ORIGINATOR_ID`
- `ILETISIM_CLIENT_ID`

## 7. 2026-09-07 Fixes — Gmail + Bulk

### 7.1 Gmail SMTP (replaces Mailtrap demo)
* `panel/.env:82-85` was `live.smtp.mailtrap.io / api / 86e1...` `hello@demomailtrap.co` → `smtp.gmail.com:587 tls kadir@kontent.com.tr / hruxkivfrwdndogm (hrux kivf rwdn dogm without spaces)` `kadir@kontent.com.tr`. `MAIL_USE_RELAY=false`, `config:clear` + `mail:test` `→ sent` at `05:31:42`. Previous `NotificationLog:102` `554 5.7.1 Demo domains can only be used to send to account owners` for `kbbozat41@hotmail.com` at `05:23:53` fixed via `php artisan notification:retry 102 → sent`. Gmail via `MailService.php:42` `smtp.gmail.com` with `verify_peer false`.

### 7.2 Bulk `Tümünü Onayla` now fires `tedarik-04/05`
* `DocumentController.php:642 setFileStatusAll` (bulk `POST /v1/trans/set-file-status-all` per-07-02) previously looped `documentFileStatus` + `cliFileStatus` only (line 658-686), never dispatched `tedarik-04/05` (line 572 in single `setFileStatus`). So bulk 5 files = 0 mails to admin/reseller vs single = 1. Fixed `660` collects `tedarikPending[order_qnid]` with `fileTitles[]` per file's order (`relation_id → parent fallback → getFormData → spec_code/sys_code`), after loop aggregates `fileTitle = "3 dosya: A,B,C…"` per order and dispatches once per order (`sendTedarikFileApproved/Rejected`) via `EmailServiceProvider:116` BUKRS+LIFNR. Prevents 5× spam, matches single logic. `refreshAllUserPermissions` still once.

## 8. Notes and recommendations

- The system currently disables SMTP peer verification at runtime for relay mail; this should be used carefully and restricted to development or trusted environments.
- SMS gateway responses may be XML or JSON, and the service handles both formats.
- `NotificationLog` provides auditability for every send attempt and enables reliable retriggering.
- UI retry and API retry both use the numeric DB `id`, avoiding table row generation artifacts.

## 8. File summary

- `panel/app/Services/MailService.php`
- `panel/app/Services/SmsService.php`
- `panel/app/Jobs/SendNotificationMailJob.php`
- `panel/app/Jobs/SendResetMailJob.php`
- `panel/app/Jobs/SendInfoMailJob.php`
- `panel/app/Models/NotificationLog.php`
- `panel/app/Models/NotificationRead.php` — read tracking (user_id, op_key, target_qnid)
- `panel/app/Console/Commands/RetryNotificationSend.php`
- `panel/app/Console/Commands/RetryNotificationSendJob.php`
- `panel/app/Http/Controllers/SystemController.php`
- `panel/resources/js/pages/coalsystem/NotificationLogs/NList.vue`
- `panel/database/migrations/2026_04_15_000000_create_notification_logs_table.php`
