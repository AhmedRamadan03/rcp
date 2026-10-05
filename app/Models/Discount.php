<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function request()
    {
        return $this->belongsTo(StatusChangeRequest::class, 'request_id', 'id');
    }
}
