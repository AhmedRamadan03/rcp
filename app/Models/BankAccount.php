<?php

namespace App\Models;

use App\Enums\BankAccountStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;
      protected $guarded = ['id'];


      public function scopeActive($query)
      {
          return $query->where('status', BankAccountStatusEnum::ACTIVE->value);
      }
}
