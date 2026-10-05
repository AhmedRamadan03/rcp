<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimStatusHistory extends Model
{
    use HasFactory;

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }


    public function file()
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function toArray()
    {
        $fileKey = null;
        if ($this->file_id) {
            $related = $this->relationLoaded('file')
                ? $this->getRelation('file')
                : File::find($this->file_id);
            $fileKey = $related?->uuid;
        }
        $fileKey ??= $this->attributes['file'] ?? null;

        return [
            'id' => $this->id,
            'claim_id' => $this->claim_id,
            'user_id' => $this->user_id,
            'previous_status' => $this->previous_status,
            'current_status' => $this->current_status,
            'note' => $this->note,
            'reason' => $this->reason,
            "file" => $fileKey ? route("download", ["key" => $fileKey]) : '',
            'user_name' => $this->user->name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

        ];
    }
}
