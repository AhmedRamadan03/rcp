<?php

namespace App\Models;

use App\Enums\ThirdPartyExecutionType;
use App\Enums\ThirdPartyType;
use App\Services\Payment\PaymentMicroservice;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThirdParty extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'values' => 'array',
        'config' => 'array',
    ];


    public const CONFIG_EXECUTION_TYPE = 'execution_type';

    public function client()
    {
        return $this->belongsTo(Client::class);
    }


    public function getExecutionTypeAttribute(): ?string
    {
        return $this->config[self::CONFIG_EXECUTION_TYPE] ?? null;
    }

    public function isApiProvider(): bool
    {
        return $this->execution_type === ThirdPartyExecutionType::API_PROVIDER->value;
    }

    public function scopeGetActiveThirdParty(Builder $query, $type): Builder
    {
        return $query->where('type', $type)
            ->where('is_active', true);
    }

    /**
     * Verify local record is in sync with the microservice.
     * Uses payment_account_label (UUID) stored in values as the reconciliation key.
     *
     * @return array{is_synced: bool, reason: string}
     */
    public function verifyWithMicroservice(): array
    {
        $paymentAccountId = $this->values['payment_account_id'] ?? null;
        $paymentAccountLabel = $this->values['payment_account_label'] ?? null;

        if (!$paymentAccountId || !$paymentAccountLabel) {
            return [
                'is_synced' => false,
                'reason' => 'Missing payment account data in values.',
            ];
        }

        $remoteAccount = PaymentMicroservice::getPaymentAccountById($paymentAccountId);

        if (!$remoteAccount) {
            return [
                'is_synced' => false,
                'reason' => "Account ID [{$paymentAccountId}] not found in microservice.",
            ];
        }

        // UUID label is the single source of truth
        if (($remoteAccount['label'] ?? null) !== $paymentAccountLabel) {
            return [
                'is_synced' => false,
                'reason' => 'UUID label mismatch. Do not process payments until resolved.',
            ];
        }

        return [
            'is_synced' => true,
            'reason' => 'In sync.',
        ];
    }

}
