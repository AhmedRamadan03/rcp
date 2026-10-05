<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TargetHistory extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(Target::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }


    public function toArray()
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'target_id' => $this->target_id,
            'target' => $this->current_target,
            'archieved' => $this->target->achieved,
            'remaining' => $this->current_target - $this->target->achieved,
            'type' => $this->type,
            'author_id' => $this->author_id,
            'author_name' => $this->author?->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
