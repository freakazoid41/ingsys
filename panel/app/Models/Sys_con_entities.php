<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Sys_con_entities extends Model
{
    use HasFactory;

    public static $rules = [
        'conn_id'     => 'required',
        'entity_tag'     => 'required',
        'entity_value'   => 'required',
    ];

    protected $fillable = [
        'conn_id',
        'table_tag',
        'entity_tag',
        'entity_value',
        'qnid'
    ];

    protected static function boot() {
        parent::boot();

        static::creating(function ($post) {
            $post->qnid = (string) Str::uuid();
            // add other column as well
        });

    }
}
