<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Enums\PermissionRequestsStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StatusChangeRequest extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function claim()
    {
        return $this->belongsTo(Claim::class, 'claim_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withTrashed();
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id', 'id')->withTrashed();
    }

    public function file()
    {
        return $this->belongsTo(AdditionalFile::class, 'additional_file_id', 'id');
    }

    public function changeAmount()
    {
        return $this->hasOne(ChangeClaimAmountRequest::class, 'request_id', 'id');
    }

    public function law()
    {
        return $this->hasOne(LawDepartmentClaim::class, 'request_id', 'id');
    }

    public function approvals()
    {
        return $this->hasMany(RequestApproval::class, 'request_id', 'id');
    }
    public function paymentRequest()
    {
        return $this->hasMany(PaymentRequest::class, 'request_id', 'id');
    }

    public function discountRequest()
    {
        return $this->hasOne(Discount::class, 'request_id', 'id')->latest();
    }

    public function approvalUserIds()
    {
        return $this->approvals()->pluck('user_id')->toArray();
    }

    public function toArray($context = 'index')
    {
        $response = [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'request_by' => $this->user?->name,
            'supervisor_id' => $this->supervisor_id,
            'supervisor_name' => $this->supervisor?->name,
            'from_status' => $this->from_status,
            'to_status' => $this->to_status,
            'additional_file' => $this->file,
            'status' => $this->request_status,
            'note' => $this->note,
            'reject_reason' => $this->reject_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];

        if ($this->to_status == ClaimStatus::DISCOUNT->value && $this->claim?->discount) {
            $response['discount_percentage'] = $this->claim?->discount()
                ->where('request_id', $this->id)
                ->latest()
                ->value('discount_percentage');
        }

        if ($context === 'show') {
            $response['claim'] = [
                'id' => $this->claim_id,
                'claim_number' => $this->claim?->claim_number,
                'type' => $this->claim?->type,
                'mistake_percentage' => $this->claim?->mistake_percentage,
                'accident_date' => $this->claim?->accident_date,
                'city' => $this->claim?->city?->name,
                'remark' => $this->claim?->remark,
            ];
            $response['debtor'] = [
                'debtor_name' => $this->claim?->debtor_name,
                'debtor_type' => $this->claim?->debtor_type,
                'debtor_nid' => $this->claim?->debtor_nid,
                'debtor_phone' => $this->claim?->debtor_phone,
            ];

        if ($this->paymentRequest && $this->paymentRequest->isNotEmpty()) { //todo will be refacted 
                if (in_array($this->to_status, [PermissionRequestsStatus::GENERATE_VOUCHER_LETTER->value, PermissionRequestsStatus::SEND_VOUCHER_LETTER->value]) )  {
                    $response['voucher_request'] = $this->paymentRequest->toArray();
                } else { 
                    $response['dispute_payment'] = $this->paymentRequest->toArray();
                }

            }
            //discountRequest
            if ($this->discountRequest) {
                $response['discount_request'] = $this->discountRequest?->only([
                    'total_amount',
                    'discount_percentage',
                    'amount_after_discount',
                    'deducted_amount'
                ]);
            }
            //discountRequest
            if ($this->changeAmount) {
                $response['change_amount_request'] = [
                    'current_amount' => $this->changeAmount->current_amount,
                    'new_amount' => $this->changeAmount->new_amount,
                ];
            }
        } else {
            $response['claim_id'] = $this->claim_id;
            $response['claim_number'] = $this->claim?->claim_number;


        }

        return $response;
    }
}
