<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActiveSession extends Model
{
    protected $table = 'active_sessions';

    protected $fillable = [
        'user_id',
        'token_id',
        'session_id',
        'ip',
        'user_agent',
        'current_status',
        'permission_version',
        'last_seen',
        'force_logout',
        'force_logout_reason',
        'force_logout_at',
    ];

    protected $casts = [
        'current_status' => 'array',
        'force_logout' => 'boolean',
        'force_logout_at' => 'datetime',
    ];
}
