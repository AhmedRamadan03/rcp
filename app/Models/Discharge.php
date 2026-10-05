<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discharge extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }

    

    public function file()
    {
        return $this->belongsTo(File::class);
    }

}
