<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CloseClaim extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id', 'id');
    }

    public function reason()
    {
        return $this->belongsTo(CloseReason::class, 'reason_id', 'id');
    }
}
