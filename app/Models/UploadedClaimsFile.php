<?php

namespace App\Models;

use App\Enums\UploadedClaimsStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UploadedClaimsFile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'uploaded_claims_status' => UploadedClaimsStatus::class,
        'inserted_date' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function claims()
    {
        return $this->hasMany(Claim::class, 'file_id');
    }

    public function failedClaims()
    {
        return $this->hasMany(FailedImportedClaim::class, 'file_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function originalFile()
    {
        return $this->belongsTo(File::class, 'original_file_id');
    }


    public function failedFile()
    {
        return $this->belongsTo(File::class, 'failed_file_id');
    }

    public function hasFailedClaims(): bool
    {
        return $this->failed_count > 0 && !is_null($this->failed_file_path);
    }
}
