<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IpLoginLock extends Model
{
    protected $fillable = ['ip_address', 'failed_attempts', 'block_count', 'locked_until'];

    protected $casts = [
        'locked_until' => 'datetime',
    ];
}