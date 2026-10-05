<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClaimComment extends Model
{
    use HasFactory;

    /**
     * Get the claim that owns the ClaimComment
     */
    public function claim(): BelongsTo
    {
        return $this->belongsTo(Claim::class, 'claim_id', 'id');
    }

    /**
     * Get the user that owns the ClaimComment
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reminder_date' => 'datetime:Y-m-d H:i',
        ];
    }


    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'claim_id' => $this->claim_id,
            'claim_number' => $this->claim?->claim_number,
            'user_id' => $this->user_id,
            'user_name' => $this->user->name,
            'user_role' => $this->user->role->title, 
            'body' => $this->body,
            'seen' => $this->seen,
            'is_reminder' => !is_null($this->reminder_date),
            'reminder_date' => $this->reminder_date,
            'is_court_reminder' => $this->is_court_reminder,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
