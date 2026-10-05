<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use App\Enums\DebtorType;
use App\Enums\LinkSendStatus;
use App\Enums\PaymentType;
use App\Enums\ReturnReason;
use App\Models\ClaimRiskHistory;
use App\Models\Concerns\HasImmutableClaimReference;
use App\Enums\Roles;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Claim extends Model
{
    use HasFactory, SoftDeletes;
    use HasImmutableClaimReference;

    protected $guarded = ['id'];

    protected $with = ['city', 'client'];

    protected $casts = [
        'created_at' => 'datetime',
        'risk_score' => 'decimal:2',
        'risk_factors' => 'array',
        'risk_calculated_at' => 'datetime',
        'accident_date' => 'date',
        'date_of_loss' => 'date',
    ];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        $roleId = (int) $user->role_id;
        $claimsData = safe_json_decode($user->permission()->first()?->claims);

        $allShow = custom_policy($user, ($claimsData->show_all_claims ?? 0) == 1);

        if ($allShow) {
            return $query;
        }
        
        $subordinateIds = $user->subordinates()->pluck('id')->toArray();
        $assigneeIds = !empty($subordinateIds)
            ? [...$subordinateIds, $user->id]
            : [$user->id];
        $now = now();

        return $query->where(function (Builder $visibilityQuery) use ($assigneeIds, $now, $user) {
            $visibilityQuery->whereHas('assignees', function (Builder $assigneeQuery) use ($assigneeIds) {
                $assigneeQuery->whereIn('to', $assigneeIds)
                    ->where('is_active', 1);
            })
                ->orWhereHas('assignees', function (Builder $assigneeQuery) use ($assigneeIds, $now) {
                    $assigneeQuery->where('is_active', 1)
                        ->whereHas('toUser.delegationsFrom', function (Builder $delegationQuery) use ($assigneeIds, $now) {
                            $delegationQuery->whereIn('to', $assigneeIds)
                                ->where('is_active', 1)
                                ->where('start_date', '<=', $now)
                                ->where(function (Builder $endDateQuery) use ($now) {
                                    $endDateQuery->whereNull('end_date')
                                        ->orWhere('end_date', '>=', $now);
                                });
                        });
                })
                ->orWhere(function (Builder $createdByQuery) use ($user) {
                    $createdByQuery->where('user_id', $user->id)
                        ->whereDoesntHave('assignees', function (Builder $assigneeQuery) {
                            $assigneeQuery->where('is_active', 1);
                        });
                });
        });
    }

    public function messages()
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function calls()
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function claimFiles()
    {
        return $this->hasMany(ClaimFile::class)->latest();
    }

    public function additionalFiles()
    {
        return $this->hasMany(AdditionalFile::class)->latest();
    }

    public function upload_file()
    {
        return $this->belongsTo(UploadedClaimsFile::class);
    }

    public function comments()
    {
        return $this->hasMany(ClaimComment::class, 'claim_id', 'id')->latest();
    }

    public function city()
    {
        return $this->belongsTo(City::class)->latest();
    }

    public function assignees()
    {
        return $this->hasMany(Assigning::class)->latest();
    }

    public function delegatedUsers()
    {
        return $this->hasMany(Delegation::class);
    }


    public function rejections()
    {
        return $this->hasMany(RejectedClaim::class);
    }

    public function assignee()
    {
        return $this->hasOne(Assigning::class)->where('is_active', 1)->latest();
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function payment()
    {
        return $this->belongsTo(Payment::class, 'id', 'claim_id')->latest();
    }

    public function discharges()
    {
        return $this->hasMany(Discharge::class, 'claim_id');
    }

    public function discharge()
    {
        return $this->hasOne(Discharge::class)->where('is_valid', true)->latestOfMany();
    }

    public function close()
    {
        return $this->hasMany(CloseClaim::class, 'claim_id', 'id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id')->withTrashed();
    }

    protected function isReadOnly(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->status === ClaimStatus::CLOSED->value || $this->status === ClaimStatus::DONE->value || $this->status === ClaimStatus::REJECTED->value || $this->status === ClaimStatus::MANUAL_COLLECTED->value
        );
    }

    protected function isCollected(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->status === ClaimStatus::DONE->value || $this->status === ClaimStatus::MANUAL_COLLECTED->value || $this->status === ClaimStatus::BANK_COLLECTED->value || $this->status === ClaimStatus::CREDITED->value
        );
    }

    protected function showFollowUp(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->status === ClaimStatus::PARTIAL->value || $this->status === ClaimStatus::DEFAULT_IN_PAYMENT->value || $this->status === ClaimStatus::DELAY->value || $this->status === ClaimStatus::DONE->value || $this->status === ClaimStatus::MANUAL_COLLECTED->value
        );
    }

    public function statusHistory()
    {
        return $this->hasMany(ClaimStatusHistory::class);
    }

    public function discount()
    {
        return $this->hasOne(Discount::class, 'claim_id', 'id');
    }

    public function activeDiscount()
    {
        return $this->hasOne(Discount::class, 'claim_id', 'id')->where('is_active', 1);
    }



    public function hasActiveDiscount()
    {
        return $this->activeDiscount()->exists();
    }

    public function pendingActions()
    {
        return $this->hasMany(PendingAction::class, 'claim_id', 'id');
    }

    public function attempts()
    {
        return $this->hasMany(LoginAttempt::class, 'claim_id', 'id');
    }

    public function permissionRequests()
    {
        return $this->hasMany(StatusChangeRequest::class, 'claim_id', 'id');
    }

    protected function hasOnePaymentAtLeast(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payments()->first()?->paid_amount > 0
        );
    }

    protected function paymentRemindersIsDisabled(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payments()->first()?->links()->where('is_enabled', 0)->count() > 0
        );
    }

    protected function installmentCounts(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payments()->first()?->links()->whereIn('send_status', [LinkSendStatus::NOT_SENT->value])->count()
        );
    }

    protected function installmentFirstDate(): Attribute
    {
        return Attribute::make(
            get: function () {
                $due_date = $this->payments()->first()?->links()->whereIn('send_status', [LinkSendStatus::NOT_SENT->value])->orderBy('due_date')->first()?->due_date;

                return Carbon::parse($due_date)->format('Y-m-d');
            }
        );
    }

    protected function firstLink(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->payments()->first()?->links()->whereNotIn('send_status', [LinkSendStatus::PAID->value, LinkSendStatus::CANCELED->value])->orderBy('due_date')->first();
            }
        );
    }
    public function riskHistories()
    {
        return $this->hasMany(ClaimRiskHistory::class)->latest();
    }

    protected function riskLevel(): Attribute
    {
        return Attribute::make(
            get: fn () => match(true) {
                ($this->risk_score ?? 0) >= 70 => 'high',
                ($this->risk_score ?? 0) >= 30 => 'medium',
                default => 'low',
            }
        );
    }

    protected function riskColor(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->risk_level) {
                'high' => 'red',
                'medium' => 'orange',
                'low' => 'green',
            }
        );
    }

    protected function riskLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match($this->risk_level) {
                'high' => 'مخاطر عالية - مراجعة إجبارية',
                'medium' => 'مخاطر متوسطة - مراجعة مستحبة',
                'low' => 'مخاطر منخفضة - معالجة عادية',
            }
        );
    }

    public function scopeHighRisk($query)
    {
        return $query->where('risk_score', '>=', 70);
    }

    public function scopeMediumRisk($query)
    {
        return $query->whereBetween('risk_score', [30, 69.99]);
    }

    public function scopeLowRisk($query)
    {
        return $query->where('risk_score', '<', 30);
    }

    protected function firstLinkWithoutStatus(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->payments()->first()?->links()->where('type', PaymentType::FULL_PAYMENT->value)->first();
            }
        );
    }

    public function toArray()
    {

        return [
            "id" => $this->id,
            "claim_number" => $this->claim_number,
            "created_by" => $this->user?->name,
            "status" => $this->status,
            "is_msg_skip" => $this->is_msg_skip,
            "insertion_type" => $this->insertion_type,
            "amount" => $this->amount,
            "type" => $this->type,
            "city_id" => $this->city_id,
            "city_name" => $this->city?->name,
            "accident_number" => $this->accident_number,
            "accident_date" => $this->accident_date,
            "debtor_name" => $this->debtor_name,
            "debtor_nid" => $this->debtor_nid,
            "debtor_phone" => $this->debtor_phone,
            "debtor_type" => $this->debtor_type,
            "return_reason_id" => ($this->debtor_type === DebtorType::INSURED->value) ? $this->return_reason : null,
            "return_reason_description" => ($this->debtor_type === DebtorType::INSURED->value) ?  ReturnReason::getString($this->return_reason) : null ,
            "remark" => $this->remark,
            "is_close" => $this->is_close,
            "is_collected" => $this->is_collected,
            "intimation_date" => $this->intimation_date,
            "insertion_date" => $this->created_at,
            "mistake_percentage" => $this->mistake_percentage,
            "plate_number" => $this->plate_number,
            "car_type" => $this->car_type,
            "policy_number" => $this->policy_number,
            "sub_claim_number" => $this->sub_claim_number,
            "risk_score" => $this->risk_score ?? 0,
            "risk_level" => $this->risk_level ?? 'low',
            "risk_color" => $this->risk_color ?? 'green',
            "risk_label" => $this->risk_label ?? 'مخاطر منخفضة',


        ];
    }
}