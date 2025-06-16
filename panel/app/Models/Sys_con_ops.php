<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Sys_con_ops extends Model
{
    use HasFactory;

    public static $rules = [
        'conn_id'     => 'required',
        'main_id' => 'required',
        'type_id'   => 'required',
    ];

    protected $fillable = [
        'conn_id',
        'main_id',
        'type_id',
        'sub_type_id'
    ];
}
