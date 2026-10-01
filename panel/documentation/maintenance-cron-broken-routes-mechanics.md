# Maintenance, Crons & Broken Routes

## 1. Overview

Scheduled jobs plus manual console commands for cleanup, FX, auth hygiene and diagnostics. Four routes reference missing controller methods and return 500.

**Key files:**
- `app/Console/Kernel.php:20-45` — schedule + command registration
- `app/Console/Commands/*.php` — 10 commands
- `app/Helpers/DocumentHelpers.php` — `cleanupTempFiles`
- `app/Classes/Currencies/TCMB.php`, `App/Classes/DataSources/Navision.php`
- `routes/api.php:36-37,56-57`, `routes/web.php:62-63`
- `app/Http/Controllers/DocumentController.php:19-495`, `AuthController.php`

## 2. Schedule (`Kernel.php:35-45`)

| Time | Job | Notes |
|------|-----|-------|
| `01:00 daily` | `request:autoclose` | closes `contract_end_date==today` requests |
| `02:00 daily` | `active-sessions:clean` | defaults `--force-logout-hours=24 --stale-days=7` |
| `03:00 daily` | `Closure cleanupTempFiles()` | `temp/` >24h + orphan `relation=temp` rows |

`$commands:20-27` registers 6 of 10: `SendTestMail, SendTestSms, RetryNotificationSend, RequestAutoclose, CleanActiveSessions, VerifyRecaptcha`. Unregistered rely on auto-discovery: `CurrencyCron, PurgeDocuments, ReencryptFileDescriptions, CreatePermissionCommand` — verify `composer.json` discovery or register explicitly.

## 3. Commands

1. `request:autoclose` (`RequestAutoclose.php:16`) — `Documents::tableList(form-type=request-form,type=request,today-ended like contract_end_date=d/m/Y)`, if found & not `doc_trans_request_end` → `setStatus(end)`. **Only first row** (`$data[0]`, TODO stub) — loop all in rebuild.
2. `active-sessions:clean` (`CleanActiveSessions.php:11`, `--force-logout-hours=24 --stale-days=7`) — deletes `force_logout && force_logout_at<now-24h` + `last_seen<now-7d`.
3. `notification:retry {id} {--queue}` (`RetryNotificationSend.php:13`) — sync `MailService/SmsService.retryNotificationLog` or dispatch `RetryNotificationSendJob`.
4. `currency:cron` (`CurrencyCron.php:19`, unscheduled) — `DELETE FROM currencies` + `TCMB(env SYS_CUR).fetchCur`. Navision branch commented. Destructive full wipe — wrap in transaction.
5. `documents:purge {--type=all|request|offer} {--dry-run} {--force}` (`PurgeDocuments.php:19`) — collects `documents+con_ops+entities+transactions+user_logs+document_files`, reports, DB transaction delete + `removeFile()` after commit (filesystem can't rollback). Skips `op-doc-client` + users. `--force` required in prod.
6. `permission:create {op_key?} {title?} {parent_op_key?}` (`CreatePermissionCommand.php:16`) — creates `SysPermissionCatalog`, clears `sys_permission_catalogs_all`.
7. `sms:test {to?=5438826976} {--message=}` — `SmsService.sendSms`.
8. `mail:test {to?} {--subject=} {--body=} {--use-relay}` — `MailService.sendMail`.
9. `files:reencrypt-descriptions {--dry-run} {--rollback=}` — compact salt re-encrypt, backup `storage/app/reencrypt-backup-YmdHis.json`, verify round-trip.
10. `recaptcha:test {token?} {--remote-ip=}` — `POST verify_url`, exit 0/1/2/3.

`cleanupTempFiles()` (helper, daily 03:00): deletes `storage/app/public/temp/*` >24h + orphan temp rows. Temp refs single-use — form saved twice with same ref fails second time; clear `formData.files` after submit.

## 4. Broken routes (verified missing)

| Route | Target | Status |
|-------|--------|--------|
| `GET /v1/get-apartments` | `DocumentController@getAparments` (typo) | BROKEN — no method, `grep` 0 hits |
| `POST /v1/set-apartments` | `DocumentController@setAparments` | BROKEN — same |
| `GET /v1/trans/prepare-payment` | `DocumentController@preparePayment` | BROKEN — legacy `FlatList transmodal` calls it |
| `POST /v1/trans/set-payment` | `DocumentController@setPayment` | BROKEN — same |
| `GET /setapartment/{apartment}`, `/closeapartment` (web) | `AuthController@setapartment/closeapartment` | BROKEN — 0 hits |
| `GET /v1/roles/items` twice | `PersonsController@rolesItems` | OK but duplicate |
| `offerPdf1` | `ExportController@offerPdf1` | dead, no route (only `offerPdf` wired `POST /export/offer`) |
| `Auth@login/checkMail` | — | dead/orphan, frontend `POST /api/auth/checkmail` 404s |

Impact: any call → 500 `BadMethodCallException`. Remove or stub with 410 Gone + log caller.

`FlatList.vue:transmodal(addbalance|income)` + `FlatForm.vue:op-doc-flat-form` are legacy demo for payment/flat — keep only if reviving payment flow.

## 5. Gotchas

1. Autoclose single-row bug — must loop.
2. Currency wipe is `DELETE` without `WHERE` — snapshot before cron.
3. Purge physical delete after commit — DB rollback won't restore files; dry-run first.
4. Temp cleanup 24h — long-lived drafts lose files; bump or warn.
5. Unregistered commands rely on discovery — pin in `Kernel::$commands`.
6. Broken payment/apartment routes look active — delete or implement, don't leave 500s.
