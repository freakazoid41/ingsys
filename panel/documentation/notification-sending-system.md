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

`panel/app/Providers/EmailServiceProvider.php` dispatches notification jobs for application events such as:

- register
- offer creation
- offer status changes
- activation
- client updates
- client file status changes

It uses `SendNotificationMailJob` to carry the payload and perform actual email sending.

### 3.2 Notification job

`panel/app/Jobs/SendNotificationMailJob.php`:

- inspects payload `type`
- routes to a handler method such as `clientRegister()`, `clientOfferGive()`, `clientActivation()`, etc.
- builds email content and recipients
- sends email via `MailService`
- logs job execution progress and failures

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

## 7. Notes and recommendations

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
- `panel/app/Console/Commands/RetryNotificationSend.php`
- `panel/app/Console/Commands/RetryNotificationSendJob.php`
- `panel/app/Http/Controllers/SystemController.php`
- `panel/resources/js/pages/coalsystem/NotificationLogs/NList.vue`
- `panel/database/migrations/2026_04_15_000000_create_notification_logs_table.php`
