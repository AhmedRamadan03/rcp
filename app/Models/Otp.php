<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    use HasFactory;

    protected $fillable = [
        'claim_id',
        'debtor_phone',
        'debtor_nid',
        'code',
    ];

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }
}
