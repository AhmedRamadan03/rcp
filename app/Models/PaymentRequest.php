<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(StatusChangeRequest::class, 'request_id', 'id');
    }

    public function toArray()
    {
        return [
            'link_id'=>$this->link_id,
            'link_amount'=>$this->link_amount,
            'link_date'=>$this->link_date,
        ];
    }
}
