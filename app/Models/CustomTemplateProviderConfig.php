<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomTemplateProviderConfig extends Model
{
    use HasFactory;
    protected $guarded = [];

    public function customTemplate()
    {
        return $this->belongsTo(CustomTemplate::class, 'custom_template_id', 'id');
    }

    public function templateVariable()
    {
        return $this->belongsTo(TemplateVariable::class, 'template_variable_id', 'id');
    }
}
