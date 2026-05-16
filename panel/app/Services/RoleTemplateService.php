<?php

namespace App\Services;

use App\Models\SysRoleTemplate;
use App\Models\SysPermissionCatalog;
use App\Models\SysNotificationType;
use App\Models\SysRoleTemplateAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * RoleTemplateService
 * 
 * Handles all role template operations with built-in file caching.
 * Replaces direct file I/O from PersonsServiceProvider.
 * All data is stored in database with automatic file cache for performance.
 */
class RoleTemplateService
{
    const CACHE_TTL = 3600; // 1 hour in seconds
    const CACHE_KEY_ROLES = 'sys_role_templates_all';
    const CACHE_KEY_PERMISSIONS = 'sys_permission_catalogs_all';
    const CACHE_KEY_NOTIFICATIONS = 'sys_notification_types_all';

    /**
     * Get all role templates with file cache
     */
    public function getRoleTemplates(): array
    {
        return Cache::store('file')->remember(
            self::CACHE_KEY_ROLES,
            self::CACHE_TTL,
            function () {
                return SysRoleTemplate::all()
                    ->map(fn ($role) => $role->toJsonFormat())
                    ->values()
                    ->toArray();
            }
        );
    }

    /**
     * Save role templates and invalidate cache
     */
    public function saveRoleTemplates(array $roles): bool
    {
        try {
            foreach ($roles as $roleData) {
                $query = [];
                if (!empty($roleData['id'])) {
                    $query['id'] = $roleData['id'];
                } elseif (!empty($roleData['op_key'])) {
                    $query['op_key'] = $roleData['op_key'];
                } else {
                    $query['name'] = $roleData['name'];
                }

                $existingRole = SysRoleTemplate::where($query)->first();
                $preservedOpKey = $existingRole?->op_key ?? null;

                $roleTemplate = SysRoleTemplate::updateOrCreate(
                    $query,
                    [
                        'name' => $roleData['name'],
                        'permissions' => $roleData['permissions'] ?? [],
                        'description' => $roleData['description'] ?? null,
                        'immutable' => $roleData['immutable'] ?? false,
                        'op_key'    => $roleData['op_key'] ?? $preservedOpKey ?? 'role-'.date('Ymdhi')
                    ]
                );  

                // Log audit trail
                SysRoleTemplateAudit::logChange(
                    $roleTemplate,
                    'updated',
                    null,
                    $roleData,
                    auth('sanctum')->user()?->id ?? auth()->user()?->id
                );
            }

            // Invalidate cache
            $this->invalidateCaches();

            return true;
        } catch (\Exception $e) {
            Log::error('RoleTemplateService::saveRoleTemplates error', [
                'error' => $e->getMessage(),
                'roles' => $roles,
            ]);
            return false;
        }
    }

    /**
     * Delete a role template
     */
    public function deleteRoleTemplate($id): ?array
    {
        try {
            $roleTemplate = SysRoleTemplate::where('id', $id)->first();

            if (!$roleTemplate) {
                return null;
            }

            // Store old data for audit
            $oldData = $roleTemplate->toJsonFormat();

            // Delete the template
            $roleTemplate->delete();

            // Log audit trail
            SysRoleTemplateAudit::create([
                'role_template_id' => $id,
                'action' => 'deleted',
                'old_data' => $oldData,
                'new_data' => null,
                'user_id' => auth('sanctum')->user()?->id ?? auth()->user()?->id,
            ]);

            // Invalidate cache
            $this->invalidateCaches();

            // Return remaining roles
            return $this->getRoleTemplates();
        } catch (\Exception $e) {
            Log::error('RoleTemplateService::deleteRoleTemplate error', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);
            return null;
        }
    }

    /**
     * Get all permission catalogs with file cache - Returns as hierarchical tree
     */
    public function getPermissionCatalogs(): array
    {
        return Cache::store('file')->remember(
            self::CACHE_KEY_PERMISSIONS,
            self::CACHE_TTL,
            function () {
                return $this->buildPermissionTree();
            }
        );
    }

    /**
     * Rebuild the permission tree structure from flat database records
     */
    private function buildPermissionTree(): array
    {
        $allPerms = SysPermissionCatalog::all()->toArray();
        $tree = [];

        // Get only parent permissions (top-level)
        foreach ($allPerms as $perm) {
            $metadata = $perm['metadata'] ?? [];
            if (empty($metadata['parent_code'])) {
                $permission = [
                    'parent_id' => $metadata['parent_id'] ?? 0,
                    'title' => $perm['title'],
                    'ttitle' => $metadata['ttitle'] ?? 'Perm_con_ops',
                    'ctitle' => $metadata['ctitle'] ?? 'type_id',
                    'group_key' => $metadata['group_key'] ?? 'op-perm',
                    'op_key' => $perm['code'],
                    'childs' => $this->getPermissionChildren($perm['code'], $allPerms),
                ];
                $tree[] = $permission;
            }
        }

        return $tree;
    }

    /**
     * Get child permissions for a given parent
     */
    private function getPermissionChildren(string $parentCode, array $allPerms): array
    {
        $children = [];

        foreach ($allPerms as $perm) {
            $metadata = $perm['metadata'] ?? [];
            if (($metadata['parent_code'] ?? null) === $parentCode) {
                $children[] = [
                    'parent_id' => $metadata['parent_id'] ?? 0,
                    'title' => $perm['title'],
                    'ttitle' => $metadata['ttitle'] ?? 'Perm_con_ops',
                    'ctitle' => $metadata['ctitle'] ?? 'type_id',
                    'op_key' => $perm['code'],
                    'childs' => $this->getPermissionChildren($perm['code'], $allPerms),
                ];
            }
        }

        return $children;
    }

    /**
     * Get all notification types with file cache - Returns as hierarchical tree
     */
    public function getNotificationTypes(): array
    {
        return Cache::store('file')->remember(
            self::CACHE_KEY_NOTIFICATIONS,
            self::CACHE_TTL,
            function () {
                return $this->buildNotificationTree();
            }
        );
    }

    /**
     * Rebuild the notification type tree structure from flat database records
     */
    private function buildNotificationTree(): array
    {
        $allNotifs = SysNotificationType::all()->toArray();
        $tree = [];

        // Get only parent notifications (top-level)
        foreach ($allNotifs as $notif) {
            $metadata = $notif['metadata'] ?? [];
            // All notifications are top-level in the original structure
            $notification = [
                'parent_id' => $metadata['parent_id'] ?? 0,
                'title' => $notif['title'],
                'group_key' => $metadata['group_key'] ?? 'op-notif',
                'op_key' => $notif['code'],
                'childs' => [], // Notifications don't have children in original JSON
            ];
            $tree[] = $notification;
        }

        return $tree;
    }

    /**
     * Get a single role template
     */
    public function getRoleTemplate($id): ?array
    {
        $template = SysRoleTemplate::find($id);
        return $template ? $template->toJsonFormat() : null;
    }

    /**
     * Get a single permission
     */
    public function getPermission($code): ?array
    {
        $permission = SysPermissionCatalog::getByCode($code);
        return $permission ? $permission->toJsonFormat() : null;
    }

    /**
     * Get a single notification type
     */
    public function getNotificationType($code): ?array
    {
        $notif = SysNotificationType::getByCode($code);
        return $notif ? $notif->toJsonFormat() : null;
    }

    /**
     * Invalidate all related caches
     */
    public function invalidateCaches(): void
    {
        Cache::store('file')->forget(self::CACHE_KEY_ROLES);
        Cache::store('file')->forget(self::CACHE_KEY_PERMISSIONS);
        Cache::store('file')->forget(self::CACHE_KEY_NOTIFICATIONS);
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'roles_cached' => Cache::store('file')->has(self::CACHE_KEY_ROLES),
            'permissions_cached' => Cache::store('file')->has(self::CACHE_KEY_PERMISSIONS),
            'notifications_cached' => Cache::store('file')->has(self::CACHE_KEY_NOTIFICATIONS),
            'cache_ttl' => self::CACHE_TTL,
        ];
    }
}
