<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLog extends Model
{
    use HasFactory;
    protected static function boot() {
        parent::boot();

        static::creating(function ($post) {
            $post->sys_code = $GLOBALS['SYS_CODE'] ?? 'CATES';
            // add other column as well
        });

    }
    protected $table = 'user_logs';

    protected $fillable = [
        'sys_code',
        'user_id',
        'type_id',
        'relation_id',
        'relation',
        'description',
    ];

    protected $casts = [
        'sys_code' => 'integer',
        'user_id' => 'integer',
        'type_id' => 'integer',
        'relation_id' => 'integer',
    ];
}
