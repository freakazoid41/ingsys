<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SysPermissionCatalog Model
 * 
 * Stores the permission code catalog.
 * Replaces JSON file storage at storage/entities/role_details.json
 */
class SysPermissionCatalog extends Model
{
    protected $table = 'sys_permission_catalogs';

    protected $fillable = [
        'code',
        'title',
        'category',
        'subcategory',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get permission by code
     */
    public static function getByCode($code)
    {
        return self::where('code', $code)->first();
    }

    /**
     * Get all permissions by category
     */
    public static function getByCategory($category)
    {
        return self::where('category', $category)->get();
    }

    /**
     * Convert to array format matching the old JSON structure
     */
    public function toJsonFormat(): array
    {
        return [
            'code' => $this->code,
            'title' => $this->title,
            'category' => $this->category,
            'subcategory' => $this->subcategory,
            'metadata' => $this->metadata,
        ];
    }
}
