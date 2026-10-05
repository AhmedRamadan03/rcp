<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Enums\DebtorType;
use App\Enums\LinkSendStatus;
use App\Enums\MessageTemplates;
use App\Enums\ReturnReason;
use App\Http\Resources\Api\V1\FileResource;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class PaymentLink extends Model
{
    use  HasApiTokens, HasFactory;

    protected $guarded = [];

    protected $casts = [
        'last_access' => 'datetime:Y-m-d h:i',
    ];

    protected $appends = [
        'is_paid',
        'can_be_paid',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'payment_link_id', 'id');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class, 'payment_link_id', 'id')->latestOfMany();
    }

    public function transactions()
    {
        return $this->hasMany(PaymentTransaction::class, 'link_id', 'id');
    }

    public function vouchers()
    {
        return $this->hasMany(Voucher::class, 'link_id', 'id');
    }

    public function voucher()
    {
        return $this->hasOne(Voucher::class, 'link_id', 'id')->where('is_valid', true)->latestOfMany();
    }

    public function ivrs()
    {
        return $this->hasMany(Ivr::class);
    }

    public function maqsamIvrs()
    {
        return $this->hasMany(MaqsamCallback::class, 'payment_link_id', 'id');
    }

    public function url()
    {
        if (config('app.env') == 'production') {
            return 'https://thr.ggi-sa.com/claim-info/' . $this->link;
        } elseif (config('app.env') == 'testing_ggi') {
            return 'https://test-ggi.taheiya.sa/claim-info/' . $this->link;
        }

        return url('/claim-info/' . $this->link);
    }

    /*
     * is paid attribute
     */
    public function isPaid(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->send_status == LinkSendStatus::PAID->value
        );
    }

    public function canBePaid(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (PaymentLink::where('payment_id', $this->payment_id)->where('send_status', LinkSendStatus::PAID->value)->count() > 0) {
                    return PaymentLink::where('order', $this->order - 1)->where('payment_id', $this->payment_id)->where('send_status', LinkSendStatus::PAID->value)->count() === 1;
                }

                return $this->order === 1;
            }
        );
    }

    /**
     * Get the SMS status from the latest message
     * Returns the status of the most recently sent message for this payment link
     */
    public function smsStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Get the latest message that was created AFTER the payment link was updated
                $latestMessage = $this->messages()
                    ->where('created_at', '>=', $this->updated_at)
                    ->latest()
                    ->first();

                return $latestMessage?->message_status ?? 'not sent';
            }
        );
    }


    public function getFullLinkAttribute()
    {
        return config('app.frontend_url') . '/claim-info/' . $this->link;
    }

    public function toArray($clientView = false)
    {


        $response =  [
            'claim_id' => $this->payment?->claim_id,
            'id' => $this->id,
            'payment_id' => $this->payment_id,
            'payment_type' => $this->type,
            'order' => $this->order,
            'amount' => $this->amount,
            'total_amount' => $this->payment?->amount,
            'link' => $this->link,
            'due_date' => $this->due_date,
            'send_status' => $this->send_status,
            'sms_status' => $this->sms_status,
            'can_be_paid' => $this->can_be_paid,
            'is_canceled' => $this->send_status == LinkSendStatus::CANCELED->value,
            'collector' => [
                'id' => $this->collector_id,
                'name' => $this->collector?->name
            ],

            'vouchers' => $this->vouchers,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at

        ];

        if ($clientView) {
            $claimFiles = $this->payment->claim->claimFiles
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

            $response['debtor_name'] = $this->payment?->claim?->debtor_name;
            $response['debtor_nid'] = $this->payment?->claim?->debtor_nid;
            $response['claim_number'] = $this->payment?->claim?->claim_number;
            $response['accident_date'] = $this->payment?->claim?->accident_date;
            $response['accident_number'] = $this->payment?->claim?->accident_number;
            $response['debtor_type'] = $this->payment?->claim?->debtor_type;
            $response['victim_plate_number'] = $this->payment?->claim?->victim_plate_number;
            $response['return_reason_description'] =  ($this->payment?->claim?->debtor_type === DebtorType::INSURED->value) ?  ReturnReason::getString($this->payment?->claim?->return_reason) : null;

            $response['discharge_letter'] = FileResource::from(
                $this->payment?->claim?->discharge?->file,
                $this->payment?->claim?->discharge?->url
            )?->toArray(request());


            $response['claim_files'] = $claimFiles;
            $response['user'] = [
                'name' => $this->payment?->claim?->assignee?->toUser?->name,
                'phone' => $this->payment?->claim?->assignee?->toUser?->phone,
                'email' => $this->payment?->claim?->assignee?->toUser?->email
            ];


            $setting = Setting::proofOfDebt()->value;
            $response['proof_debt_visibility'] = $setting == 1 && $this->payment?->claim?->proof_debt_enable ? 'public' : 'private';
            $response['client_requests_enabled'] = $this->payment?->claim?->client_requests_enabled;
        } else {
            $response['transactions'] = $this->transactions;
        }

        


        return $response;
    }
}
