<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LawDepartmentClaim extends Model
{
    //
    protected $table = 'law_department_claim';

    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function request()
    {
        return $this->belongsTo(StatusChangeRequest::class, 'request_id', 'id');
    }
}
