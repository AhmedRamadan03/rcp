<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class Ivr extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function paymentLink()
    {
        return $this->belongsTo(PaymentLink::class, 'payment_link_id', 'id');
    }

    public function showRecallButton($ivrs)
    {
        return collect($ivrs)->where('payment_link_id', $this->payment_link_id)->max('id') === $this->id && $this->status !== 'completed';
    }

    public function scopeFilter($query)
    {
        if (request()->status) {
            $query->whereIn('status', Arr::wrap(request()->status));
        }
        if (request()->ids) {
            $query->whereIn('id', Arr::wrap(request()->ids));
        }

        if (request()->call_duration) {
            $callDuration = request()->call_duration;

            $query->whereRaw("
                EXISTS (
                    SELECT 1
                    FROM jsonb_array_elements(response::jsonb -> 'recipients') AS r
                    WHERE r ->> 'duration' ILIKE ?
                )
            ", ['%' . $callDuration . '%']);
        }
        if (request()->claim_number) {
            $query->whereHas('paymentLink.payment.claim', function ($q)  {
                $q->where('claim_number', 'ILIKE', '%' . request()->claim_number . '%');
            });
        }
        if (request()->debtor_phone) {
            $query->whereHas('paymentLink.payment.claim', function ($q)  {
                $q->where('debtor_phone', 'ILIKE', '%' . request()->debtor_phone . '%');
            });
        }
        if (request()->claim_id) {
            $query->whereHas('paymentLink.payment.claim', function ($q)  {
                $q->where('id', request()->claim_id);
            });
        }
        if (request()->call_date_from && request()->call_date_to) {
                $query->whereDate('created_at', [request()->call_date_from,request()->call_date_to]);
            
        }
        if (request()->call_status) {
                $query->where('status', Arr::wrap(request()->call_status));
            
        }

         if (request()->search) {
            $query->where(function($q){
                $q->whereHas('paymentLink.payment.claim', function ($q2)  {
                    $q2->where('claim_number', 'ILIKE', '%' . request()->search . '%')
                    ->orWhere('debtor_phone', 'ILIKE', '%' . request()->search . '%');;
                })
                ->orWhere('response', 'ILIKE', '%' . request()->search . '%');
            
            });
        }
    }



    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'payment_link_id' => $this->payment_link_id,
            'claim_id' => $this->paymentLink?->payment?->claim_id,
            'claim_number' => $this->paymentLink?->payment?->claim?->claim_number,
            'phone_number' => $this->paymentLink?->payment?->claim?->debtor_phone,
            // 'reference' => $this->paymentLink,
            'retry_number' => $this->retry_number,
            'status' => $this->status,
            'response' => json_decode($this->response),
            'type' => $this->type,
            'callId' => $this->callId,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
