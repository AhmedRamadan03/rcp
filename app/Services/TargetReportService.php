<?php

namespace App\Services;

use App\Enums\Roles;
use App\Http\Resources\TopCollectorTarget;
use App\Models\Claim;
use App\Models\PaymentTransaction;
use App\Models\Target;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class TargetReportService
{
    private function officerSummaryQuery($userQuery)
    {

        $userQuery->withCount([
            'officer_claims as all_claims',
            'outstanding_status as outstanding_claims',
            'pending_status as pending_claims',
            'remaining_claims as remaining__claims',
            'canceled_claims as cancelled__claims',
            'collected_claims as collected__claims',
            'delay_status as delay_claims',
            'partial_status as partial_claims',
            'closed_status as closed_claims',
            'legal_status as legal_claims',
            'follow_up_status as follow_up_claims',
            'targets as active_targets_count',
        ])
            ->withSum('targets as target_sum', 'target')
            ->withSum('targets as target_achieved', 'achieved')
            ->withSum('targets as target_remaining', 'remaining')
            ->withSum('outstanding_status as outstanding_sum', 'amount')
            ->withSum('pending_status as pending_sum', 'amount')
            ->withSum('remaining_claims as remaining_sum', 'amount')
            ->withSum('collected_claims as collected_sum', 'amount')
            ->withSum('delay_status as delay_sum', 'amount')
            ->withSum('partial_status as partial_sum', 'amount')
            ->withSum('closed_status as closed_sum', 'amount')
            ->withSum('legal_status as legal_sum', 'amount')
            ->withSum('canceled_claims as canceled_sum', 'amount');

        return $userQuery;
    }

    public function getCollectorsWithAllData(?int $perPage = null, object $user = null, bool $isQuery = false, array $filters = [])
    {
        $user = auth()->user()??$user;

        $isPrivileged = in_array($user->role_id, [
            Roles::SUPER_ADMIN->value,
            Roles::SUPERVISOR->value,
        ]);
        $query = $this->officerSummaryQuery(
            User::without('client', 'role')
                ->select(['id', 'role_id', 'name'])
                ->whereHas('targets', function ($q) {
                    $q->withTrashed();
                })
                ->whereIn('role_id', [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value])
                ->when(
                    !$isPrivileged
                    && in_array($user->role_id, [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value])
                    && !$user->can('showReports', Claim::class),
                    function ($query) use ($user) {
                        $query->where('id', $user->id);
                    }
                )
                ->when($user->role_id === Roles::SENIOR_COLLECTOR->value, function ($query) use ($user) {
                    $query->where('supervisor_id', $user->id);
                })
                
        )
            ->orderByDesc('active_targets_count');

        $query = $this->subStringQuery($query,$filters);
        if ($isQuery) {
            return $query;
        }

        return $perPage ? $query->paginate($perPage) : $query->get();
    }

    public function getOneOfficerWithAllData($userId)
    {

        return $this->officerSummaryQuery(
            User::without('client', 'role')
                ->select(['id', 'role_id', 'name'])
                ->where('id', $userId)
        )->first();
    }

    public function getTopTarget()
    {
        $data = User::without('client', 'role')
            ->with('targets', function ($query) {
                $query->select('id', 'user_id', 'target', 'achieved', 'remaining', 'start_date')
                    ->without('user', 'claim')
                    ->orderBy('start_date');
            })
            ->select(['id', 'name'])
            ->withSum('targets as target_achieved', 'achieved')
            ->orderBy('target_achieved', 'desc')
            ->limit(5)
            ->get();

        return TopCollectorTarget::collection($data)->response()->getData();
    }

    public function getMonths($data)
    {
        return collect($data)->pluck('categories')->flatten(0)->unique()->values()->all();
    }

    //     public function getTargetData($target_id)
//     {
//         $target = Target::findOrFail($target_id);
//         $target_collector = User::findOrFail($target->user_id);

    //         $claims = $target_collector->collected_claims()
//             ->with([
//                 'payments.transactions' => function ($q) {
//                     $q->where('send_status', 'paid');
//                 },
//                 'payments.links' => function ($q) {
//                     $q->where('send_status', 'paid');
//                 }
//             ])
//             ->get();

    //         $rows = collect();

    //         foreach ($claims as $claim) {
//             foreach ($claim->payments as $payment) {
//                 foreach ($payment->transactions as $transaction) {

    //                     $link = $payment->links
//                         ->where('id', $transaction->link_id)
//                         ->first();

    //                     if (!$link) {
//                         continue;
//                     }

    //                 $rows->push([
//                     'claim_id'          => $claim->id,
//                     'collected'         => $transaction->amount,
//                     'date'              => $transaction->created_at,
//                     'claim_number'      => $claim->claim_number,
//                     'installment_number'=> $link->order,
//                 ]);
//             }
//         }
//     }
//     return $rows;
// }

    public function getTargetData($target_id, $perPage = 10, $isQuery = false, array $filters= [])
    {
        $target = Target::withTrashed()->findOrFail($target_id);

        // Filter transactions by the target's date range
        $startDate = $target->start_date;
        $endDate = $target->end_date;

        // Get payment links for this collector with their transactions within date range

        $transactions = PaymentTransaction::with(['link.payment.claim'])
            ->whereHas('link', function ($q) use ($target) {
                $q->where('collector_id', $target->user_id);
            })
            ->where('send_status', 'paid')
            ->when($startDate, function ($q) use ($startDate) {
                $q->where('created_at', '>=', $startDate);
            })
            ->when($endDate, function ($q) use ($endDate) {
                $q->where('created_at', '<=', $endDate);
            })
            ->orderBy('created_at', 'desc');

            $transactions = $this->filterQuery($transactions,$filters);

        if ($isQuery) {
            return $transactions;
        }
        $transactions = $transactions->get();

        $rows = $transactions->map(function ($transaction) {
            return [
                'id'=> $transaction->id,
                'claim_id' => $transaction->link?->payment?->claim_id,
                'collected' => $transaction->amount,
                'date' => $transaction->created_at,
                'claim_number' => $transaction->link?->payment?->claim?->claim_number,
                'installment_number' => $transaction->link?->order,
            ];
        });

        $page = request()->get('page', 1);
        $total = $rows->count();
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return $paginated;
    }


     private function subStringQuery($data,$filters)
    {
       $wrapped =  DB::query()->fromSub($data, 'summary');
        if (isset($filters['search'])) {
            $wrapped->where(function($q) use ($filters){
                $q->where('name', 'ILIKE',"%{$filters['search']}%")
                ->orWhere('all_claims', 'like', "%{$filters['search']}%")
                ->orWhere('target_remaining', 'like', "%{$filters['search']}%")
                ->orWhere('target_sum', 'like', "%{$filters['search']}%")
                ->orWhere('target_achieved', 'like',"%{$filters['search']}%");
            });
        }
        if (isset($filters['officer'])) {
            $wrapped->where('name', 'ILIKE',"%{$filters['officer']}%");
        }

        if (isset($filters['total_claims'])) {
            $wrapped->where('all_claims', 'like', "%{$filters['total_claims']}%");
           
        }
        if (isset($filters['arcieved_target'])) {
            $wrapped->where('target_achieved', 'like',"%{$filters['arcieved_target']}%");
        }
        if (isset($filters['remaining_target'])) {
            $wrapped->where('target_remaining', 'like',"%{$filters['remaining_target']}%"); 
        }
        if (isset($filters['total_target'])) {
            $wrapped->where('target_sum', 'like',"%{$filters['total_target']}%"); 
        }
        if (isset($filters['ids'])) {
            $wrapped->whereIn('id', $filters['ids']); 
        }

       return $wrapped->orderByDesc('active_targets_count');
    }


     private function searchFilter($queryBuilder, $search)
{
    $queryBuilder->where(function ($q) use ($search) {
        $q->whereRaw('amount::text LIKE ?', ["%{$search}%"])
            ->orWhereHas('link.payment.claim', function ($q) use ($search) {
                $q->where('claim_number', 'like', "%{$search}%");
            })
            ->orWhereHas('link', function ($q) use ($search) {
                $q->where('order', 'like', "%{$search}%");
            });
    });
}

private function filterQuery($data, $filters)
{
    if (isset($filters['collected'])) {
        $data->where('amount', 'like', "%{$filters['collected']}%");
    }
    if (isset($filters['ids'])) {
        $data->whereIn('id', Arr::Wrap($filters['ids']));
    }

    if (isset($filters['claim_number'])) {
        $data->whereHas('link.payment.claim', function ($q) use ($filters) {
            $q->where('claim_number', 'like', "%{$filters['claim_number']}%");
        });
    }

    if (isset($filters['installment_number'])) {
        $data->whereHas('link', function ($q) use ($filters){
            $q->where('order', 'like', "%{$filters['installment_number']}%");
        });
    }

    if (isset($filters['date_from'] ) && isset($filters['date_to'])) {
        $data->whereBetween('created_at', [$filters['date_from'], $filters['date_to']]);
    }

    if (isset($filters['search'])) {
        $this->searchFilter($data, $filters['search']);
    }

    if (isset($filters['created_at_from']) && isset($filters['created_at_to'])) {
        $data->whereBetween('created_at', [$filters['created_at_from'], $filters['created_at_to']]);
    }

    return $data;
}
}