<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Target extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected $appends = ['percentage', 'width_percentage'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(TargetHistory::class, 'target_id', 'id');
    }

    public function period()
    {
        return $this->belongsTo(TargetPeriod::class, 'period_id', 'id');
    }

    protected function percentage(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $this->target > 0 ? round($this->achieved * 100 / $this->target, 2) : 0
        );
    }

    protected function widthPercentage(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->percentage > 100 ? 100 : $this->percentage
        );
    }
}
