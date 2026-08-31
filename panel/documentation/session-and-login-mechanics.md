# Session & Login Mechanics

## 1. Overview

This system uses a **two-factor, SMS/mail code login flow** on top of Laravel's classic session auth, with **Sanctum tokens** for the SPA/API and a custom `active_sessions` table for live session tracking, single-session enforcement, permission versioning, and force-logout.

This document covers the **full login pipeline, session state, token lifecycle, and the permission-version refresh machinery**. It complements:
- `permission-system-analysis.md` — permission model & codes (read that first for permission details)
- `single-session-enforcement-system.md` — force-logout flow in plain English

**Key files:**
- `app/Http/Controllers/AuthController.php` — all auth endpoints
- `app/Http/Middleware/CheckPermissionVersion.php` — per-request session/version enforcement
- `app/Services/PermissionService.php` — permission cache + version + session flags
- `app/Models/ActiveSession.php`, `app/Models/UserLog.php`
- `app/Providers/PersonsServiceProvider.php` — person loading & `clientPermInfo`
- `app/Services/SmsService.php`, `app/Services/MailService.php` — code delivery channels
- `app/Console/Commands/CleanActiveSessions.php` — stale session cleanup
- `resources/js/stores/auth.js`, `resources/js/lib/pickle.js` — frontend auth handling

---

## 2. Login flow (step by step)

### 2.1 Step 1 — Credentials: `POST /v1/auth/login/{type?}` → `AuthController::loginUser`

1. **Session flush** — any pre-existing session data is wiped first.
2. **Validation** — `email`, `password`, `g-recaptcha-response` (custom `Recaptcha` rule). Failures redirect back to `/` with `login-error`.
3. **User lookup** — `User::where(['email' => $email, 'status' => '1'])`. A disabled user gets a generic "Bilgiler Hatalıdır" (no account-enumeration leak).
4. **Brute-force lockout** (cache-based):
   - Cache keys: `login:attempts:{email}`, `login:locked:{email}`.
   - 5 failed attempts → 15-minute lock. Each failure logs a `UserLog` (`log-login-failed`); a lock logs `log-lock`.
   - Lock check happens BEFORE auth attempt, so even a correct password is rejected while locked.
   - Success clears both cache keys.
5. **Person load** — `PersonsServiceProvider->getPerson(..., true, $user->person_id)`; person must exist.
6. **`Auth::attempt`** — password check. On failure: `UserLog(log-login-failed)` + attempts increment + lock check.
7. **Session priming (pre-2FA)** — sets `type_key`, `person_id`, `email`, `ptitle`, `grp_code` in session.
8. **2FA dispatch** — `generateAndSendTwoFactorCode($user, $person, $token)` (see §3). On success redirect to `/smscallback` (login-sms page) with the debug code flashed only on `localhost:8000`.

### 2.2 Step 2 — One-time code: `POST /api/auth/checkcode` → `AuthController::checkCode`

1. **Code reassembly** — inputs named `code_1..code_6` are collected into the 6-digit string. A `*` segment means the code expired (the UI stamps `*` on expired boxes).
2. **Code file lookup** — the expected code is stored as a plain `.txt` file on the `local` storage disk, keyed `{token}-{personId}-login.txt`.
   - **120-second TTL** enforced via file mtime; expired files are deleted and rejected.
   - The file is deleted on read (single-use).
3. **Auth commit** — on match:
   - `Auth::login($user)` — Laravel session login.
   - Person re-loaded; session primed with `person_id`, `user_id`, `email`, `ptitle`, `type_key`, `2f_success = true`.
   - `loadUserPermissionsToSession($user)` — permission flags into session (§5).
   - `currentStatus = clientPermInfo(personId, typeKey)` — the reseller "can proceed / can respond" gate (see §6).
4. **First-login detection** — `firstLogin = true` when: no `UserLog` rows exist for the user, OR `users.needs_refresh = 1`, OR a `{token}-refreshmailsms.txt` file exists (password-reset-sms path). DEV_ADMIN is exempt.
5. **Login logging** — `UserLog(log-login)`.
6. **Session takeover + single-session enforcement**:
   - Stale `force_logout` records older than 1 day are purged.
   - `PermissionService->forceLogoutPerson(person_id, 'Bu hesaba başka bir cihazdan giriş yapıldı.')` — marks ALL existing `active_sessions` rows for this person `force_logout = true`.
7. **Token creation** — `$user->createToken("API TOKEN")->plainTextToken`. The token id (`explode('|')[0]`) is stored in the new `active_sessions` row along with `session_id`, `ip`, `user_agent`, `current_status`, `permission_version`, `last_seen`.
8. **Redirect** — first login → login page with `sms-firstlogin` + token (frontend prompts password change); otherwise `sms-success` + token. **The token is handed to the SPA via session flash, and the SPA stores it in localStorage** (`auth.js`).

### 2.3 Token handoff to the SPA

The token never leaves the browser-session flow:

1. `checkCode` flashes the token via `redirect()->route($loginRoute)->with('sms-success', $token)`.
2. `resources/views/auth/coallogin.blade.php` renders it into a hidden `<input name="apiKey" value="...">` (plus `firstLogin` input for the password-change case).
3. `public/front/pages/coallogin/page.js` reads it and does `localStorage.setItem('token', ...)`, then redirects to `/coalpanel` — or `/auth/passwordreset/firstlogin` when first-login (that route works because the Laravel session cookie is already authenticated from `checkCode`).
4. All subsequent API calls attach `Authorization: Bearer <localStorage token>` (`pickle.js`). On logout/force-logout the token is removed from localStorage.

**Password change page** (`passwordReset` view + `POST /auth/passchange`): also serves as the **first-login** password setter — `passChange` hashes the new password, clears `needs_refresh`, flushes the session, and redirects to login.

### 2.4 Code resend: `POST /api/auth/resend-code`

- Requires `session('token')` + `session('login_person')`.
- Max 2 resends, 60s cooldown (session-tracked `resend_count`, `resend_last_at`).
- Regenerates the code file + re-sends over the same channels.

---

## 3. 2FA code generation & delivery

`AuthController::generateAndSendTwoFactorCode()`:

- Code: **DEV_ADMIN** → fixed `111111`; everyone else → `rand(100000, 999999)`.
- Code file written to `local` disk before sending (so checkCode can verify).
- **Delivery targets**: iterates the person's contacts (from the `contacts` JSON in `getPerson`):
  - keys starting `contmail*` → HTML mail via `MailService`.
  - keys starting `contphone*` → SMS via `SmsService`.
- Fallbacks if nothing was delivered: mail to `user->email`, then (DEV_ADMIN only) SMS to a hardcoded admin number.
- `debug_code` returned only when host is `localhost:8000`.

### Password reset flow (`sendMail` + `passwordReset` + `passChange`)

- `POST /api/auth/sendmail` (throttled 4/min): if email exists, writes `{key}-refreshmail.txt` (key = 20 random hex bytes) and mails a reset link `/auth/passwordreset/{key}`.
- `GET /auth/passwordreset/{code}`: verifies the file, deletes it, generates an SMS code file (`{code}-{personId}-login.txt`), sets session (`login_person`, `token`, `login_type`), writes `{code}-refreshmailsms.txt` (this is what flips `firstLogin` at step 2.2-4 later), sends the code, redirects to the SMS page.
- `POST /auth/passchange`: guarded by `session('auth-forgot')` + authenticated user; hashes new password, clears `needs_refresh`, flushes session, redirects to login with success message.

### 3.1 Self-registration (resellers): `POST /v1/auth/register` → `registerUser`

Public registration for supplier (reseller) accounts — two modes:

- **Non-AJAX (browser form)**: validates `email`, `phone`, `password`, reCAPTCHA; rejects already-used emails via `Auth::attempt`; creates the person with `status = -1` (pending approval), role `immutable-reseller`, type `op-pert-reseller`, plus contact entities (`contphone/contmail/conttitle` under `userfacilitygroup`). Flashes a "reviewing your registration" message and redirects to login.
- **AJAX/JSON mode** (requires `X-Requested-With: XMLHttpRequest`): same core, plus `cli_id`/`cli_code`/`cli_title` client binding entities (`userclientgroup`) for white-label setups. Returns JSON.

Both paths fire `EmailServiceProvider::sendregisterMails()` to admins. There is **no auto-approval**: an admin must later activate the user (set `users.status = 1` + password), at which point the first-login loop (§2.2-4) applies. `status = -1` also means the user can't authenticate (login requires `status = '1'`).

### 3.2 Supporting auth endpoints

- `POST /api/auth/checkcode` — 2FA verify (see §2.2). Note it is registered in `api.php` WITHOUT the `v1` prefix.
- `GET /v1/me` — `MeController` (invokable) returns the authenticated user as a `UserResource` (no session required, token-only).
- `POST /api/auth/checkmail` — `checkMail()` returns `{ success: !userExists }` for the register form's inline email check.
- `GET /logout` — `AuthController::logout`: writes `UserLog(log-logout)`, flushes the session, redirects to login. **Does not revoke the Sanctum token** (see §10).

---

## 4. Session state (what lives in the session)

| Key | Set at | Meaning |
|-----|--------|---------|
| `type_key` | login (pre-2FA) | person type op_key: `op-pert-admin`, `op-pert-reseller`, ... |
| `person_id` | login | persons.id |
| `user_id` | checkCode | users.id |
| `email` | login | account email |
| `ptitle` | login | display name |
| `grp_code` | login | `'here'` (placeholder) |
| `2f_success` | checkCode | **gate for the SPA route** (`web.php` `$coalAuth` aborts 403 without it) |
| `perms` | loadPermissionsToSession | array of permission codes |
| `permission_version` | loadPermissionsToSession | version string of the cached permissions |
| `sper-{code}` | loadPermissionsToSession | boolean flag per permission code |
| `currentStatus` | checkCode | `clientPermInfo()` result (reseller gate) |
| `login_person`, `token`, `login_type`, `resend_*` | pre-2FA | 2FA plumbing |
| `auth-forgot` | reset | password reset marker |

**Note on token vs session**: the SPA authenticates via `auth:sanctum` (Bearer token). Many controllers also read session state (`session('type_key')`, `session('currentStatus')`), which only exists for stateful (browser session) requests. Helper `currentPersonTypeKey()` falls back to the DB person type for pure-token requests.

### 4.1 Global HTTP middleware stack (`bootstrap/app.php`)

Everything below is registered **globally** (not per-route):

- `ParsePutMultipart` — parses `multipart/form-data` bodies on PUT/PATCH into `$request` + `UploadedFile`s (PHP's `request_parse_body()`), since PHP natively doesn't populate `$_FILES` for PUT. This is what makes the form save envelopes work on edits. (See `form-system-mechanics.md` §5.)
- `CspMiddleware` — nonce-based CSP (`script-src 'nonce-...'`), hardcoded + `CSP_ADDITIONAL_HOSTS`/`APP_URL`/`ASSET_URL` host lists, Permissions-Policy; nonce is injected into `<script>`/`<style>` tags. Skipped when `IS_TEST=true`.
- `TrustProxies` — `trustProxies('*', ...)` trusts ALL proxies (see §10 security note).
- `statefulApi()` — enables sessions for API requests that send a session cookie (this is why SPA requests have session state while pure Bearer-token clients don't).
- **CSRF validation is globally disabled** (`validateCsrfTokens(except: ['*'])`) — the app relies on the 2FA token + Sanctum bearer auth instead. Note for reuse: acceptable for an internal SPA, but keep it in mind.
- `StartSession` is NOT appended globally (commented out) — sessions start only via `statefulApi()`/web group.

---

## 5. Permission session machinery (`PermissionService`)

### 5.1 Cache + version keys

- `permissions.user.{personId}` → canonical permission array (file cache, 30-day TTL, store configurable via `PERMISSIONS_CACHE_STORE`).
- `permissions.user.version.{personId}` → microsecond-resolution version string (`(int)(microtime(true) * 1e6)`), so two bumps in the same second still differ.

### 5.2 `loadPermissionsToSession($user)`

Reads canonical perms + version → forgets old `sper-*` flags → writes `session('perms')`, `session('permission_version')`, and `sper-*` per code.

### 5.3 `ensureSessionFreshness($user)`

Compares `session('permission_version')` with the cached version; reloads session flags when stale. Called by `getPermissions` and inside every `has()`.

### 5.4 `has($user, $key)`

1. `all` + DEV_ADMIN → true.
2. `ensureSessionFreshness`.
3. `session('sper-'.$key)` → true.
4. cached permissions array contains key → true.
5. DEV_ADMIN → true (superuser fallback).

### 5.5 `bumpUserPermissionVersion($personId, $newStatus = null)`

Writes a fresh version, **forgets** the cached permission list, and updates `active_sessions.permission_version` (+ optional `current_status`) for all users of that person. Used after any permission/role/status change.

### 5.6 `forceLogoutPerson($personId, $reason)`

Flags all `active_sessions` rows of the person: `force_logout = true`, reason, timestamp. Logs a `UserLog` (`log-user-logout`).

---

## 6. `clientPermInfo` — the reseller gate

`PersonsServiceProvider::clientPermInfo($personQnId, $typeKey)` returns:

```json
{
  "canProceed": true,          // reseller has completed required client info
  "canResponse": true,         // reseller's required signature files are all approved
  "clientQnid": "uuid",
  "clientTitle": "...",
  "clientQnidList": ["uuid", ...],
  "rejectedFiles": [...]
}
```

- Derives the client list from `cliid**` entities in `op-doc-user-client-form`.
- `canProceed = false` when a bound client doc lacks `cont_imza_file**` entities.
- `canResponse = false` when any active signature file's last status ≠ `doc_file_accepted`.
- **Refreshed at login, on `GET /v1/getpermissions` (when missing/stale), and after client-document updates.**

This value is what blocks suppliers from offering without approved files (`DocumentController` offer gates) and scopes their list queries.

---

## 7. Per-request middleware: `CheckPermissionVersion`

Runs on every protected route (`auth:sanctum` + this middleware, both `routes/api.php` and `routes/web.php`).

1. **Locate the active session row** — by `token_id` (from bearer token, `explode('|')[0]`) or by `session_id`.
2. **Force-logout check** — if `force_logout`:
   - Deletes the Sanctum token, logs out web session, invalidates + regenerates session token.
   - Deletes the `active_sessions` row.
   - AJAX → `401 { success:false, message:'force_logout', reason }`; browser → redirect to login with error.
3. **Permission-version check** — if `active.permission_version !== cached version`:
   - `loadPermissionsToSession($user)` (transparent refresh — no user-visible failure), update row's version/current_status/last_seen, continue.
4. **Touch `last_seen`** and continue.

The `/v1/getpermissions` endpoint is explicitly allowed to run a soft refresh (it calls `ensureSessionFreshness` itself), so a stale session can always re-sync.

---

## 8. Active session lifecycle

| Event | Effect |
|-------|--------|
| Login (checkCode) | old sessions of the person flagged `force_logout`; new `active_sessions` row created |
| Any request | `last_seen` updated |
| Permission/role/status change | version bumped; middleware refreshes all live sessions transparently |
| Admin disables user / changes status | `forceLogoutPerson` called from `setPerson` |
| `CleanActiveSessions` (cron) | removes stale rows (see command) |
| `active_sessions.last_seen >= now() - 1 min` | powers `is_active` in `User::tableList` |

**Token revocation nuance:** web `logout` (`GET /logout`) only flushes the session — the Sanctum token row in `personal_access_tokens` is *not* deleted there. The real kill-switch for tokens is `forceLogoutPerson` → middleware token deletion. A leaked token from a forced-out session is destroyed on its next use.

---

## 9. Frontend auth handling

- `resources/js/stores/auth.js`: holds `personId`, `currentStatus`, `typeKey`, `userName`, `permissions`; `getPermissions()` calls `GET /v1/getpermissions` (returns full list for DEV_ADMIN, else `session('perms')`). Also has a **30-second heartbeat** (`startHeartbeat()`, invoked at app init) that re-polls `getPermissions()` — this is how currentStatus/permissions stay fresh on the client between middleware refreshes.
- `resources/js/lib/pickle.js`: intercepts `401` responses —
  - `permission_changed` → refresh permissions and retry the request (rarely triggered now; backend refreshes transparently).
  - `force_logout` → show the reason, clear auth state, redirect to login (no retry).
- `web.php` `$coalAuth` closure: the SPA view is served only when `session('type_key')` and `session('2f_success')` are both set — otherwise 403.

---

## 10. Security notes / sharp edges

1. **2FA code files** are plaintext on the `local` disk with a 120s TTL and single-use — adequate but worth replacing with a hashed store in a new system.
2. **`DEV_ADMIN` is a god-mode backdoor**: fixed code `111111`, all permissions, bypasses first-login, excluded from user listings. Configurable via `.env`; keep it out of production.
3. **Lockout is per-email via cache** — cache eviction resets it; use DB-backed throttling for stricter guarantees.
4. **Session flags vs token requests** — controllers mixing `session()` reads with Bearer-token requests can misbehave; prefer `currentPersonTypeKey()`-style fallbacks.
5. **`logout` doesn't revoke the API token** — new system should revoke tokens on logout.
6. **Login attempts are logged** (`log-login-failed`, `log-lock`, `log-login`, `log-login-code-failed`, `log-logout`) — cheap and useful; keep the pattern.
7. **Single-session is "last login wins"** — enforced at checkCode, not at credential step; an attacker with credentials but no 2FA still can't take over a session.
8. **CSRF is globally disabled** and **all proxies are trusted** (`trustProxies('*')`) — combined with session-based flows this is fine for the internal SPA but must be revisited if the app ever faces the public internet directly.
9. **`status = -1` registrations** can never log in until an admin activates them — the login lookup requires `users.status = '1'`. There's no self-service activation email; approval is manual.

---

## 11. Reuse checklist for a new system

- [ ] `active_sessions` + `CheckPermissionVersion` middleware on both web and API route groups.
- [ ] 2FA code file store (or a better secret store) + 120s TTL + single-use + resend throttle.
- [ ] `PermissionService` (cache + version + session flags) wired to `getpermissions` and permission writes.
- [ ] `clientPermInfo`-style per-role gates kept in session and re-validated server-side per request.
- [ ] First-login / `needs_refresh` password-change loop.
- [ ] Force-logout cleanup cron (`CleanActiveSessions`) + stale-record purge at login.
- [ ] Audit `UserLog` rows at every auth transition.