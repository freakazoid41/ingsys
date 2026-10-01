# User Onboarding & Credentials Mechanics

## 1. Overview

Public supplier self-registration plus admin activation plus two password recovery paths (self-service mail link + admin credential reset). Pending users (`status=-1`) can never log in until activated.

**Key files:**
- `app/Http/Controllers/AuthController.php` — `register:74`, `registerUser:88`, `loginUser:205`, `checkCode:330`, `resendCode:622`, `sendMail:674`, `passwordReset:726`, `passChange:782`, `checkMail:802` (orphan, no route)
- `app/Http/Controllers/PersonsController.php` — `index:19` (activation), `resetUserCradentals:389`
- `app/Providers/PersonsServiceProvider.php` — `setPerson:183-285`, `setClientToPerson:485-558`
- `app/Providers/EmailServiceProvider.php` — `sendregisterMails:18`, `sendapproveMails:54`, `sendresetMail:92`
- `app/Jobs/SendNotificationMailJob.php` — `clientRegister:121`, `clientActivation:304`
- `app/Jobs/SendResetMailJob.php:28-68`
- `routes/web.php:7-13`, `routes/api.php:22-29`

## 2. Registration — `POST /v1/auth/register` → `registerUser`

Branch on `X-Requested-With !== XMLHttpRequest` (`AuthController.php:91`).

| Mode | Validation | Client binding | Recaptcha |
|------|------------|----------------|-----------|
| Non-AJAX (browser form) | `email,phone,password,g-recaptcha-response required` | none at create | required |
| AJAX/JSON | `email,phone,password,cli_id required` | `cliid/clicode/clititle**userclientgroup**` from `cli_id/cli_code/cli_title` | none |

Both:
- `session()->flush()` first (non-AJAX).
- Uniqueness via `Auth::attempt(email,password)` hack — if succeeds, email taken (`Bu E-Posta kullanılmaktadır`).
- Create via `setPerson(0, [main_name=email, user_status=-1, user_username=email, user_password=plaintext, user_role=immutable-reseller, type_key=op-pert-reseller, contphone/contmail/conttitle**userfacilitygroup**], ...)`. `setPerson` hashes (`Hash::make`) + `User::updateOrCreate`.
- Fire `sendregisterMails(email,phone)` → `SendNotificationMailJob(type:register)` → `clientRegister` subject `Yeni Müşteri Kaydı` → `informSystemUsers(notif-00)` to users with `notif-00` in `op-doc-user-notification-form`.
- Non-AJAX: `Session::put(auth-forgot, incelendikten...)` + redirect `login`. AJAX: `json {success:true, message:...} 200`.

## 3. `status=-1` gate

`loginUser:227-228`: `$user=User::where([email,status=>'1'])->first()` else `Bilgiler Hatalıdır`. `-1` never reaches `Auth::attempt` or 2FA. No self-service activation — manual admin step required.

Activation in `PersonsController@index PUT:94-114`:
- Needs `per-04-02` if `user_password/user_username/permissions` present; self-only fallback strips sensitive keys.
- If `type==op-pert-reseller && user_status==1`: `clientPermInfo`; if `clientQnidList` empty → `setClientToPerson` creates `op-doc-client` doc + `cliid/clicode/clititle**userclientgroup**` links.
- If `user_status==1`: `sendapproveMails(email)` → `dispatch(type:activation)` → `clientActivation` subject `Hesap Aktivasyonu`, view `emails.verify-email`, direct to user.
- Status change side-effect in `setPerson:183-190`: any `user[status]` write → `forceLogoutPerson`.

## 4. Self-service reset — `sendMail/passwordReset/passChange`

`POST /api/auth/sendmail` (throttle 4/min): if email exists, `$key=bin2hex(random_bytes(10))` (20 hex), `Storage::disk(local)->put($key-refreshmail.txt, email)`, mail `Şifre Değiştirme` with `{host}/auth/passwordreset/{key}`.

`GET /auth/passwordreset/{code}`:
- Authed user → `session(auth-forgot=email)`, view `auth.passwordReset`.
- Unauth: `exists(code-refreshmail.txt)` else fail; load user by stored email, `delete` mail file, `rand(100000,999999)` → `put(code-personId-login.txt)`, `session(login_person,token=code,login_type)`, `put(code-refreshmailsms.txt,email)`, then `generateAndSendTwoFactorCode` (overwrites login file with fresh code, sends mail+SMS). Redirect SMS page.

`POST /auth/passchange`: guard `session(auth-forgot) && sanctum user`; `Hash::make`, `needs_refresh=0`, `Session::flush()`, redirect login.

Code files on `local` disk: `{token}-{personId}-login.txt` (120s TTL, single-use, deleted on read), `{key}-refreshmail.txt`, `{key}-refreshmailsms.txt` (flips `firstLogin` in `checkCode`).

## 5. Admin reset — `POST /v1/auth/resetusercradentals/{id}`

**Outside `auth:sanctum` group** (`api.php:29` before group `:32`) — verify before exposing publicly.

- Input `$data=[user_password=>bin2hex(random_bytes(8))` (16 hex), `user_needs_refresh=>1]` → `setPerson` hashes, sets `needs_refresh`.
- `forceLogoutPerson(id, Şifreniz Sıfırlandı...)` + `UserLog log-user-status-update`.
- `sendresetMail(email, plaintext)` → `SendResetMailJob` subject `Şifreniz Sıfırlandı`, body `Yeni şifreniz: <strong>...</strong>`.
- Next login `checkCode:407-409` sees `needs_refresh==1` → `firstLogin=true` → `sms-firstlogin` → password-change loop, cleared in `passChange`.

`needs_refresh` schema `users.needs_refresh smallInt default false` (`migrations/0001_01_01...:17`).

## 6. `forceLogout` triggers

`PermissionService::forceLogoutPerson` sets `active_sessions.force_logout=true,reason,at` + `UserLog log-user-logout`:

1. Login single-session (`checkCode:431-449`) — purge `force_logout_at<now-1day`, then flag all, then `createToken + ActiveSession::create`.
2. Status change (`setPerson:183-189`).
3. Credential reset (`PersonsController:409-410`).

## 7. Gotchas

1. `resetusercradentals` outside auth group — audit urgently; should require `per-04-02`.
2. `checkMail` orphan — `AuthController:802` exists, no route, frontend `POST /api/auth/checkmail` 404s.
3. Uniqueness via `Auth::attempt` leaks timing; use `User::where(email)->exists()`.
4. AJAX register skips recaptcha — rate-limit or add captcha.
5. `generateAndSendTwoFactorCode` overwrites the `passwordReset` SMS code — intentional, don't double-send.
6. DEV_ADMIN fixed code `111111`, bypasses `firstLogin` — keep out of prod.
