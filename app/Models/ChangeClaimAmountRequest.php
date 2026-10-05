<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChangeClaimAmountRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function reqeust()
    {
        return $this->belongsTo(StatusChangeRequest::class, 'reqeust_id', 'id');
    }
}
