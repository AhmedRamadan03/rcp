<?php

namespace App\Models;

use App\Enums\PaymentTransactionStatus;
use App\Helpers\PaymentTransactionResponseParser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function link()
    {
        return $this->belongsTo(PaymentLink::class, 'link_id', 'id');
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'payment_id', 'id');
    }

    public function claim()
    {
        return $this->hasOneThrough(
            Claim::class,
            Payment::class,
            'id',          // FK on payments table (payments.id)
            'id',          // FK on claims table (claims.id)
            'payment_id',  // local key on payment_transactions (payment_transactions.payment_id)
            'claim_id'     // local key on payments (payments.claim_id)
        );
    }

    public function collector()
    {
        return $this->claim?->assignees()->where('is_active', 1);
    }

    public function transactionCollector()
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public static function initiatePayment($paymentLink, $transaction_id, $amount, $collector_id, $payment_type)
    {
        return self::create([
            'link_id' => $paymentLink->id,
            'payment_id' => $paymentLink->payment_id,
            'amount' => $amount,
            'send_status' => PaymentTransactionStatus::CREATED->value,
            'transaction_ref' => $transaction_id,
            'collector_id' => $collector_id,
            'payment_type' => $payment_type,
        ]);
    }

    public function saveRequest(string $data)
    {
        $this->request = $data;
        $this->save();
    }

    public function getResponseAttribute($value)
    {
        if ($value === null || $value === '[]') {
            return null;
        }

        return $value;
    }


    public function scopeFilter($query, $filters)
    {

        if (isset($filters['status'])) {
            $query->whereIn('send_status', Arr::wrap($filters['status']));
        }

        if (isset($filters['payment_type'])) {
            $query->whereIn('payment_type', Arr::wrap($filters['payment_type']));
        }
        if (isset($filters['ids'])) {
            $query->whereIn('id', Arr::wrap($filters['ids']));
        }

        if (isset($filters['date_from']) && isset($filters['date_to'])) {
            $query->whereBetween('created_at',[$filters['date_from'], $filters['date_to']]);
        }


       if (isset($filters['masked_pan'])) {
            $maskedPan = '%' . $filters['masked_pan'] . '%';

            $query->where(function ($q) use ($maskedPan) {
                $q->whereRaw("response->>'maskedPAN' ILIKE ?", [$maskedPan])
                ->orWhereRaw("response->>'card_number' ILIKE ?", [$maskedPan])
                ->orWhereRaw("response->'transaction'->'response'->>'maskedPAN' ILIKE ?", [$maskedPan])
                ->orWhereRaw("response->'transaction'->'gateway_response'->>'card_number' ILIKE ?", [$maskedPan]);
            });
        }
       if (isset($filters['payment_id'])) {
            $paymentId = '%' . $filters['payment_id'] . '%';

            $query->where(function ($q) use ($paymentId) {
                $q->whereRaw("response->>'PaymentId' ILIKE ?", [$paymentId])
                ->orWhereRaw("response->>'fort_id' ILIKE ?", [$paymentId])
                ->orWhereRaw("response->'transaction'->'response'->>'PaymentId' ILIKE ?", [$paymentId])
                ->orWhereRaw("response->'transaction'->'gateway_response'->>'fort_id' ILIKE ?", [$paymentId]);
            });
        }
       if (isset($filters['card_brand'])) {
            $cardBrand = '%' . $filters['card_brand'] . '%';

            $query->where(function ($q) use ($cardBrand) {
                $q->whereRaw("response->>'cardBrand' ILIKE ?", [$cardBrand])
                ->orWhereRaw("response->>'payment_option' ILIKE ?", [$cardBrand])
                ->orWhereRaw("response->'transaction'->'response'->>'cardBrand' ILIKE ?", [$cardBrand])
                ->orWhereRaw("response->'transaction'->'gateway_response'->>'payment_option' ILIKE ?", [$cardBrand]);
            });
        }
        if (isset($filters['rrn'])) {
            $rrn = '%' . $filters['rrn'] . '%';

            $query->where(function ($q) use ($rrn) {
                $q->whereRaw("response->>'RRN' ILIKE ?", [$rrn])
                ->orWhereRaw("response->>'merchant_reference' ILIKE ?", [$rrn])
                ->orWhereRaw("response->'transaction'->'response'->>'RRN' ILIKE ?", [$rrn])
                ->orWhereRaw("response->'transaction'->'gateway_response'->>'merchant_reference' ILIKE ?", [$rrn]);
            });
        }

        if (isset($filters['search'])) {
            $search = '%' . $filters['search'] . '%';

            $query->where(function ($q) use ($search) {

                // Payment ID
                $q->orWhereRaw("response->>'PaymentId' ILIKE ?", [$search])
                ->orWhereRaw("response->>'fort_id' ILIKE ?", [$search])
                ->orWhereRaw("response->'transaction'->'response'->>'PaymentId' ILIKE ?", [$search])
                ->orWhereRaw("response->'transaction'->'gateway_response'->>'fort_id' ILIKE ?", [$search])

                // Masked PAN
                ->orWhereRaw("response->>'maskedPAN' ILIKE ?", [$search])
                ->orWhereRaw("response->>'card_number' ILIKE ?", [$search])
                ->orWhereRaw("response->'transaction'->'response'->>'maskedPAN' ILIKE ?", [$search])
                ->orWhereRaw("response->'transaction'->'gateway_response'->>'card_number' ILIKE ?", [$search])

                // Card Brand
                ->orWhereRaw("response->>'cardBrand' ILIKE ?", [$search])
                ->orWhereRaw("response->>'payment_option' ILIKE ?", [$search])
                ->orWhereRaw("response->'transaction'->'response'->>'cardBrand' ILIKE ?", [$search])
                ->orWhereRaw("response->'transaction'->'gateway_response'->>'payment_option' ILIKE ?", [$search])

                // RRN
                ->orWhereRaw("response->>'RRN' ILIKE ?", [$search])
                ->orWhereRaw("response->>'merchant_reference' ILIKE ?", [$search])
                ->orWhereRaw("response->'transaction'->'response'->>'RRN' ILIKE ?", [$search])
                ->orWhereRaw("response->'transaction'->'gateway_response'->>'merchant_reference' ILIKE ?", [$search]);
               
            });
        }
        
    }

    public function toArray()
    {
        return [
            "id" => $this->id,
            "payment_link_id" => $this->link->id,
            "claim_number" => $this->claim?->claim_number,
            "type" => $this->payment_type,
            "installment_number" => $this->link?->order,
            "response" => [
                "PaymentId"=>  PaymentTransactionResponseParser::paymentId($this),
                "cardBrand"=>   PaymentTransactionResponseParser::cardBrand($this),
                "maskedPAN"=>   PaymentTransactionResponseParser::maskedPan($this),
                "RRN"=>   PaymentTransactionResponseParser::RRN($this),
            ],
            'collector' => [
                'id' => $this->collector_id ?? null,
                'name' => $this->transactionCollector?->name ?? null
            ],
            "claim_id" => $this->claim->id,
            "amount" => $this->amount,
            "status" => $this->send_status,
            "payment_type" => $this->payment_type,
            "created_at" => $this->created_at,
            "updated_at" => $this->updated_at,


        ];
    }
}
