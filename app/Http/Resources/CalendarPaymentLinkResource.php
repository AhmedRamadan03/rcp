<?php

namespace App\Http\Resources;

use App\Enums\LinkSendStatus;
use App\Enums\PaymentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarPaymentLinkResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $color = '';
        $text_color = '';

        if ($this->type == PaymentType::INSTALLMENT_PAYMENT->value && $this->send_status == LinkSendStatus::NOT_SENT->value) {
            $color = 'blue';
            $text_color = 'white';
        } elseif ($this->type == PaymentType::INSTALLMENT_PAYMENT->value && $this->send_status == LinkSendStatus::SENT->value) {
            $color = 'green';
            $text_color = 'white';
        } elseif ($this->type == PaymentType::DELAY_PAYMENT->value && $this->send_status == LinkSendStatus::NOT_SENT->value) {
            $color = 'gray';
            $text_color = 'white';
        } elseif ($this->type == PaymentType::DELAY_PAYMENT->value && $this->send_status == LinkSendStatus::SENT->value) {
            $color = 'orange';
            $text_color = 'white';
        } elseif ($this->type == PaymentType::FULL_PAYMENT->value) {
            $color = '#b32c20ff';
            $text_color = 'white';
        } elseif ($this->send_status == LinkSendStatus::PAID->value) {
            $color = 'black';
            $text_color = 'white';
         } 
        //else {
        //     return [];
        // }

        return [
            'id' => $this->id,
            'status' => $this->status,
            'send_status' => $this->send_status,
            'type' => $this->type,
            'amount' => $this->amount,
            'link' => $this->link ? url('/api/bit.ly/' . $this->link) : null,
            'views_count' => $this->views_count,
            'last_access' => $this->last_access,
            'due_date' => $this->due_date,
            'claim_id' => $this->payment?->claim?->id,
            'claim_number' => $this->payment?->claim?->claim_number,
            'color' => $color,
            'text_color' => $text_color,
        ];
    }
}
