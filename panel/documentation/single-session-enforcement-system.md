# Single-Session Enforcement System

## What does it do?

A user can only be logged in on **one browser at a time**.

If you open the app in Firefox, then open it in Chrome — the Firefox session gets killed. You get kicked out and have to log in again.

---

## How does it work? (Simple version)

There is a database table called `active_sessions`. Every time someone logs in, the system writes a row to this table with info about that login (which browser, which user, etc.).

The trick is: **before** creating the new row, the system marks ALL old rows for that user as `force_logout = true`.

Then, every time the old browser makes a request, a piece of code (called **middleware**) checks: "Is my session marked for force logout?" If yes → the user is logged out immediately.

Think of it like a bouncer checking a list at the door. Your name gets added to the "kick out" list, and next time you try to do anything, the bouncer throws you out.

---

## Step-by-step, plain English

### Step 1: You log in on Chrome

The login process (`checkCode` in `AuthController.php`) does 3 things, in order:

1. **Kick out old sessions** — calls `forceLogoutPerson()` which finds all old sessions for this user and marks them `force_logout = true`
2. **Create a token** — for API authentication
3. **Record the new session** — inserts a new row in `active_sessions` (this one has `force_logout = false`)

### Step 2: The old Firefox tab tries to do something

The old browser makes a request (clicks a button, loads a page, whatever).

The middleware `CheckPermissionVersion` runs on **every request** (both web pages and API calls).

It looks up the session in `active_sessions`. It sees `force_logout = true`.

So it:

- Deletes your API token
- Destroys your web session (logs you out)
- Deletes the session row from the table
- Redirects you to the login page (or sends a 401 error if it's an API call)

### Step 3: You see the login screen

You're kicked out. Log in again.

---

## Wait, how does the middleware know which session is mine?

There are two ways a user can be authenticated in this app:

| Auth type | How the middleware finds your session |
|-----------|---------------------------------------|
| **Web login** (session-based) | Uses `session_id` from Laravel's session |
| **API token** (Sanctum) | Extracts the token ID from the bearer token |

The middleware tries the token ID first. If there's no token, it falls back to the session ID.

---

## When does `forceLogoutPerson()` get called?

| Situation | What happens |
|-----------|-------------|
| User logs in from a new browser | Old sessions get killed (single-session enforcement) |
| Admin changes the user's status (active/inactive) | All sessions get killed |
| Admin changes the user's role | All sessions get killed |
| Admin resets the user's password | All sessions get killed |

---

## What does the frontend do when it gets kicked?

When JavaScript makes an API call and gets back a `401` with `message: "force_logout"`, the frontend code in `pickle.js`:

- Clears the auth data (user info, permissions)
- Redirects to the login page
- Shows the reason (e.g., "Bu hesaba başka bir cihazdan giriş yapıldı.")

---

## Bonus: It also handles permission changes

The same middleware does a second thing: it checks if the user's **permissions** have changed since they logged in.

If an admin updates the user's role or permissions, the middleware detects a version mismatch and **silently reloads** the permissions into the session. The user doesn't get logged out — they just get their new permissions on the next request.

---

## Visual diagram

```
Firefox (old)                   Server                    Chrome (new)
     │                             │                           │
     │  Already logged in          │                           │
     │                             │                           │
     │                             │    User logs in here      │
     │                             │◄──────────────────────────│
     │                             │                           │
     │                             │  1. Mark old sessions     │
     │                             │     force_logout = true   │
     │                             │                           │
     │                             │  2. Create new session    │
     │                             │     force_logout = false  │
     │                             │                           │
     │  Tries to do something      │                           │
     │────────────────────────────►│                           │
     │                             │                           │
     │  Middleware checks:         │                           │
     │  "force_logout = true"      │                           │
     │                             │                           │
     │  Kills the session          │                           │
     │  Redirects to login         │                           │
     │◄────────────────────────────│                           │
     │                             │                           │
```

---

## Where are the important files?

| File | What it does |
|------|-------------|
| `app/Http/Middleware/CheckPermissionVersion.php` | The bouncer — checks if session should be killed, runs on every request |
| `app/Services/PermissionService.php` | Has the `forceLogoutPerson()` function that marks sessions |
| `app/Models/ActiveSession.php` | The model for the `active_sessions` table |
| `app/Http/Controllers/AuthController.php` | Calls forceLogout on new login |
| `app/Providers/PersonsServiceProvider.php` | Calls forceLogout on status/role change |
| `app/Http/Controllers/PersonsController.php` | Calls forceLogout on password reset |
| `resources/js/lib/pickle.js` | Frontend code that handles the kicked-out response |
| `routes/web.php` | Where the middleware is added to web routes |
| `routes/api.php` | Where the middleware is added to API routes |
| `app/Console/Commands/CleanActiveSessions.php` | Scheduled task that deletes old stale session records |
| `app/Console/Kernel.php` | Registers the cleanup command to run daily at 2 AM |

---

## Things that could go wrong (and what we did about them)

### 1. Stale "force_logout" records — ✅ FIXED

**The problem:** When a browser gets killed, the middleware deletes the session row. But if the user closes their laptop or loses internet before making another request, the row stays in the database with `force_logout = true` forever. Dead records pile up.



**Layer 1 — Login-time cleanup:** Every time a user logs in, we delete any stale `force_logout` records older than 24 hours for that user before creating the new session.

```php
// In AuthController@checkCode
ActiveSession::where('user_id', $user->id)
    ->where('force_logout', true)
    ->where('force_logout_at', '<', Carbon::now()->subDay())
    ->delete();
```

**Layer 2 — Daily cron job:** A scheduled Artisan command runs every night at 2 AM and cleans up:

- `force_logout` records older than 24 hours
- Any active session untouched for 7+ days (abandoned sessions)

```bash
php artisan active-sessions:clean
```

You can also run it manually or customize the cutoffs:

```bash
# Clean force_logout records older than 12 hours, stale sessions older than 3 days
php artisan active-sessions:clean --force-logout-hours=12 --stale-days=3
```


