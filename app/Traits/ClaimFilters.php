<?php

namespace App\Traits;

use App\Models\Escalation;
use App\Models\RejectedClaim;
use App\Models\StatusChangeRequest;
use App\Models\PaymentLink;
use App\Models\ClaimComment;
use App\Enums\EscalationType;
use App\Enums\ClaimStatus;
use App\Enums\InvoiceStatus;
use App\Enums\UserType;
use App\Models\Claim;
use App\Models\User;
use Illuminate\Support\Arr;

trait ClaimFilters
{
    /**
     * Return Claim::with('assignee.toUser')
     */
    private function baseQuery()
    {
        return Claim::with('assignee.toUser');
    }

    /**
     * Applies all filters to a query.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function applyAllFilters($query, $filters)
    {
        $this->applyEscalation($query, $filters);
        $this->applyReminder($query, $filters);
        $this->applyStatus($query, $filters);
        $this->applyBasicFilters($query, $filters);
        $this->applySearch($query, $filters);

        return $query;
    }

    public function applyAccountFilters($query, $filters, $export = false)
    {
        // if ($export) {
        //     $this->applyAccountQuery($query, $filters, true);
        // } else {
            $this->applyAccountQuery($query, $filters);
        // }
        $this->applyAccountingSearch($query, $filters);
        return $query->orderByDesc('id');
    }

    /**
     * Applies escalation filter to a query.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyEscalation($query, $filters)
    {
        $query->when(!empty($filters['escalation_type']), function ($query) use ($filters) {

            $query->whereIn('id', function ($sub) use ($filters) {

                $sub->from('escalations');

                match ($filters['escalation_type']) {
                    'claim_reject' => $sub
                        ->select('rejected_claims.claim_id')
                        ->join('rejected_claims', 'rejected_claims.id', '=', 'escalations.model_id')
                        ->where('escalations.model_type', RejectedClaim::class)
                        ->where('escalations.type', EscalationType::CLAIM_REJECT->value),

                    'status_request' => $sub
                        ->select('status_change_requests.claim_id')
                        ->join('status_change_requests', 'status_change_requests.id', '=', 'escalations.model_id')
                        ->where('escalations.model_type', StatusChangeRequest::class)
                        ->where('escalations.type', EscalationType::STATUS_REQUEST->value),

                    default => $sub
                        ->select('payments.claim_id')
                        ->join('payment_links', 'payment_links.id', '=', 'escalations.model_id')
                        ->join('payments', 'payments.id', '=', 'payment_links.payment_id')
                        ->where('escalations.model_type', PaymentLink::class)
                        ->where('escalations.type', $filters['escalation_type']),
                };
            });
        });
    }

    /**
     * Applies reminder filter to a query.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyReminder($query, $filters)
    {
        $query->when(!empty($filters['reminder']), function ($query) {
            $query->whereIn('id', function ($sub) {
                $sub->select('claim_id')
                    ->from('claim_comments')
                    ->where('seen', false)
                    ->whereNotNull('reminder_date');
            });
        });
    }

    /**
     * Applies status filter to a query.
     *
     * The status filter uses a map of statuses to their corresponding values.
     * The statuses are as follows:
     * - registered
     * - assigned
     * - approved
     * - rejected
     * - done
     * - default_in_payment_by_system
     * - default_in_payment
     * - follow_up
     * - manual
     * - credited
     * - law
     * - delay
     * - partial
     * - transfer_to_elm
     * - closed
     * - collected_out_of_system
     *
     * The filter will only be applied if the status is not empty in the filters array.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyStatus($query, $filters)
    {
        $map = [
            'registered' => 'registered',
            'assigned' => 'assigned',
            'approved' => 'approved',
            'rejected' => 'rejected',
            'done' => 'done',
            'default_in_payment_by_system' => ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value,
            'default_in_payment' => ClaimStatus::DEFAULT_IN_PAYMENT->value,
            'follow_up' => ClaimStatus::FOLLOW_UP->value,
            'manual' => ClaimStatus::MANUAL_COLLECTED->value,
            'credited' => ClaimStatus::CREDITED->value,
            'law' => ClaimStatus::LAW_FIRM->value,
            'delay' => ClaimStatus::DELAY->value,
            'partial' => ClaimStatus::PARTIAL->value,
            'transfer_to_elm' => ClaimStatus::TRANSFER_TO_ELM->value,
            'closed' => ClaimStatus::CLOSED->value,
            'collected_out_of_system' => ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value,
        ];

        $statuses = collect($map)
            ->filter(fn($value, $key) => !empty($filters[$key]))
            ->values();

        if ($statuses->isNotEmpty()) {
            $query->whereIn('status', $statuses);
        }
    }

    /**
     * Applies basic filters to a query.
     * 
     * The basic filters are as follows:
     * - debtor_type
     * - city_id
     * - date
     * - updated_at
     * - assigned_at
     * - created_start
     * - created_end
     * - accident_date
     * - estimation_date
     * - collector_id
     * - is_link_viewed
     * - is_assigned_claims
     * - is_unassigned_claims
     * 
     * The LIKE filters loop through the following fields and applies the filter if the field is not empty in the filters array:
     * - claim_number
     * - debtor_phone
     * - debtor_name
     * - debtor_nid
     * - accident_number
     * - policy_number
     * - amount
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyBasicFilters($query, $filters)
    {
        $likeFields = [
            'claim_number',
            'debtor_phone',
            'debtor_name',
            'debtor_nid',
            'accident_number',
            'policy_number',
            'amount'
        ];

        $query
            ->when(isset($filters['debtor_type']), fn($q) => $q->where('debtor_type', $filters['debtor_type']))
            ->when(isset($filters['city_id']), fn($q) => $q->whereIn('city_id', Arr::wrap($filters['city_id'])))
            ->when(isset($filters['date']), fn($q) => $q->whereDate('created_at', $filters['date']))
            ->when(isset($filters['updated_from']) && isset($filters['updated_to']), fn($q) => $q->whereBetween('updated_at', [$filters['updated_from'], $filters['updated_to']]))

            ->when(isset($filters['assigned_from']) && isset($filters['assigned_to']), function ($q) use ($filters) {
                $q->whereHas('assignees', fn($q) => $q->whereBetween('assign_date', [$filters['assigned_from'], $filters['assigned_to']]));
            })

            ->when(isset($filters['created_from']) && isset($filters['created_to']), fn($q) => $q->whereBetween('created_at', [$filters['created_from'], $filters['created_to']]))
            ->when(isset($filters['accident_from']) && isset($filters['accident_to']), fn($q) => $q->whereBetween('accident_date', [$filters['accident_from'], $filters['accident_to']]))
            ->when(isset($filters['accident_date']), fn($q) => $q->whereDate('accident_date', $filters['accident_date']))
            ->when(isset($filters['intimation_from']) && isset($filters['intimation_to']), fn($q) => $q->whereBetween('intimation_date', [$filters['intimation_from'], $filters['intimation_to']]))

            ->when(isset($filters['collector_id']), function ($q) use ($filters) {
                $q->whereHas('assignees', function ($q) use ($filters) {
                    $q->whereIn('to', Arr::wrap($filters['collector_id']))
                        ->where('is_active', 1);
                });
            })
            ->when(isset($filters['user_id']), function ($q) use ($filters) {
                $q->whereIn('user_id', Arr::wrap($filters['user_id']));
            })

            ->when(isset($filters['is_link_viewed']), function ($q) use ($filters) {
                $q->whereHas('payments.links', function ($sub) use ($filters) {
                    $filters['is_link_viewed'] == 0
                        ? $sub->where('views_count', 0)
                        : $sub->where('views_count', '>', 0);
                });
            })
            ->when(isset($filters['is_assigned_claims']), function ($query) use ($filters) {
                if ($filters['is_assigned_claims'] == 1) {
                    $query->whereHas('assignees', function ($q) {
                        $q->where('is_active', 1);
                    });
                }
            })->when(isset($filters['is_unassigned_claims']), function ($query) use ($filters) {
                if ($filters['is_unassigned_claims'] == 1) {
                    $query->whereDoesntHave('assignees', function ($q) {
                        $q->where('is_active', 1);
                    });
                }
            });

        // LIKE filters loop
        foreach ($likeFields as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, 'ILIKE', "%{$filters[$field]}%");
            }
        }
    }

    /**
     * Apply search filter to the query.
     *
     * Search in claim_number, debitor_phone, amount and debitor_name fields.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applySearch($query, $filters)
    {
        $query->when(!empty($filters['search']), function ($q) use ($filters) {
            $q->where(function ($q) use ($filters) {
                $q->where('claim_number', 'ILIKE', "%{$filters['search']}%")
                    ->orWhere('debtor_phone', 'ILIKE', "%{$filters['search']}%")
                    ->orWhere('amount', 'ILIKE', "%{$filters['search']}%")
                    ->orWhere('debtor_name', 'ILIKE', "%{$filters['search']}%");
            });
        });
    }
    private function applyAccountingSearch($query, $filters)
    {
        $query->when(!empty($filters['search']), function ($q) use ($filters) {
            $q->where(function ($q) use ($filters) {
                $q->where('claim_number', 'ILIKE', "%{$filters['search']}%")
                    ->orWhere('debtor_phone', 'ILIKE', "%{$filters['search']}%")
                    ->orWhere('id', 'ILIKE', "%{$filters['search']}%")
                    ->orWhere('amount', 'ILIKE', "%{$filters['search']}%")
                    ->orWhere('debtor_name', 'ILIKE', "%{$filters['search']}%");
            });
        });
    }

    /**
     * Applies the base conditions to the claims query.
     * This includes filtering by client id and file id if provided.
     * Also orders the results in descending order by id.
     *
     * @param \Illuminate\Database\Eloquent\Builder $claims
     * @param array $filters
     * @param null|\App\Models\User $user
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function applyBaseConditions($claims, $filters, $user = null)
    {
        $user = $user ?? auth()->user();
        $claims->where('client_id', $user->client_id);

        $claims->when(!empty($filters['file_id']), function ($q) use ($filters) {
            $q->where('file_id', $filters['file_id']);
        });

        $claims->orderByDesc('id');

        return $claims;
    }

    /**
     * Handles the result of a claim query based on the provided parameters.
     *
     * If $query is true, the raw query builder is returned.
     * If $withPagination is true, the result is paginated with $perPage records per page.
     * Otherwise, the result is retrieved as a collection using get().
     *
     * @param \Illuminate\Database\Eloquent\Builder $claims
     * @param bool $withPagination
     * @param int $perPage
     * @param bool $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Pagination\LengthAwarePaginator|\Illuminate\Database\Eloquent\Collection
     */
    private function handleResult($claims, $withPagination, $perPage, $query)
    {
        if ($query) {
            return $claims;
        }

        return $withPagination
            ? $claims->paginate($perPage)
            : $claims->get();
    }


    private function applyAccountQuery($query, $filters = [], $isExport = false)
    {
        $userIds = User::where('user_type', UserType::EXTERNAL_USER->value)
            ->pluck('id');

        $query->whereNotIn('status', [ClaimStatus::REJECTED->value])

            // // invoiced logic
            // ->when($isExport, function ($q) {
            //     $q->where('invoice_status', InvoiceStatus::PENDING->value);
            // })
            // ->when(!$isExport && isset($filters['invoice_status']), function ($q) use ($filters) {
            ->when( isset($filters['invoice_status']), function ($q) use ($filters) {
                $q->whereIn('invoice_status', Arr::wrap($filters['invoice_status']));
            })

            ->when(isset($filters['collector_id']), function ($q) use ($filters) {
                $q->whereHas('assignees', function ($q) use ($filters) {
                    $q->whereIn('to', Arr::wrap($filters['collector_id']))
                        ->where('is_active', 1);
                });
            })

            
            ->when(isset($filters['claim_number']), function ($q) use ($filters) {
                $q->where('claim_number', 'ILIKE', "%{$filters['claim_number']}%");
            })
            ->when(isset($filters['claim_amount']), function ($q) use ($filters) {
                $q->where('amount', 'ILIKE', "%{$filters['claim_amount']}%");
            })
            ->when(isset($filters['claim_id']), function ($q) use ($filters) {
                $q->where('id', 'ILIKE', "%{$filters['claim_id']}%");
            })
            ->when(isset($filters['ids']), function ($q) use ($filters) {
                $q->whereIn('id', Arr::wrap($filters['ids']));
            })
            ->when(isset($filters['created_at_from'])  && isset($filters['created_at_to']), function ($q) use ($filters) {
                $q->whereBetween('created_at', [$filters['created_at_from'], $filters['created_at_to']]);

            })
            ->when(isset($filters['invoice_date_from']) && isset($filters['invoice_date_to']), function ($q) use ($filters) {
                $q->whereBetween('invoice_date', [$filters['invoice_date_from'], $filters['invoice_date_to']]);
            })

            ->when(isset($filters['user_type']), function ($q) use ($filters) {
                $q->whereHas('assignees.toUser', function ($sub) use ($filters) {
                        $sub->whereIn('user_type', Arr::wrap($filters['user_type'] ))->where('is_active',1);
                    });
            })

            ->where(function ($q) use ($userIds) {

                // أي status غير REGISTERED → يعدي عادي
                $q->where('status', '!=', ClaimStatus::REGISTERED->value)

                    // أو لو REGISTERED نطبق عليه الشروط
                    ->orWhere(function ($q2) use ($userIds) {
                        $q2->where('status', ClaimStatus::REGISTERED->value)
                            ->where(function ($q3) use ($userIds) {

                                // مر عليه 30 يوم
                                $q3->where('updated_at', '<=', now()->subDays(30))

                                    // أو assigned لـ external
                                    ->orWhereHas('assignees', function ($sub) use ($userIds) {
                                        $sub->whereIn('to', $userIds)
                                            ->where('is_active', 1);
                                    });
                            });
                    });
            });
    }



    private function collectFilters($request): array
    {
        return [
            'registered' => $request->registered,
            'assigned' => $request->assigned,
            'approved' => $request->approved,
            'rejected' => $request->rejected,
            'done' => $request->done,
            'follow_up' => $request->follow_up,
            'manual' => $request->manual,
            'credited' => $request->credited,
            'law' => $request->law,
            'delay' => $request->delay,
            'partial' => $request->partial,
            'closed' => $request->closed,
            'collected_out_of_system' => $request->collected_out_of_system,
            'third_party' => $request->third_party,
            'insured' => $request->insured,
            'debtor_type' => $request->debtor_type,
            'created_from' => $request->created_from,
            'created_to' => $request->created_to,
            'updated_from' => $request->updated_from,
            'updated_to' => $request->updated_to,
            'accident_date' => $request->accident_date,
            'intimation_from' => $request->intimation_from,
            'intimation_to' => $request->intimation_to,
            'accident_from' => $request->accident_from,
            'accident_to' => $request->accident_to,
            'user_id' => $request->user_id,
            'company_id' => $request->company_id,
            'claim_number' => $request->claim_number,
            'debtor_phone' => $request->debtor_phone,
            'debtor_name' => $request->debtor_name,
            'policy_number' => $request->policy_number,
            'amount' => $request->amount,
            'default_in_payment_by_system' => $request->default_in_payment_by_system,
            'default_in_payment' => $request->default_in_payment,
            'collector_id' => $request->collector_id,
            'file_id' => $request->file_id,
            'transfer_to_elm' => $request->transfer_to_elm,
            'search' => $request->search,
            'date' => $request->date,
            'city_id' => $request->city_id,
            'is_link_viewed' => $request->is_link_viewed,
            'is_assigned_claims' => $request->is_assigned_claims,
            'is_unassigned_claims' => $request->is_unassigned_claims,
            'assigned_from' => $request->assigned_from,
            'assigned_to' => $request->assigned_to,
            'escalation_type' => $request->escalation_type,
            'reminder' => $request->reminder,
            'debtor_nid' => $request->debtor_nid,
            'accident_number' => $request->accident_number,

            'invoice_status' => $request->invoice_status,
            'invoice_date' => $request->invoice_date,
            'user_type' => $request->user_type,
            'claim_amount' => $request->claim_amount,
            'claim_id' => $request->claim_id,
            'created_at' => $request->created_at,
        ];
    }
}
