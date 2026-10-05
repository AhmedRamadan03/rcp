<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestApproval extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    public function user()
    {
        return $this->belongsTo(User::class, 'role_id', 'id');
    }

    public function request()
    {
        return $this->belongsTo(StatusChangeRequest::class, 'request_id', 'id');
    }
}
