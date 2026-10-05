<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FailedImportedClaim extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'row_data' => 'array',
        'error_message' => 'array',
    ];

    public function uploadedClaimsFile()
    {
        return $this->belongsTo(UploadedClaimsFile::class, 'file_id');
    }
}
