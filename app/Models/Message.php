<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * @method static findOrFail(int $message_id)
 */
class Message extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'response' => 'array',
        'request' => 'array',
    ];

    use HasFactory;

    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id');
    }

    public function paymentLink()
    {
        return $this->belongsTo(PaymentLink::class, 'payment_link_id');
    }


    protected function messageWithUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                if (preg_match('"\b(http?://\S+)"', $this->body)) {
                    $html_links = preg_replace('"\b(http?://\S+)"', '<a class="underline" target="_blank" href="$1">$1</a>', $this->body);

                    return $html_links;
                }

                $html_links = preg_replace('"\b(https?://\S+)"', '<a class="underline" target="_blank" href="$1">$1</a>', $this->body);

                return $html_links;
            }

        );
    }


    public function scopeFilter($query)
    {
        if (request('search')) {
            $query->where(function ($q) {
                $q->where('phone_number', 'ILIKE', '%' . request('search') . '%')
                    ->orWhere('body', 'ILIKE', '%' . request('search') . '%')
                    ->orWhere('status', 'ILIKE', '%' . request('search') . '%')
                    ->orWhere('status_message', 'ILIKE', '%' . request('search') . '%')
                    ->orWhere('message_status', 'ILIKE', '%' . request('search') . '%');
            });
        }

        if (request('claim_id')) {
            $query->where('claim_id', request('claim_id'));
        }
        if (request('ids')) {
            $query->whereIn('id', request('ids'));
        }
        if (request('message_type')) {
            $query->where('is_elm_message', request('message_type'));
        }
        if (request('debtor_phone')) {
           $query->whereHas('claim', function ($q) {
                $q->where('phone_number', 'ILIKE', '%' . request('debtor_phone') . '%');
            });
        }
        if (request('claim_number')) {
           $query->whereHas('claim', function ($q) {
                $q->where('claim_number', 'ILIKE', '%' . request('claim_number') . '%');
            });
        }
        if (request('national_id')) {
           $query->whereHas('claim', function ($q) {
                $q->where('debtor_nid', 'ILIKE', '%' . request('national_id') . '%');
            });
        }
        if (request('message_body')) {
            $query->where('body', 'ILIKE', '%' . request('message_body') . '%');
        }
        if (request('status')) {
            $query->whereIn('status', Arr::wrap(request('status')) );
        }
        if (request('message_status')) {
            $text = strtolower(request('message_status'));
            if ($text == 'sent') {
                $text = 'sended';
            }elseif($text == 'Failed'){
                $text = 'not sended';
            }
            $query->where('status_message', 'ILIKE', '%' . $text . '%');
        }
        if (request('date_from') && request('date_to')) {
            $query->whereBetween('send_date', [request('date_from'),request('date_to')] );
        }
        if (request('created_at_from') && request('created_at_to')) {
            $query->whereBetween('created_at', [request('created_at_from'),request('created_at_to')] );
        }
    }



    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'body' => $this->messageWithUrl,
            'claim_id' => $this->claim_id,
            'claim_number' => $this->claim?->claim_number,
            'phone_number' => $this->phone_number,
            'send_date' => $this->send_date,
            'status' => $this->status,
            'message_status' => $this->message_status,
            'status_message' => $this->status_message,
            'sent_count' => $this->sent_count,
            'is_elm_message' => $this->is_elm_message,
            'national_id' => $this->national_id ?? null,
            //'request' => safe_json_decode($this->request),
            //'response' => safe_json_decode($this->response),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at

        ];
    }
}
