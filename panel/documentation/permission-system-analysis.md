# CoalApp Permission System Analysis

## 1. Overview

The CoalApp permission system is a custom, session-driven role-and-permission implementation layered on top of Laravel and Vue. It is not using Laravel Gates/Policies in a standard way; instead, it uses:

- permission codes like `per-04-02`, `per-05-01`, `per-07-02`
- role templates stored in JSON under `storage/entities/coal_roles_templates.json`
- user permission values stored in database connection tables via `sys_con_entities`
- runtime authorization via session flags and a helper function `checkPerm()`
- cached user permissions in file cache for freshness tracking
- frontend view control via `authStore.permissions` in Pinia

## 2. Core permission model

### 2.1 Permission codes and catalog

The permission catalog is defined in `panel/storage/entities/role_details.json`.

Key permission areas:

- `per-00`: Mail/notification permission base
  - `per-00-01`: Supplier registration request permission
- `per-04`: Control panel
  - `per-04-01`: User listing
  - `per-04-02`: User create/edit
  - `per-04-03`: Role & permission management
- `per-05`: Request management
  - `per-05-01`: Request listing
  - `per-05-02`: Request create/edit
- `per-06`: Company management
  - `per-06-01`: Company listing
  - `per-06-02`: Company create/edit
- `per-07`: Documents
  - `per-07-01`: Document listing
  - `per-07-02`: Document edit
- `per-08`: Offers
  - `per-08-01`: Offer listing
  - `per-08-02`: Offer edit

These codes are used both in backend authorization checks and frontend UI gating.

### 2.2 Role templates

Role templates are stored in `panel/storage/entities/coal_roles_templates.json`.

A role template has this shape:

- `id`: string or number
- `name`: display name
- `description`
- `permissions`: array of permission codes
- `created_at`

Example roles in the project include:

- `immutable-reseller`
- `immutable-admin`
- `immutable-super-admin`
- `immutable-satınalma-personeli`
- `immutable-satınalma-keyuser`

Role templates are managed through the API and frontend UI by `PersonsController::rolesTemplate()`.

## 3. Backend permission storage

### 3.1 How permissions are attached to users

Permissions are persisted by `App\\Providers\\PersonsServiceProvider::setPerson()`.

Key behavior:

- It reads `permissions` from the submitted form data.
- It stores permissions in `sys_con_entities` using `upsertConnectionEntity()`.
- The connection entity is keyed by `entity_tag` of the form:
  - `{$document->id}**userpermissiongroup**{$document->id}`
- Stored `entity_value` is JSON text of the permission array, e.g. `['per-04-02', 'per-05-01']`.

This means user permissions are not stored directly on the `users` table, but through a related connection entity structure.

### 3.2 Reading permissions

`PersonsServiceProvider::getPerson()` loads permissions via a SQL subquery and returns them as a `permissions` property.

This is the data source used during login.

### 3.3 Role-based propagation

When role templates are saved, `PersonsController::rolesTemplate()` calls:

- `PersonsServiceProvider::updateUserPermissions($item['id'], $item['permissions'])`

This updates permissions for every `User` whose `role` equals the template `id`.

### 3.4 Permission cache layer

The system now maintains a file-cache copy of each user's canonical permissions.

- permissions are cached at `permissions.user.{personId}`
- a version token is cached at `permissions.user.version.{personId}`
- the cache is refreshed after permission changes and role updates
- session state is rebuilt when the cached version differs from `session('permission_version')`

This allows session-based checks to remain fast while still reflecting updates quickly.

## 4. New Database Entity System (PostgreSQL)

### 4.1 Overview

As of April 2026, the permission, role, and notification systems have been migrated from JSON file storage to PostgreSQL database tables with a dedicated service layer architecture.

**Benefits:**
- Centralized database storage instead of file I/O
- Hierarchical data structure preservation (tree-based permissions)
- Built-in caching with Laravel file cache (1-hour TTL)
- Service layer abstraction for clean API
- Easy permission expansion via CLI command
- Audit trail for role template changes

### 4.2 Database Tables

#### 4.2.1 `sys_role_templates` TABLE

Stores immutable role definitions.

```sql
CREATE TABLE sys_role_templates (
  id BIGINT PRIMARY KEY,
  name VARCHAR(255) UNIQUE,
  description TEXT,
  permissions JSON,
  immutable BOOLEAN DEFAULT true,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
)
```

**Fields:**
- `id`: Unique role identifier
- `name`: Role display name (e.g., "Admin", "Super Admin")
- `description`: Role purpose
- `permissions`: JSON array of permission codes assigned to this role
- `immutable`: Whether this is a system role (cannot be deleted)
- `created_at`, `updated_at`: Timestamps

**Example:**
```json
{
  "id": 1,
  "name": "Admin",
  "description": "Administratör erişim",
  "permissions": ["per-04", "per-04-01", "per-04-02", "per-04-03"],
  "immutable": true
}
```

#### 4.2.2 `sys_permission_catalogs` TABLE

Stores the permission hierarchy with parent-child relationships.

```sql
CREATE TABLE sys_permission_catalogs (
  id BIGINT PRIMARY KEY,
  code VARCHAR(255) UNIQUE,
  title VARCHAR(255),
  metadata JSON,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
)
```

**Fields:**
- `id`: Internal ID
- `code`: Unique permission code (e.g., "per-04-02")
- `title`: Display title in Turkish
- `metadata`: JSON containing:
  - `parent_code`: Parent permission code (null for top-level)
  - `group_key`: "op-perm" (operation permission type)
  - `ttitle`: Translation key
  - `ctitle`: Category title key
- `created_at`, `updated_at`: Timestamps

**Example:**
```json
{
  "id": 4,
  "code": "per-04-02",
  "title": "Kullanıcı Oluşturma / Düzenleme",
  "metadata": {
    "parent_code": "per-04",
    "group_key": "op-perm",
    "ttitle": "Perm_con_ops",
    "ctitle": "type_id"
  }
}
```

**Hierarchy Structure:**
```
per-00 (Bildirimler - Notifications)
├── per-00-01 (Bildirim Yönetimi)

per-04 (Kontrol Paneli - Control Panel)
├── per-04-01 (Kullanıcı Listeleme - User Listing)
├── per-04-02 (Kullanıcı Oluşturma/Düzenleme - User Create/Edit)
├── per-04-03 (Rol ve Yetki Yönetimi - Role & Permission Management)

per-05 (Talep Yönetimi - Request Management)
├── per-05-01 (Talep Listeleme - Request Listing)
├── per-05-02 (Talep Oluşturma/Düzenleme - Request Create/Edit)

per-06 (Firma Yönetimi - Company Management)
├── per-06-01 (Firma Listeleme - Company Listing)
├── per-06-02 (Firma Oluşturma/Düzenleme - Company Create/Edit)

per-07 (Dökümanlar - Documents)
├── per-07-01 (Döküman Listeleme - Document Listing)
├── per-07-02 (Döküman Düzenleme - Document Edit)

per-08 (Teklifler - Offers)
├── per-08-01 (Teklif Listeleme - Offer Listing)
├── per-08-02 (Düzenleme - Edit)
```

#### 4.2.3 `sys_notification_types` TABLE

Stores notification type definitions.

```sql
CREATE TABLE sys_notification_types (
  id BIGINT PRIMARY KEY,
  code VARCHAR(255) UNIQUE,
  title VARCHAR(255),
  metadata JSON,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
)
```

**Fields:**
- `id`: Internal ID
- `code`: Unique notification code (e.g., "notif-00")
- `title`: Display title in Turkish
- `metadata`: JSON containing:
  - `group_key`: "op-notif" (operation notification type)
- `created_at`, `updated_at`: Timestamps

**Example:**
```json
{
  "id": 1,
  "code": "notif-00",
  "title": "Tedarikçi Kayıt Başvurusu",
  "metadata": {
    "group_key": "op-notif"
  }
}
```

#### 4.2.4 `sys_role_template_audit` TABLE

Stores audit trail of all role template changes.

```sql
CREATE TABLE sys_role_template_audit (
  id BIGINT PRIMARY KEY,
  role_template_id BIGINT,
  action VARCHAR(50),
  old_data JSON,
  new_data JSON,
  user_id BIGINT,
  created_at TIMESTAMP,
  updated_at TIMESTAMP
)
```

**Fields:**
- `id`: Audit record ID
- `role_template_id`: Foreign key to role template
- `action`: Type of change ("created", "updated", "deleted")
- `old_data`: Previous state
- `new_data`: New state
- `user_id`: User who made the change
- `created_at`, `updated_at`: Timestamps

### 4.3 Eloquent Models

#### 4.3.1 `SysRoleTemplate`

```php
class SysRoleTemplate extends Model {
    protected $casts = [
        'permissions' => 'array',
        'immutable' => 'boolean',
    ];
    
    public function audits() {
        return $this->hasMany(SysRoleTemplateAudit::class);
    }
    
    public function toJsonFormat() {
        return [...];  // Converts to API response format
    }
}
```

#### 4.3.2 `SysPermissionCatalog`

```php
class SysPermissionCatalog extends Model {
    protected $casts = [
        'metadata' => 'array',
    ];
    
    public function scopeGetByCode($query, $code) {
        return $query->where('code', $code)->first();
    }
}
```

#### 4.3.3 `SysNotificationType`

```php
class SysNotificationType extends Model {
    protected $casts = [
        'metadata' => 'array',
    ];
    
    public function scopeGetByCode($query, $code) {
        return $query->where('code', $code)->first();
    }
}
```

#### 4.3.4 `SysRoleTemplateAudit`

```php
class SysRoleTemplateAudit extends Model {
    protected $casts = [
        'old_data' => 'array',
        'new_data' => 'array',
    ];
    
    public static function logChange(
        $roleTemplate,
        $action,
        $oldData,
        $newData,
        $userId = null
    ) {
        return self::create([...]);
    }
}
```

### 4.4 RoleTemplateService Layer

Location: `app/Services/RoleTemplateService.php`

Provides high-level operations with automatic caching and tree building.

**Key Methods:**

```php
// Get all role templates with caching
getRoleTemplates(): array

// Save role templates and invalidate cache
saveRoleTemplates(array $roles): bool

// Delete a role template
deleteRoleTemplate($id): ?array

// Get all permission catalogs as hierarchical tree
getPermissionCatalogs(): array

// Get all notification types as hierarchical tree
getNotificationTypes(): array

// Get a single permission by code
getPermission(string $code): ?array

// Get a single notification by code
getNotificationType(string $code): ?array

// Clear all caches
invalidateCaches(): void
```

**Caching Strategy:**
- Cache store: Laravel file cache (`storage/framework/cache/`)
- Cache TTL: 3600 seconds (1 hour)
- Cache keys:
  - `sys_role_templates_all` - Role templates list
  - `sys_permission_catalogs_all` - Permission hierarchy tree
  - `sys_notification_types_all` - Notification types tree
- Cache is automatically invalidated on data changes

**Tree Building:**

The service rebuilds hierarchical tree structures from flat database records:

```php
private function buildPermissionTree(): array {
    // Get all permissions from database
    $allPerms = SysPermissionCatalog::all()->toArray();
    
    // Filter to parent permissions (parent_code = null)
    foreach ($allPerms as $perm) {
        if (empty($metadata['parent_code'])) {
            // Add to root level
            $permission['childs'] = $this->getPermissionChildren($perm['code'], $allPerms);
        }
    }
    
    return $tree;  // Hierarchical structure
}

private function getPermissionChildren($parentCode, $allPerms): array {
    // Recursively find and nest children
    // Supports unlimited nesting levels
}
```

**Output Format:**

```json
[
  {
    "op_key": "per-04",
    "title": "Kontrol Paneli",
    "group_key": "op-perm",
    "childs": [
      {
        "op_key": "per-04-01",
        "title": "Kullanıcı Listeleme",
        "childs": []
      },
      {
        "op_key": "per-04-02",
        "title": "Kullanıcı Oluşturma / Düzenleme",
        "childs": []
      }
    ]
  }
]
```

### 4.5 Data Migration from JSON to PostgreSQL

**Original JSON Files (Legacy):**
- `storage/entities/coal_roles_templates.json` - Role definitions
- `storage/entities/role_details.json` - Permission catalog
- `storage/entities/role_notifications.json` - Notification types

**Migration Process:**

1. **Seeder**: `database/seeders/SysRoleTemplateSeeder.php`
   - Reads all three JSON files
   - Inserts records into PostgreSQL tables
   - Maintains hierarchy and metadata

2. **Build Script**: `./prjBuildLive`
   - Runs `php artisan migrate` (creates new tables)
   - Runs `php artisan db:seed --class=SysRoleTemplateSeeder` (populates data)
   - Clears application cache
   - Ready for use

**Data Integrity:**
- ✅ All 18 permissions migrated with proper hierarchies
- ✅ All 5 role templates migrated with permission assignments
- ✅ All 4 notification types migrated
- ✅ Metadata preserved (parent codes, group keys, titles)

### 4.6 Adding New Permissions via CLI

**Command:** `php artisan permission:create`

**Usage:**

Option 1: Direct arguments
```bash
php artisan permission:create per-09 "Raporlar" 
php artisan permission:create per-09-01 "Rapor Listeleme" per-09
```

Option 2: Interactive mode
```bash
php artisan permission:create
# Enter operation key: per-10
# Enter permission title: Ayarlar
# Enter parent operation key (or press Enter for top-level): [Enter]
```

**Features:**
- Validates op_key doesn't already exist
- Validates parent permission exists
- Automatically sets metadata structure
- Clears cache for fresh tree generation
- Supports unlimited nesting levels
- Non-destructive (only adds, doesn't modify existing permissions)

**Implementation:**
- Location: `app/Console/Commands/CreatePermissionCommand.php`
- Creates permission in database
- Clears file cache automatically
- Shows success confirmation

### 4.7 Integration Points

**PermissionService:**
- Centralizes permission cache keys, versioning, refresh, and runtime authorization.
- Provides `has()`, `loadPermissionsToSession()`, `ensureSessionFreshness()`, `refreshUserPermissionCache()`, `bumpUserPermissionVersion()`, and `invalidateUserPermissionCache()`.
- Uses `permissions.cache_store` / `PERMISSIONS_CACHE_STORE` to choose the cache backend.

**checkPerm() compatibility:**
- `panel/app/Helpers/PermissionHelpers.php` now delegates to `PermissionService->has()`.
- This keeps legacy checks working while new authorization paths move to the service.

**Controllers:**
- `App\Http\Controllers\PersonsController` now uses `PermissionService->has($user, $perm)` instead of direct `checkPerm()` in most authorization paths.
- `App\Http\Controllers\SystemController` and `App\Http\Controllers\DocumentController` likewise use the service for user permission checks.
- `AuthController::getPermissions()` uses `PermissionService` to refresh session state before returning permissions.

**Provider changes:**
- `PersonsServiceProvider` now delegates permission data extraction to `PermissionService` for caching and version management.
- It still writes user permission entities into `sys_con_entities`, but permission refresh and cache invalidation are handled in the service layer.

**Route-level middleware:**
- `routes/api.php` applies `auth:sanctum` and `App\Http\Middleware\CheckPermissionVersion` to most protected API routes.
- Protected endpoints now include administrative utilities such as `/v1/admin/refresh-perms/{personId}` and `/v1/admin/force-logout/{personId}`.
- This middleware checks active session versioning and refreshes stale permission state transparently.

### 4.8 Active session tracking and live permission refresh

A new active session model now tracks authenticated users and permission version state.

**`active_sessions` table:**
- `user_id`, `token_id`, `session_id`
- `ip`, `user_agent`
- `current_status`
- `permission_version`
- `last_seen`
- standard timestamps

**`App\Models\ActiveSession`:**
- Represents session/token activity per authenticated user.
- Enables detection of currently active users from the DB.
- Works for both web session and Sanctum token requests.

**Live refresh flow:**
- `PermissionService::bumpUserPermissionVersion($personId)` writes a new version token to the cache.
- It also invalidates the cached permission list and updates `active_sessions.permission_version` for all users sharing that person.
- When a protected request arrives, `CheckPermissionVersion` compares the active session/version against the cache version.
- If stale, it refreshes session permissions through `PermissionService`, updates the active session record, and continues the request.

**Client-side fallback:**
- `resources/js/lib/pickle.js` contains logic for a 401/`permission_changed` retry path.
- It also handles `401`/`force_logout` responses by showing a forced-logout message and preventing retries.
- In the current backend flow, the middleware refreshes stale permissions transparently, so the hard reject path should rarely be triggered.

**Force logout support:**
- Administrators can call `/v1/admin/force-logout/{personId}` to mark a person’s active sessions for immediate termination.
- The system stores `force_logout`, `force_logout_reason`, and `force_logout_at` on `active_sessions`.
- When a forced-logout session makes a request, `CheckPermissionVersion` returns a `force_logout` response instead of allowing further access.

**Active user detection:**
- `App\Models\User::tableList()` computes `is_active` by checking `active_sessions.last_seen >= now() - interval '1 minutes'`.
- This means the database can answer "currently active users" queries directly from recent `active_sessions` rows.

### 5. Login and runtime authorization

The overall login and authorization sequence has been refined to a shared permission service.

**Login flow:**
- `AuthController::checkCode()` still authenticates the user and builds session state.
- After login, it calls `loadUserPermissionsToSession($user)` to populate session permission flags.
- It creates an `ActiveSession` record including `token_id`, `session_id`, `current_status`, and `permission_version`.

**PermissionService session model:**
- `loadPermissionsToSession($user)` loads canonical permissions from cache.
- It writes `session('perms')`, `session('permission_version')`, and individual `session('sper-'.$perm)` flags.
- `ensureSessionFreshness($user)` compares `session('permission_version')` to the cached version and reloads if needed.

**Authorization API:**
- `AuthController::getPermissions()` uses `PermissionService->ensureSessionFreshness(auth()->user())` before returning permissions.
- It returns:
  - `personId`
  - `currentStatus`
  - `typeKey`
  - `permissions`

### 5.1 Permission helper vs service

`panel/app/Helpers/PermissionHelpers.php` now acts as a compatibility layer.

- `checkPerm($key)` delegates to `PermissionService->has(auth()->user(), $key)`.
- `loadUserPermissionsToSession($user)` delegates to the service.
- `ensurePermissionSessionFreshness()` delegates to the service.

This preserves existing helper-based checks while moving the real logic into a central service.

### 5.2 Special `all` permission

`PermissionService->has($user, 'all')` still grants full permission for the configured `DEV_ADMIN` user.

- This is the same superuser fallback as before.
- `AuthController::getPermissions()` returns a full permission list when `has(..., 'all')` is true.

### 6. Backend authorization points

The core controllers now rely on the service layer for permission decisions.

- `App\Http\Controllers\PersonsController`
  - user create/edit still gates sensitive fields with `per-04-02`
  - role/template management still requires `per-04-03`
  - role/item endpoints use the service for authorization
- `App\Http\Controllers\SystemController`
  - data table operations use `per-04`, `per-04-01`, `per-07`, `per-07-01`
- `App\Http\Controllers\DocumentController`
  - document and file status updates use `per-07-02`

These checks are now implemented through `PermissionService->has($authUser, $permissionCode)` instead of direct session variable reads.

### 7. Frontend permission enforcement

Frontend permission enforcement remains via Pinia and Vue, but it now aligns with the new backend permission service.

**Auth store:**
- `resources/js/stores/auth.js` stores:
  - `permissions`
  - `currentStatus`
  - `typeKey`
  - `personId`
- `getPermissions()` calls `/api/v1/getpermissions`.

**Permission refresh:**
- `resources/js/lib/pickle.js` has a fallback retry path for `401` + `permission_changed`.
- In practice, the backend middleware now refreshes stale permissions transparently, so frontend retries should be rare.

**App init:**
- `resources/js/app.js` still bootstraps by calling `authStore.getPermissions()`.
- It also loads role templates and permission items for UI rendering.

**UI gating:**
- Components still use `authStore.permissions?.includes(...)` to show/hide features.
- Common checks remain around `per-04-02`, `per-05-02`, `per-07-01`, etc.

### 8. Data storage details

#### 8.1 `users` table

The `users` table still stores:
- `email`
- `password`
- `person_id`
- `role`
- `status`
- `needs_refresh`

Roles remain string keys in `users.role`.

#### 8.2 `persons` / `sys_con_entities`

User permissions are still persisted via the generic connection model:
- `sys_con_ops`
- `sys_con_entities`

Each user permission entity uses `type_id` for `op-doc-user-permission-form` and an `entity_tag` around `userpermissiongroup`.

#### 8.3 Active sessions and live status

The new `active_sessions` table tracks who is currently active.

- `last_seen` is updated on each request through middleware.
- `permission_version` is stored per active session.
- `current_status` captures client-specific state for reseller users.

This table also powers active-user detection for listings, using a 1-minute freshness window in `User::tableList()`.

### 9. Important implementation notes and risks

#### 9.1 Centralized permission service

The major new pattern is centralizing authorization in `PermissionService`.

- This reduces scattered `session('sper-...')` checks.
- It unifies session and token permission handling.
- It makes permission refresh/versioning behave consistently across web and API requests.

#### 9.2 Versioned permission refresh

Stale permission state is now detected by comparing:
- `active_sessions.permission_version`
- cached version `permissions.user.version.{personId}`

When a mismatch occurs, middleware refreshes session state instead of failing the request.

#### 9.3 Client-side fallback

The frontend still contains a fallback for `401` + `permission_changed`, but the backend now aims to handle refresh transparently.

#### 9.4 Superuser fallback

The configured `DEV_ADMIN` email still acts as an override for permission checks.

- It remains configured in `.env`.
- It is used both in `PermissionService->has()` and in authentication logic.

#### 9.5 Permission propagation

`updateUserPermissions()` applies a role template's permissions to all users that currently have that role.

After updating the stored permissions, it now also refreshes the file cache for each affected user.

If a user’s `users.role` stays unchanged, their permissions remain synchronized via this migration flow.

#### 9.6 Forced logout behavior

Administrators can trigger a forced logout for a specific person using `/v1/admin/force-logout/{personId}`.

- This sets `force_logout = true` on all active sessions for that person.
- The session middleware returns a `401` response with `message: "force_logout"` and a human-readable reason.
- The frontend can display this reason and prompt the user to log in again.

## 10. Environment configuration

### 10.1 DEV_ADMIN

The superuser email is now configurable via environment variable:

```env
DEV_ADMIN=kadir@kontent.com.tr
```

This variable is used in:

- `AuthController::checkCode()` - sets fixed verification code `111111` for dev admin
- `AuthController::checkCode()` - sends SMS directly to dev admin phone
- `AuthController::checkCode()` - bypasses first-login check for dev admin
- `PermissionHelpers::checkPerm()` - grants all permissions automatically
- `User::where()` filtering - excludes dev admin from reports
- `coallogin.blade.php` - pre-fills login form in test mode with `IS_TEST=true`
- `UserSeeder::up()` - creates test admin user with this email

### 10.2 Changing the superuser email

To use a different development admin email:

1. Update `.env`:
   ```env
   DEV_ADMIN=admin@yourdomain.com
   ```

2. Clear application cache:
   ```bash
   php artisan cache:clear
   rm -rf bootstrap/cache/*.php
   ```

3. Re-seed the database if needed:
   ```bash
   php artisan db:seed --class=UserSeeder
   ```

All references will automatically use the new email from the environment.

## 11. Key files for the permission system

- `panel/app/Helpers/PermissionHelpers.php`
- `panel/app/Providers/PersonsServiceProvider.php`
- `panel/app/Http/Controllers/AuthController.php`
- `panel/storage/permissions` cache is managed by Laravel file cache via `PersonsServiceProvider`
- `panel/app/Http/Controllers/PersonsController.php`
- `panel/routes/api.php`
- `panel/resources/js/stores/auth.js`
- `panel/resources/js/stores/permissiondata.js`
- `panel/resources/js/app.js`
- `panel/storage/entities/coal_roles_templates.json`
- `panel/storage/entities/role_details.json`

## 12. Summary

CoalApp permissions are now implemented as a hybrid session-driven system with a centralized backend service and live session tracking.

The main runtime decision point remains `checkPerm($key)` for legacy compatibility, but the real authorization logic is centralized in `PermissionService`.

User permission state is cached and versioned by `permissions.user.{personId}` and `permissions.user.version.{personId}`.

Active sessions are tracked in `active_sessions`, allowing the system to detect live users, refresh stale permissions, and forcibly terminate sessions when needed.

Administrators can now force a user out of the system via `/v1/admin/force-logout/{personId}`.

The frontend still reads permissions from `/api/v1/getpermissions` and uses those values to show/hide UI elements, while the backend enforces the same codes consistently.

---

If you want, I can also add a second document that maps every permission code to the exact controllers/routes where it is enforced.