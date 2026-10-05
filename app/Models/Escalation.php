<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Escalation extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function notification()
    {
        return $this->belongsTo(Notification::class, 'notification_id', 'id');
    }
}
