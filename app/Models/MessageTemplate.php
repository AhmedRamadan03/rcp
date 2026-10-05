<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MessageTemplate extends Model
{
    use HasFactory,SoftDeletes;

    protected $guarded = [];

    public function variables()
    {
        return $this->hasMany(TemplateVariable::class, 'template_id', 'id');
    }

    public function customTemplates()
    {
        return $this->hasMany(CustomTemplate::class, 'template_id', 'id');
    }
}
