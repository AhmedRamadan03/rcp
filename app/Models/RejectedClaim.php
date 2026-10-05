<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RejectedClaim extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function supervisorUser()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id', 'id');
    }

    public function attachments()
    {
        return $this->belongsToMany(File::class, 'rejected_claim_files')
            ->withPivot('file_path')
            ->withTimestamps();
    }
}
