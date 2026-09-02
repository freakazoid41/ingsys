<?php

namespace App\Services;

use App\Providers\PersonsServiceProvider;
use App\Models\ActiveSession;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PermissionService
{
    protected PersonsServiceProvider $personProvider;
    protected string $cacheStore;

    public function __construct(PersonsServiceProvider $personProvider = null)
    {
        $this->personProvider = $personProvider ?: new PersonsServiceProvider();
        $this->cacheStore = config('permissions.cache_store', env('PERMISSIONS_CACHE_STORE', 'file'));
    }

    public function getCacheStore(): string
    {
        return $this->cacheStore;
    }

    public function getCache()
    {
        return Cache::store($this->getCacheStore());
    }

    public function getUserPermissionCacheKey($personId): string
    {
        return "permissions.user.{$personId}";
    }

    public function getUserPermissionVersionCacheKey($personId): string
    {
        return "permissions.user.version.{$personId}";
    }

    public function cacheUserPermissions($personId, array $permissions): string
    {
        $version = $this->freshVersion();
        $this->getCache()->put($this->getUserPermissionCacheKey($personId), $permissions, now()->addDays(30));
        $this->getCache()->put($this->getUserPermissionVersionCacheKey($personId), $version, now()->addDays(30));
        return $version;
    }

    /**
     * Microsecond-resolution version string so two bumps within the same second
     * still produce distinct versions (time() collisions hid changes).
     * Casting through int avoids scientific (E) notation on large floats.
     */
    private function freshVersion(): string
    {
        return (string)(int)(microtime(true) * 1_000_000);
    }

    public function getCachedUserPermissions($personId): array
    {
        $cacheKey = $this->getUserPermissionCacheKey($personId);
        $permissions = $this->getCache()->get($cacheKey);
        if ($permissions === null) {
            $this->refreshUserPermissionCache($personId);
            return $this->getCache()->get($cacheKey, []);
        }

        return is_array($permissions) ? array_values($permissions) : [];
    }

    public function getCachedUserPermissionVersion($personId): string
    {
        $versionKey = $this->getUserPermissionVersionCacheKey($personId);
        $version = $this->getCache()->get($versionKey);
        if ($version === null) {
            $this->refreshUserPermissionCache($personId);
            return (string) $this->getCache()->get($versionKey, '');
        }

        return (string) $version;
    }

    public function refreshUserPermissionCache($personId)
    {
        $permissions = $this->personProvider->getUserPermissionsByPersonId($personId);
        return [
            'permissions' => $permissions,
            'version' => $this->cacheUserPermissions($personId, $permissions),
        ];
    }

    public function bumpUserPermissionVersion($personId, $newCurrentStatus = null): string
    {
        $version = $this->freshVersion();
        $this->getCache()->put($this->getUserPermissionVersionCacheKey($personId), $version, now()->addDays(30));
        $this->getCache()->forget($this->getUserPermissionCacheKey($personId));

        try {
            $users = DB::table('users')->where('person_id', $personId)->pluck('id')->toArray();
            if (!empty($users)) {
                $update = ['permission_version' => $version];
                if ($newCurrentStatus !== null) {
                    $update['current_status'] = $newCurrentStatus;
                }
                ActiveSession::whereIn('user_id', $users)->update($update);
            }
        } catch (\Throwable $e) {
            // swallow errors to avoid breaking callers
        }

        return $version;
    }

    public function forceLogoutPerson($personId, string $reason = null): bool
    {
        try {
            $users = DB::table('users')->where('person_id', $personId)->pluck('id')->toArray();
            if (empty($users)) {
                return false;
            }

            ActiveSession::whereIn('user_id', $users)->update([
                'force_logout' => true,
                'force_logout_reason' => $reason,
                'force_logout_at' => now(),
            ]);
            
            $authUser = auth('sanctum')->user() ?? auth()->user();
            UserLog::create([
                'user_id'     => $authUser->id ?? 0,
                'sys_code'    => $GLOBALS['SYS_CODE'] ?? 'GDZ',
                'relation'    => 'persons',
                'relation_id' => $personId,
                'type_id'     => Sys_options::where('op_key', 'log-user-logout')->value('id') ?? 0,
                'description' => json_encode([ 'desc' => 'Forcibly logged out user by person_id', 'reason' => $reason ], JSON_UNESCAPED_UNICODE),
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function invalidateUserPermissionCache($personId): void
    {
        $this->getCache()->forget($this->getUserPermissionCacheKey($personId));
        $this->getCache()->forget($this->getUserPermissionVersionCacheKey($personId));
    }

    public function has($user, string $permissionKey): bool
    {
        if (!$user || empty($user->person_id)) {
            return false;
        }

        if ($permissionKey === 'all' && in_array($user->email, [env('DEV_ADMIN')], true)) {
            return true;
        }

        $this->ensureSessionFreshness($user);

        if (session('sper-'.$permissionKey) === true) {
            return true;
        }

        $permissions = $this->getCachedUserPermissions($user->person_id);
        if (in_array($permissionKey, $permissions, true)) {
            return true;
        }

        if (in_array($user->email, [env('DEV_ADMIN')], true)) {
            return true;
        }

        return false;
    }

    public function loadPermissionsToSession($user)
    {
        if (!$user || empty($user->person_id)) {
            return false;
        }

        $permissions = $this->getCachedUserPermissions($user->person_id);
        $version = $this->getCachedUserPermissionVersion($user->person_id);

        $oldPerms = session('perms', []);
        if (is_array($oldPerms)) {
            foreach ($oldPerms as $perm) {
                session()->forget('sper-'.$perm);
            }
        }

        session(['perms' => $permissions, 'permission_version' => $version]);

        if (is_array($permissions)) {
            foreach ($permissions as $perm) {
                session(['sper-'.$perm => true]);
            }
        }

        return true;
    }

    public function ensureSessionFreshness($user)
    {
        if (!$user || empty($user->person_id)) {
            return false;
        }

        $currentVersion = $this->getCachedUserPermissionVersion($user->person_id);
        if (session('permission_version') !== $currentVersion) {
            $this->loadPermissionsToSession($user);
        }

        return true;
    }
}
