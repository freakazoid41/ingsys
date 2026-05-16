<?php

namespace App\Console\Commands;

use App\Models\SysPermissionCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class CreatePermissionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permission:create 
                            {op_key? : The operation key (e.g., per-00, per-04-01)}
                            {title? : The title of the permission}
                            {parent_op_key? : The parent operation key (leave empty for top-level)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new permission in the system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Get arguments or prompt for them
        $op_key = $this->argument('op_key') ?? $this->ask('Enter operation key (e.g., per-00, per-04-01)');
        $title = $this->argument('title') ?? $this->ask('Enter permission title');
        $parent_op_key = $this->argument('parent_op_key') ?? $this->ask('Enter parent operation key (or press Enter for top-level)', null);

        // Validate op_key is not already used
        if (SysPermissionCatalog::where('code', $op_key)->exists()) {
            $this->error("Permission with op_key '{$op_key}' already exists!");
            return 1;
        }

        // Validate parent exists if provided
        if ($parent_op_key && !SysPermissionCatalog::where('code', $parent_op_key)->exists()) {
            $this->error("Parent permission with op_key '{$parent_op_key}' does not exist!");
            return 1;
        }

        try {
            // Prepare metadata
            $metadata = [
                'parent_code' => $parent_op_key ?: null,
                'group_key' => 'op-perm',
                'ttitle' => 'Perm_con_ops',
                'ctitle' => 'type_id',
            ];

            // Create the permission
            $permission = SysPermissionCatalog::create([
                'code' => $op_key,
                'title' => $title,
                'metadata' => $metadata,
            ]);

            // Clear cache using the service's cache keys
            Cache::store('file')->forget('sys_permission_catalogs_all');
            Cache::store('file')->forget('sys_notification_types_all');

            $this->info("✓ Permission created successfully!");
            $this->line("  Op Key: {$permission->code}");
            $this->line("  Title: {$permission->title}");
            $this->line("  Parent: " . ($parent_op_key ?: 'None (top-level)'));

            return 0;
        } catch (\Exception $e) {
            $this->error("Error creating permission: {$e->getMessage()}");
            return 1;
        }
    }
}
