<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuthenticationLog extends Model
{
    use HasFactory;

    protected $table = 'authentication_log';

    protected $fillable = [
        'authenticatable_id',
        'authenticatable_type',
        'personal_access_token_id',
        'ip_address',
        'user_agent',
        'login_at',
        'login_successful',
        'logout_at',
        'cleared_by_user',
    ];

    public function authenticatable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
