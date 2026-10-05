<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaqsamCallback extends Model
{
    use HasFactory;

    protected $table = 'maqsam_callbacks';

    // Fields that are mass assignable
    protected $fillable = [
        'InquiryId',
        'Event',
        'FromState',
        'ToState',
        'Trials',
        'UnreachableReason',
        'Length',
        'Result',
        'Entries',
        'RecordingUrl',
        'payment_link_id',
        'type',
    ];
}
