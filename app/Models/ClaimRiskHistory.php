<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimRiskHistory extends Model
{
    use HasFactory;

    protected $guarded = [];
    protected $casts = [
        'risk_score' => 'decimal:2',
        'risk_factors' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class);
    }

    public function calculatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calculated_by');
    }
}