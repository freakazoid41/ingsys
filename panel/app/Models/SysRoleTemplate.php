<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SysRoleTemplate Model
 * 
 * Stores role templates with their associated permissions.
 * Replaces JSON file storage at storage/entities/coal_roles_templates.json
 */
class SysRoleTemplate extends Model
{
    protected $table = 'sys_role_templates';

    protected $fillable = [
        'name',
        'permissions',
        'description',
        'immutable',
        'op_key'
    ];

    protected $casts = [
        'permissions' => 'json',
        'immutable' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the audit history for this role template
     */
    public function audits()
    {
        return $this->hasMany(SysRoleTemplateAudit::class, 'role_template_id');
    }

    /**
     * Convert to array format matching the old JSON structure
     */
    public function toJsonFormat(): array
    {
        return [
            'id' => (string)$this->id,
            'name' => $this->name,
            'op_key' => $this->op_key,
            'permissions' => $this->permissions ?? [],
            'description' => $this->description,
            'immutable' => $this->immutable,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
