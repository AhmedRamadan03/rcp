<?php

namespace App\Models;

use App\Http\Resources\Api\V1\FileResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClaimFile extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }


    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function toArray()
    {
        return [
            "id" => $this->id,
            "claim_id" => $this->claim_id,
            "file" => FileResource::from($this->file, $this->url)?->toArray(request()),
            "title" => $this->title,
            "access" => $this->access,
        ];
    }
}
