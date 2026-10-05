<?php

namespace App\Services;

use App\Enums\LinkSendStatus;
use App\Enums\Roles;
use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TransactionService
{
    public function list($filters = [], $paginate = 10)
    {
        return $this->buildQuery($filters)->paginate($paginate);
    }

    public function export(array $filters = [], ?int $userId = null): Builder
    {
        return $this->buildQuery($filters, $userId);
    }


    protected function buildQuery(array $filters = [], ?int $userId = null): Builder
    {
        $userId = $userId ?? auth()->id();
        $user = User::findOrFail($userId);

        return PaymentTransaction::query()
            ->with('link.payment.claim.assignees')
            ->when(
                $user->cannot('viewAll', PaymentTransaction::class),
                fn(Builder $query) => $this->applyRoleScope($query, $user)
            )

            // ids filter 
            ->when(isset($filters['ids']), function (Builder $query) use ($filters) {
                $query->whereIn('id', $filters['ids']);
            })

            // claim_id 

            ->when(isset($filters['claim_id']), function (Builder $query) use ($filters) {
                $query->whereHas('link.payment', function (Builder $q) use ($filters) {
                    $q->where('claim_id', $filters['claim_id']);
                });
            })

            ->when(isset($filters['search']), function (Builder $query) use ($filters) {
                $search = $filters['search'];

                $query->where(function (Builder $query) use ($search) {
                    $query->where('amount', 'ILIKE', "%{$search}%")
                        ->orWhereHas('link', function (Builder $q) use ($search) {
                            $q->where('order', 'ILIKE', "%{$search}%");
                        })
                        ->orWhereHas('link.payment.claim', function (Builder $q) use ($search) {
                            $q->where('claim_number', 'ILIKE', "%{$search}%")
                                ->orWhere('id', 'ILIKE', "%{$search}%");
                        })
                        ->orWhereHas('transactionCollector', function (Builder $q) use ($search) {
                            $q->where('name', 'ILIKE', "%{$search}%");
                        })
                        ->orWhere(function (Builder $q) use ($search) {
                            $searchTerm = "%{$search}%";

                            // Payment ID
                            $q->orWhereRaw("response->'transaction'->'gateway_response'->>'fort_id' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->'transaction'->'response'->>'PaymentId' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->>'PaymentId' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->>'fort_id' ILIKE ?", [$searchTerm])

                                // Masked PAN
                                ->orWhereRaw("response->'transaction'->'gateway_response'->>'card_number' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->'transaction'->'response'->>'maskedPAN' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->>'maskedPAN' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->>'card_number' ILIKE ?", [$searchTerm])

                                // Card Brand
                                ->orWhereRaw("response->'transaction'->'gateway_response'->>'payment_option' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->'transaction'->'response'->>'cardBrand' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->>'cardBrand' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->>'payment_option' ILIKE ?", [$searchTerm])

                                // RRN
                                ->orWhereRaw("response->'transaction'->'gateway_response'->>'merchant_reference' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->'transaction'->'response'->>'RRN' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->>'RRN' ILIKE ?", [$searchTerm])
                                ->orWhereRaw("response->>'merchant_reference' ILIKE ?", [$searchTerm]);
                        });
                });
            }, function (Builder $query) use ($filters) {
                $query
                    ->when(isset($filters['amount']), function (Builder $query) use ($filters) {
                        $query->where('amount', 'ILIKE', "%{$filters['amount']}%");
                    })
                    ->when(isset($filters['claim_number']), function (Builder $query) use ($filters) {
                        $query->whereHas('link.payment.claim', function (Builder $q) use ($filters) {
                            $q->where('claim_number', 'ILIKE', "%{$filters['claim_number']}%");
                        });
                    })
                    ->when(isset($filters['collector_name']), function (Builder $query) use ($filters) {
                        $query->whereHas('transactionCollector', function (Builder $q) use ($filters) {
                            $q->where('name', 'ILIKE', "%{$filters['collector_name']}%");
                        });
                    })
                    ->when(isset($filters['payment_id']), function (Builder $query) use ($filters) {
                        $paymentId = "%{$filters['payment_id']}%";
                        $query->where(function (Builder $q) use ($paymentId) {
                            $q->whereRaw("response->'transaction'->'gateway_response'->>'fort_id' ILIKE ?", [$paymentId])
                                ->orWhereRaw("response->'transaction'->'response'->>'PaymentId' ILIKE ?", [$paymentId])
                                ->orWhereRaw("response->'transaction'->>'charge_id' ILIKE ?", [$paymentId])
                                ->orWhereRaw("response->>'PaymentId' ILIKE ?", [$paymentId])
                                ->orWhereRaw("response->>'fort_id' ILIKE ?", [$paymentId]);
                        });
                    })
                    ->when(isset($filters['card_brand']), function (Builder $query) use ($filters) {
                        $cardBrand = "%{$filters['card_brand']}%";
                        $query->where(function (Builder $q) use ($cardBrand) {
                            $q->whereRaw("response->'transaction'->'gateway_response'->>'payment_option' ILIKE ?", [$cardBrand])
                                ->orWhereRaw("response->'transaction'->'response'->>'cardBrand' ILIKE ?", [$cardBrand])
                                ->orWhereRaw("response->>'cardBrand' ILIKE ?", [$cardBrand])
                                ->orWhereRaw("response->>'payment_option' ILIKE ?", [$cardBrand]);
                        });
                    })
                    ->when(isset($filters['masked_pan']), function (Builder $query) use ($filters) {
                        $maskedPan = "%{$filters['masked_pan']}%";
                        $query->where(function (Builder $q) use ($maskedPan) {
                            $q->whereRaw("response->'transaction'->'gateway_response'->>'card_number' ILIKE ?", [$maskedPan])
                                ->orWhereRaw("response->'transaction'->'response'->>'maskedPAN' ILIKE ?", [$maskedPan])
                                ->orWhereRaw("response->>'maskedPAN' ILIKE ?", [$maskedPan])
                                ->orWhereRaw("response->>'card_number' ILIKE ?", [$maskedPan]);
                        });
                    })
                    ->when(isset($filters['rrn']), function (Builder $query) use ($filters) {
                        $rrn = "%{$filters['rrn']}%";
                        $query->where(function (Builder $q) use ($rrn) {
                            $q->whereRaw("response->'transaction'->'gateway_response'->>'merchant_reference' ILIKE ?", [$rrn])
                                ->orWhereRaw("response->'transaction'->'response'->>'RRN' ILIKE ?", [$rrn])
                                ->orWhereRaw("response->>'RRN' ILIKE ?", [$rrn])
                                ->orWhereRaw("response->>'merchant_reference' ILIKE ?", [$rrn]);
                        });
                    });
            })
            ->when(isset($filters['installment_number']), function (Builder $query) use ($filters) {
                $query->whereHas('link', function (Builder $q) use ($filters) {
                    $q->where('order', $filters['installment_number']);
                });
            })
            ->when(isset($filters['status']), function (Builder $query) use ($filters) {
                $query->whereIn('send_status', $filters['status']);
            })
            ->when(isset($filters['payment_type']), function (Builder $query) use ($filters) {
                $query->whereIn('payment_type', $filters['payment_type']);
            })
            ->when(isset($filters['created_at_from']), function (Builder $query) use ($filters) {
                $query->whereDate('created_at', '>=', $filters['created_at_from']);
            })
            ->when(isset($filters['created_at_to']), function (Builder $query) use ($filters) {
                $query->whereDate('created_at', '<=', $filters['created_at_to']);
            })
            ->orderBy('created_at', 'desc');
    }

    protected function applyRoleScope(Builder $query, User $user): Builder
    {
        return $query->whereHas('link.payment.claim.assignees', function (Builder $q) use ($user) {
            $q->where('is_active', 1)
                ->where(function (Builder $q) use ($user) {
                    $q->where('to', $user->id)
                        //if user is supervisor on the to_user 
                        ->orWhereHas('toUser', fn(Builder $qu) => $qu->where('supervisor_id', $user->id));
                });
        });
    }

}