<?php

namespace App\Models;

use App\Http\Resources\Api\V1\FileResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Voucher extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function link()
    {
        return $this->belongsTo(PaymentLink::class, 'link_id', 'id');
    }

    public function file()
    {
        return $this->belongsTo(File::class);
    }

    public function toArray()
    {
        return [
            'id' => $this->id,
            'file' => FileResource::from($this->file, $this->url)?->toArray(request()),
            'is_valid' => $this->is_valid,
        ];
    }
}
