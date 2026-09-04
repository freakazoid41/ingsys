<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SysRoleTemplate;
use App\Models\SysPermissionCatalog;
use App\Models\SysNotificationType;
use Illuminate\Support\Facades\Storage;

class SysRoleTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->seedRoleTemplates();
        $this->seedPermissionCatalog();
        $this->seedNotificationTypes();
        
        // Invalidate RoleTemplateService caches so /v1/roles/* immediately reflects new DB (file cache otherwise stale 1h)
        try { (new \App\Services\RoleTemplateService())->invalidateCaches(); } catch (\Throwable $e) {}
        // Also clear permission file cache for all users
        try { \Illuminate\Support\Facades\Cache::store('file')->flush(); } catch (\Throwable $e) {}
        $this->command->info('Entity data successfully seeded from JSON files! (caches cleared)');
    }

    /**
     * Seed role templates from coal_roles_templates.json
     */
    private function seedRoleTemplates(): void
    {
        $path = storage_path('entities/coal_roles_templates.json');
        
        if (!file_exists($path)) {
            $this->command->warn("⚠️  coal_roles_templates.json not found at: {$path}");
            return;
        }

        $content = file_get_contents($path);
        $roles = json_decode($content, true);

        if (!is_array($roles)) {
            $this->command->error('Invalid JSON format in coal_roles_templates.json');
            return;
        }

        foreach ($roles as $roleData) {
            $opKey = $roleData['id'] ?? $roleData['op_key'] ?? null;
            if (!$opKey) continue;
            // use op_key as unique identifier to allow renames (e.g. Satınalma KeyUser -> İş Birimi)
            $roleTemplate = SysRoleTemplate::updateOrCreate(
                ['op_key' => $opKey],
                [
                    'name' => $roleData['name'],
                    'op_key' => $opKey,
                    'permissions' => $roleData['permissions'] ?? [],
                    'description' => $roleData['description'] ?? null,
                    'immutable' => strpos($opKey, 'immutable') === 0,
                ]
            );

            $this->command->line("✓ Role template seeded: {$roleTemplate->name} ({$opKey})");
        }

        // Cleanup obsolete role templates not in JSON
        $jsonOpKeys = array_map(fn($r) => $r['id'] ?? $r['op_key'] ?? null, $roles);
        $jsonOpKeys = array_filter($jsonOpKeys);
        $dbOpKeys = SysRoleTemplate::pluck('op_key')->toArray();
        $obsoleteRoles = array_diff($dbOpKeys, $jsonOpKeys);
        foreach ($obsoleteRoles as $obs) {
            SysRoleTemplate::where('op_key', $obs)->delete();
            $this->command->warn("✗ Removed obsolete role: {$obs}");
        }
    }

    /**
     * Seed permission catalog from role_details.json
     */
    private function seedPermissionCatalog(): void
    {
        $path = storage_path('entities/role_details.json');
        
        if (!file_exists($path)) {
            $this->command->warn("⚠️  role_details.json not found at: {$path}");
            return;
        }

        $content = file_get_contents($path);
        $permissions = json_decode($content, true);

        if (!is_array($permissions)) {
            $this->command->error('Invalid JSON format in role_details.json');
            return;
        }

        $this->processPermissionHierarchy($permissions);

        // Cleanup obsolete permissions not in new JSON (e.g. per-08 removed)
        $allJsonCodes = $this->collectPermissionCodes($permissions);
        $dbCodes = SysPermissionCatalog::pluck('code')->toArray();
        $obsolete = array_diff($dbCodes, $allJsonCodes);
        foreach ($obsolete as $obs) {
            SysPermissionCatalog::where('code', $obs)->delete();
            $this->command->warn("✗ Removed obsolete permission: {$obs}");
        }
    }

    /**
     * Recursively process permission hierarchy
     */
    private function processPermissionHierarchy(array $permissions, ?string $parentCode = null): void
    {
        foreach ($permissions as $permission) {
            $code = $permission['op_key'] ?? null;
            if (!$code) {
                continue;
            }

            // Determine category and subcategory
            $parts = explode('-', $code);
            $category = $parts[0] . '-' . $parts[1]; // e.g., "per-00"
            $subcategory = isset($parts[2]) ? $parts[2] : null; // e.g., "01"

            SysPermissionCatalog::updateOrCreate(
                ['code' => $code],
                [
                    'code' => $code,
                    'title' => $permission['title'] ?? '',
                    'category' => $category,
                    'subcategory' => $subcategory,
                    'metadata' => [
                        'parent_code' => $parentCode,
                        'ttitle' => $permission['ttitle'] ?? null,
                        'ctitle' => $permission['ctitle'] ?? null,
                        'group_key' => $permission['group_key'] ?? null,
                    ],
                ]
            );

            $this->command->line("✓ Permission seeded: {$code} - {$permission['title']}");

            // Process child permissions
            if (!empty($permission['childs'])) {
                $this->processPermissionHierarchy($permission['childs'], $code);
            }
        }
    }

    private function collectPermissionCodes(array $permissions): array
    {
        $codes = [];
        foreach ($permissions as $p) {
            if (!empty($p['op_key'])) $codes[] = $p['op_key'];
            if (!empty($p['childs'])) $codes = array_merge($codes, $this->collectPermissionCodes($p['childs']));
        }
        return $codes;
    }

    /**
     * Seed notification types from notification_details.json
     */
    private function seedNotificationTypes(): void
    {
        $path = storage_path('entities/notification_details.json');
        
        if (!file_exists($path)) {
            $this->command->warn("⚠️  notification_details.json not found at: {$path}");
            return;
        }

        $content = file_get_contents($path);
        $notifications = json_decode($content, true);

        if (!is_array($notifications)) {
            $this->command->error('Invalid JSON format in notification_details.json');
            return;
        }

        foreach ($notifications as $notifData) {
            $code = $notifData['op_key'] ?? null;
            if (!$code) {
                continue;
            }

            SysNotificationType::updateOrCreate(
                ['code' => $code],
                [
                    'code' => $code,
                    'title' => $notifData['title'] ?? '',
                    'description' => $notifData['description'] ?? null,
                    'category' => $notifData['group_key'] ?? 'op-notif',
                    'metadata' => [
                        'parent_id' => $notifData['parent_id'] ?? 0,
                        'group_key' => $notifData['group_key'] ?? null,
                    ],
                ]
            );

            $this->command->line("✓ Notification type seeded: {$code} - {$notifData['title']}");
        }
    }
}
