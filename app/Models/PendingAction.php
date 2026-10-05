<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PendingAction extends Model
{
    protected $guarded = [];

    public function claim(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
