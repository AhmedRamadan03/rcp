<?php

namespace App\Models;

use App\Enums\AdditionalFileType;
use App\Http\Resources\Api\V1\FileResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdditionalFile extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function statusChangeRequest()
    {
        return $this->hasOne(StatusChangeRequest::class, 'additional_file_id', 'id');
    }

    
    public function file()
    {
        return $this->belongsTo(File::class);
    }

   public function getFileTitleWithExtensionAttribute()
{
    if (!$this->url) {
        return null;
    }

    $parts = explode('/', trim($this->url, '/'));

    $fileName = end($parts); // اسم الملف
    $folder   = prev($parts); // الفولدر اللي قبله

    // لو فيه فولدر و مش رقم (زي 17) رجعه معاه
    if ($folder && !is_numeric($folder)) {
        return $folder . '/' . $fileName;
    }

    return $fileName;
}

    public function toArray(): array
    {
        $response =  [
            'id' => $this->id,
            'title' => $this->title,
            'display_file_name' => $this->file_title_with_extension,
            'claim_id' => $this->claim_id,
            'note' => $this->note,
            'file' => FileResource::from($this->file, $this->url)?->toArray(request()),
            'type' => $this->type,
            'attachment_status' => $this->attachment_status,
            'uploaded_by' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null,
            'created_at' => $this->created_at
        ];

        if ($this->type == AdditionalFileType::VOUCHER->value) {
            $setting = Setting::proofOfDebt()->value;
            $response['visibility'] = $setting == 1 && $this->claim?->proof_debt_enable ? 'public' : 'private';
        }
        return $response;

    }
}
