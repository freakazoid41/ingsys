<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SysRoleTemplateAudit Model
 * 
 * Tracks changes to role templates for audit purposes
 */
class SysRoleTemplateAudit extends Model
{
    protected $table = 'sys_role_template_audit';

    protected $fillable = [
        'role_template_id',
        'action',
        'old_data',
        'new_data',
        'user_id',
    ];

    protected $casts = [
        'old_data' => 'json',
        'new_data' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the role template this audit record belongs to
     */
    public function roleTemplate(): BelongsTo
    {
        return $this->belongsTo(SysRoleTemplate::class, 'role_template_id');
    }

    /**
     * Get the user who made the change
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Create audit record for role template change
     */
    public static function logChange(
        SysRoleTemplate $roleTemplate,
        string $action,
        ?array $oldData = null,
        ?array $newData = null,
        $userId = null
    ): self {
        return self::create([
            'role_template_id' => $roleTemplate->id,
            'action' => $action,
            'old_data' => $oldData,
            'new_data' => $newData,
            'user_id' => $userId ?? auth('sanctum')->user()?->id ?? auth()->user()?->id,
        ]);
    }
}
