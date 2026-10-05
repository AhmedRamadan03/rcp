<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Carbon\Carbon;

class VerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'otpable_id',
        'otpable_type',
        'otp',
        'purpose',
        'phone',
        'expire_at',
        'verified_at',
    ];
    protected $casts = [
        'expire_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    // Polymorphic relation
    public function otpable(): MorphTo
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeValid($query)
    {
        return $query->whereNull('verified_at')
            ->where('expire_at', '>', Carbon::now());
    }

    public function scopeForPurpose($query, string $purpose)
    {
        return $query->where('purpose', $purpose);
    }

    public function isExpired(): bool
    {
        return $this->expire_at->isPast();
    }

    public function isVerified(): bool
    {
        return !is_null($this->verified_at);
    }

    public function markVerified(): void
    {
        $this->update(['verified_at' => Carbon::now()]);
    }

}
