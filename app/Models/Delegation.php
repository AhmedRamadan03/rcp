<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Delegation extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $with = ['fromUser','toUser'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
    ];

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to');
    }

    public function fromUserWithTrashed()
    {
        return $this->belongsTo(User::class, 'from')->withTrashed();
    }

    public function toUserWithTrashed()
    {
        return $this->belongsTo(User::class, 'to')->withTrashed();
    }

}
