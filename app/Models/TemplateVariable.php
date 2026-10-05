<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TemplateVariable extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function MessageTemplate()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id', 'id');
    }
}
