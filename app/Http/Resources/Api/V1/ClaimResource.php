<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\DebtorType;
use App\Enums\FileVisibility;
use App\Enums\ReturnReason;
use Illuminate\Http\Resources\Json\JsonResource;
class ClaimResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            "risk_score" => $this->risk_score ?? 0,
            "risk_level" => $this->risk_level ?? 'low',
            "risk_color" => $this->risk_color ?? 'green',
            "risk_label" => $this->risk_label ?? 'مخاطر منخفضة',

            'reference' => $this->reference,
            'external_reference' => $this->external_reference,
            'client_id' => $this->client_id,
            'user_id' => $this->user_id,
            "created_by" => $this->user?->name,
            'status' => $this->status,
            'is_msg_skip' => $this->is_msg_skip,
            'invoice_status' => $this->invoice_status,
            'invoice_date' => $this->invoice_date,
            'insertion_type' => $this->insertion_type,
            'amount' => $this->amount,
            'type' => $this->type,
            'city_id' => $this->city_id,
            'file_id' => $this->file_id,
            'debtor_type' => $this->debtor_type,
            "return_reason_id" => ($this->debtor_type === DebtorType::INSURED->value) ? $this->return_reason : null,
            "return_reason_description" => ($this->debtor_type === DebtorType::INSURED->value) ?  ReturnReason::getString($this->return_reason) : null ,
            'policy_number' => $this->policy_number,
            'is_transformed_to_lawyer' => $this->is_transformed_to_lawyer,
            'is_transformed_to_naviz' => $this->is_transformed_to_naviz,
            'client_requests_enabled' => $this->client_requests_enabled,
            'claim_number' => $this->claim_number,
            'debtor_name' => $this->debtor_name,
            'debtor_nid' => $this->debtor_nid,
            'debtor_phone' => $this->debtor_phone,
            'accident_date' => $this->accident_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'remark' => $this->remark,
            'parties' => $this->parties,
            'is_close' => $this->is_close,
            'intimation_date' => $this->intimation_date,
            'sub_claim_number' => $this->sub_claim_number,
            'car_type' => $this->car_type,
            'plate_number' => $this->plate_number,
            'victim_plate_number' => $this->victim_plate_number,
            'proof_debt_enable' => $this->proof_debt_enable,
            'mistake_percentage' => $this->mistake_percentage,
            'policy_holder' => $this->policy_holder,
            'date_of_loss' => $this->date_of_loss,
            'accident_number' => $this->accident_number,
            'recovered_amount' => $this->recovered_amount,
            'is_draft' => $this->is_draft,
            'draft_message' => $this->draft_message,
            'city' => $this->city,
            'assignee' => AssigningResource::make($this->assignee),
            'files' => ClaimFileResource::collection($this->claimFiles),
            'payments' => PaymentResource::collection($this->payments),
            'payment_reminders_is_disabled' => $this->payment_reminders_is_disabled,
            'files_count' => $this->claimFiles->count(),
            'additional_files_count' => $this->additionalFiles->where('attachment_status', FileVisibility::PUBLISHED->value)->count(),
            'client_requests_count' => $this->rejections->count(),
            'permission_requests_count' => $this->permissionRequests()->where(function ($query) {
                $query->where('user_id', auth()->id())
                    ->orWhere('supervisor_id', auth()->id())
                     ->orWhereHas('approvals', function ($query) {
                        $query->where('user_id', auth()->id());
            });;
            })->count(),

        ];
    }
}