<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Enums\DebtorType;
use App\Enums\LinkSendStatus;
use App\Enums\ReturnReason;
use App\Http\Resources\Api\V1\FileResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * @method static find(mixed $payment_id)
 */
class Payment extends Model
{
    use HasApiTokens,HasFactory;

    protected $guarded = [];


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            if (empty($payment->link)) {
                $payment->link = generateUniqueCode(self::class, 'link', 7);
            }
        });
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function links()
    {
        return $this->hasMany(PaymentLink::class,'payment_id','id');
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'payment_id', 'id');
    }


    public function discounts()
    {
        return $this->hasMany(Discount::class);
    }

    public function activeDiscount()
    {
        return $this->hasOne(Discount::class)->where('is_active', 1)->latest();
    }

    public function activePaymentLink()
    {
        return $this->hasOne(PaymentLink::class, 'payment_id', 'id')
            ->whereNotNull('verification_code')
            ->whereNotIn('send_status', [
                LinkSendStatus::CANCELED->value,
                LinkSendStatus::PAID->value,
            ])
            ->latestOfMany();
    }

    public function getActiveDiscountDataAttribute()
    {
        $activeDiscount = $this->discounts()->where('is_active', 1)->first();

        if(!$activeDiscount){
            return null;
        }

        return [
            
            'total_amount' => $activeDiscount->total_amount,
            'amount_after_discount' => $activeDiscount->amount_after_discount,
            'discount_percentage' => $activeDiscount->discount_percentage,
            'deducted_amount' => $activeDiscount->deducted_amount,
        ];

    }

    public function toArray($clientView = false)
    {
        $response = [
            'id' => $this->id,
            'claim_id' => $this->claim_id,
            'amount' => $this->amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->amount - $this->paid_amount,
            'discount'=> $this->active_discount_data,
            'status' => $this->payment_status,
            'has_created_discharge_letter_before' => $this->claim->discharges()->count() > 0,
            
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($clientView) {
            $claimFiles = $this->claim->claimFiles
                ->where('access', 'public')
                ->map(function ($file, $index) {
                    return [
                        // 'index' => $index + 1,
                        'title' => $file->title,
                        'file' => FileResource::from($file->file, $file->url)?->toArray(request()),
                    ];
                })->values();

            if ($claimFiles->isEmpty()) {
                $claimFiles = [];
            }

            $response['debtor_name'] = $this->claim?->debtor_name;
            $response['debtor_nid'] = $this->claim?->debtor_nid;
            $response['claim_number'] = $this->claim?->claim_number;
            $response['accident_date'] = $this->claim?->accident_date;
            $response['accident_number'] = $this->claim?->accident_number;
            $response['debtor_type'] = $this->claim?->debtor_type;
            $response['victim_plate_number'] = $this->claim?->victim_plate_number;
            $response['return_reason_description'] =  ($this->claim?->debtor_type === DebtorType::INSURED->value) ?  ReturnReason::getString($this->claim?->return_reason) : null;

            $response['discharge_letter'] = FileResource::from(
                $this->claim?->discharge?->file,
                $this->claim?->discharge?->url
            )?->toArray(request());


            $response['claim_files'] = $claimFiles;
            $phoneSetting = Setting::hidePhoneNumber()->value;
            $response['user'] = [
                'name' => $this->claim?->assignee?->toUser?->name,
                'email' => $this->claim?->assignee?->toUser?->email
            ];
            if ($phoneSetting == 0) {
               $response['user']['phone'] = $this->claim?->assignee?->toUser?->phone;
            }

            
            $setting = Setting::proofOfDebt()->value;
            $response['proof_debt_visibility'] = $setting == 1 && $this->claim?->proof_debt_enable ? 'public' : 'private';
            $response['client_requests_enabled'] = $this->claim?->client_requests_enabled;
        } else {
            $response['links'] = $this->links;
        }
        if ($this->claim?->status === ClaimStatus::TRANSFER_TO_ELM->value) {
            $response['debtor_phone'] = 'required';
        }


        return $response;
    }
}
