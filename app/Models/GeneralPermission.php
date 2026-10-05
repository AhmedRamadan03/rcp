<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GeneralPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'permission',
        'path',
        'type',
        'permission_mode',
    ];

    protected $casts = [
        'permission_mode' => 'array',
    ];
}
