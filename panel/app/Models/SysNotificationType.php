<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SysNotificationType Model
 * 
 * Stores notification type definitions.
 * Replaces JSON file storage at storage/entities/notification_details.json
 */
class SysNotificationType extends Model
{
    protected $table = 'sys_notification_types';

    protected $fillable = [
        'code',
        'title',
        'description',
        'category',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get notification type by code
     */
    public static function getByCode($code)
    {
        return self::where('code', $code)->first();
    }

    /**
     * Get all notification types by category
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
            'description' => $this->description,
            'category' => $this->category,
            'metadata' => $this->metadata,
        ];
    }
}
