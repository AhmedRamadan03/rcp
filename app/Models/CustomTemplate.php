<?php

namespace App\Models;

use App\Enums\CustomTemplateStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    public function MessageTemplate()
    {
        return $this->belongsTo(MessageTemplate::class, 'template_id', 'id');
    }


    public function thirdParty()
    {
        return $this->belongsTo(ThirdParty::class, 'third_party_id', 'id');
    }

    public function providerConfigs()
    {
        return $this->hasMany(CustomTemplateProviderConfig::class, 'custom_template_id', 'id');
    }

    public function scopeWithVariables(Builder $query): Builder
    {
        return $query->with(['messageTemplate.variables']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', CustomTemplateStatus::ACTIVE->value);
    }

    public function scopeWithProviderDetails(Builder $query): Builder
    {
        return $query->with(['thirdParty', 'providerConfigs.templateVariable']);
    }
}
