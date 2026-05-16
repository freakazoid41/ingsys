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
        
        $this->command->info('Entity data successfully seeded from JSON files!');
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
            $roleTemplate = SysRoleTemplate::updateOrCreate(
                ['name' => $roleData['name']],
                [
                    'name' => $roleData['name'],
                    'op_key' => $roleData['id'],
                    'permissions' => $roleData['permissions'] ?? [],
                    'description' => $roleData['description'] ?? null,
                    'immutable' => strpos($roleData['id'] ?? '', 'immutable') === 0,
                ]
            );

            $this->command->line("✓ Role template seeded: {$roleTemplate->name}");
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
