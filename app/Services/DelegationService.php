<?php

namespace App\Services;

use App\Enums\DelegationStatus;
use App\Enums\NotificationType;
use App\Enums\Roles;
use App\Exceptions\NotExistsException;
use App\Models\Assigning;
use App\Models\Claim;
use App\Models\Delegation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

class DelegationService
{
    public function list(array $filters = [], ?int $perPage = 10)
    {
        return $this->buildQuery($filters)->paginate($perPage);
    }


    public function create($delegation)
    {

        $assigning = Assigning::where('claim_id', $delegation['claim_id'])
            ->latest()
            // ->unique('claim_id')
            ->first();

        $delegation['form'] = $assigning?->to;

        // return $delegation;

        return Delegation::create($delegation)->refresh();

    }

    public function createDelegation(array $data): Delegation
    {
        $isSeniorCollector = auth()->user()->role_id == Roles::SENIOR_COLLECTOR->value ?? false;
            if( $isSeniorCollector ) {
                if( !(new UserService())->belongToSupervisor([$data['from_user_id'], $data['to_user_id']], auth()->user()->id) ) {
                    throw new \Exception('One or more users are not under your supervision');
                }

            }

        return DB::transaction(function () use ($data) {

            // Check if from user already has an active delegation
            if ($this->hasActiveDelegationAsDelegator($data['from_user_id'])) {
                throw new \Exception('This user already has an active delegation.');
            }

            // check for active delegation between `from` and `to` users
            // if ($this->hasActiveDelegation($data['from_user_id'], $data['to_user_id'])) {
            //     throw new \Exception('An active delegation already exists between these users.');
            // }

            $claimsData = $this->getUserClaimsData($data['from_user_id']);

            $delegation = Delegation::create([
                'from' => $data['from_user_id'],
                'to' => $data['to_user_id'],
                'is_active' => 1,
                'status' => DelegationStatus::IN_PROGRESS->value,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'claims_status_before_delegation' => json_encode($claimsData),
            ]);


            NotificationService::create([
                'title_ar' => __('notification.delegations', [], 'ar'),
                'title_en' => __('notification.delegations', [], 'en'),
                'description_ar' => __('notification.delegation_claims', [], 'ar'),
                'description_en' => __('notification.delegation_claims', [], 'en'),
                'regarding' => NotificationType::DELEGATIONS->value,
                'user_id' => auth()->user()->id,
                'to' => json_encode([$data['from_user_id'], $data['to_user_id']]),
                'links' => json_encode([
                    [
                        'title_ar' => 'تم إنشاء التفويض',
                        'title_en' => 'delegation created',
                        'url' => 'delegations',
                    ],
                ]),
            ]);

            return $delegation->load(['fromUser', 'toUser']);
        });
    }


    /**
     * Cancel a delegation
     */
    public function cancelDelegation(int $delegationId): void
    {
        $delegation = Delegation::findOrFail($delegationId);
        $isSeniorCollector = auth()->user()->role_id == Roles::SENIOR_COLLECTOR->value ?? false;

        if ($isSeniorCollector) {
            if(!(new UserService())->belongToSupervisor([$delegation->from, $delegation->to], auth()->user()->id)){
                throw new \Exception('One or more users are not under your supervision');
            }
        }

        if ($delegation->is_active == 0) {
            throw new \Exception('The delegation already not active.');
        }

        $delegation->update([
            'is_active' => 0,
            'status' => DelegationStatus::CANCELLED_MANUALLY->value,
            'end_date' => Carbon::now()
        ]);

        NotificationService::create([
                'title_ar' => __('notification.delegations', [], 'ar'),
                'title_en' => __('notification.delegations', [], 'en'),
                'description_ar' => __('notification.delegation_claims', [], 'ar'),
                'description_en' => __('notification.delegation_claims', [], 'en'),
                'regarding' => NotificationType::DELEGATIONS->value,
                'user_id' => auth()->user()->id,
                'to' => json_encode([$delegation->from, $delegation->to]),
                'links' => json_encode([
                    [
                        'title_ar' => 'تم انهاء التفويض',
                        'title_en' => 'delegation cancelled',
                        'url' => 'delegations',
                    ],
                ]),
            ]);


    }

    /**
     * Check if from user already has an active delegation (can only have one)
     */

    public function hasActiveDelegationAsDelegator(int $fromUserId): bool
    {
        return Delegation::where([
            'from' => $fromUserId,
            'is_active' => 1,
        ])->exists();
    }

    /**
     * Check for existing active delegation between from to users
     */
    public function hasActiveDelegation(int $fromUserId, int $toUserId): bool
    {
        return Delegation::where(function ($query) use ($fromUserId, $toUserId) {
            $query->where([
                'from' => $fromUserId,
                'to' => $toUserId,
                'is_active' => 1,
            ])->orWhere([
                        'from' => $toUserId,
                        'to' => $fromUserId,
                        'is_active' => 1,
                    ]);
        })->exists();
    }

    /**
     * Get user claims data for delegation record
     */
    private function getUserClaimsData(int $userId): array
    {
        $claims = Claim::where('user_id', $userId)->get();

        return [
            'claims_count' => $claims->count(),
            'registered' => $claims->where('status', 'registered')->count(),
            'assigned' => $claims->where('status', 'assigned')->count(),
            'rejected' => $claims->where('status', 'rejected')->count(),
            'approved' => $claims->where('status', 'approved')->count(),
            'follow_up' => $claims->where('status', 'follow_up')->count(),
        ];
    }

    public function acceptDelegation($delegation_id)
    {

        // TODO check id is exists

        $delegation = Delegation::where('id', $delegation_id)->first();

        if ($delegation->status != DelegationStatus::NOT_SEEN->value) {
            throw new NotExistsException('You can not accept delegation that is not in not send state');
        }

        if ($delegation->status == DelegationStatus::REJECTED->value) {
            throw new NotExistsException('You can not accept delegation that is already rejected');
        }

        if (auth()->user()->role_id != Roles::SUPERVISOR->value) {
            throw new NotExistsException('You are not supervisor you can not accept this delegation');
        }

        // if (auth()->user()->id != $delegation->supervisor_id){
        //     throw new NotExistsException("You are not the one who create this delegation");
        // }

        return $delegation
            ->update([
                'status' => DelegationStatus::ACCEPTED,
            ]);

    }

    public function rejectDelegation($delegationRequest)
    {
        $delegation = Delegation::where('id', $delegationRequest['delegate_id'])->first();

        if ($delegation->status == DelegationStatus::REJECTED->value) {
            throw new NotExistsException('You can not reject delegation that is already rejected');
        }

        if ($delegation->status == DelegationStatus::ACCEPTED->value) {
            throw new NotExistsException('You can not reject delegation that is already accepted');
        }

        if (auth()->user()->role_id != Roles::SUPERVISOR->value) {
            throw new NotExistsException('You are not supervisor you can not reject this delegation');
        }

        return $delegation
            ->update([
                'status' => DelegationStatus::REJECTED->value,
                'rejection_comment' => $delegationRequest['comment'],
            ]);
    }


    public function export(array $filters = [], ?int $userId = null): Builder
    {
        return $this->buildQuery($filters, $userId);
    }

    public function deactivateUserDelegations(User $user)
    {
        $delegationFromIds = $user->delegationFrom()->pluck('id')->toArray();
        $delegationToIds = $user->delegationTo()->pluck('id')->toArray();

        $delegations = array_unique(array_merge($delegationFromIds, $delegationToIds));

        if(!empty($delegations)){
            Delegation::whereIn('id', $delegations)->update([
                'is_active' => 0,
                'status' => DelegationStatus::CANCELLED_BY_USER_DELETION->value,
                ]);
        }

    }
    protected function buildQuery(array $filters = [], ?int $userId = null): Builder
    {
        $userId = $userId ?? auth()->id();
        $user = User::findOrFail($userId);

        return Delegation::query()
            ->with(['fromUserWithTrashed', 'toUserWithTrashed'])
            ->when(
                !$this->canViewAll($user),
                fn(Builder $query) => $this->applyRoleScope($query, $user)
            )

            // ids filter 
            ->when(isset($filters['ids']), function (Builder $query) use ($filters) {
                $query->whereIn('id', $filters['ids']);
            })

            ->when(isset($filters['search']), function (Builder $query) use ($filters) {
                $this->applySearch($query, $filters['search']);
            })
            ->when(isset($filters['from_user_name']), function (Builder $query) use ($filters) {
                $query->whereHas(
                    'fromUserWithTrashed',
                    fn(Builder $q) => $q->where('name', 'ILIKE', "%{$filters['from_user_name']}%")
                );
            })
            ->when(isset($filters['to_user_name']), function (Builder $query) use ($filters) {
                $query->whereHas(
                    'toUserWithTrashed',
                    fn(Builder $q) => $q->where('name', 'ILIKE', "%{$filters['to_user_name']}%")
                );
            })
            ->when(isset($filters['start_date_from']), function (Builder $query) use ($filters) {
                $query->whereDate('start_date', '>=', $filters['start_date_from']);
            })
            ->when(isset($filters['start_date_to']), function (Builder $query) use ($filters) {
                $query->whereDate('start_date', '<=', $filters['start_date_to']);
            })
            ->when(isset($filters['end_date_from']), function (Builder $query) use ($filters) {
                $query->whereDate('end_date', '>=', $filters['end_date_from']);
            })
            ->when(isset($filters['end_date_to']), function (Builder $query) use ($filters) {
                $query->whereDate('end_date', '<=', $filters['end_date_to']);
            })

            ->when(
                isset($filters['created_at_from']) && isset($filters['created_at_to']),
                fn(Builder $query) => $query->whereBetween('created_at', [
                    $filters['created_at_from'],
                    $filters['created_at_to'],
                ])
            )

            ->when(isset($filters['status']), function (Builder $query) use ($filters) {
                $query->whereIn('status', $filters['status']);
            })
            ->when(isset($filters['duration_days']), function (Builder $query) use ($filters) {
                // calc: end_date (or now() if still open) minus start_date.
                $query->whereRaw(
                    'DATE_PART(\'day\', COALESCE(end_date, NOW()) - start_date) = ?',
                    [$filters['duration_days']]
                );
            })
            ->orderBy('created_at', 'desc');
    }

    /**
     * Generic search across from_user name, to_user name, and duration_days.
     * OR'd together — a match on any one field is enough. Kept as its own
     * parenthesized where() group so it never widens the role-scope clause.
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->orWhereHas(
                'fromUserWithTrashed',
                fn(Builder $qu) => $qu->where('name', 'ILIKE', "%{$search}%")
            );

            $q->orWhereHas(
                'toUserWithTrashed',
                fn(Builder $qu) => $qu->where('name', 'ILIKE', "%{$search}%")
            );

            if (is_numeric($search)) {
                $q->orWhereRaw(
                    'DATE_PART(\'day\', COALESCE(end_date, NOW()) - start_date) = ?',
                    [(int) $search]
                );
            }
        });
    }

    /**
     * SUPER_ADMIN and SUPERVISOR see every delegation, unscoped.
     */
    protected function canViewAll($user): bool
    {
        return in_array($user->role_id, [
            Roles::SUPER_ADMIN->value,
            Roles::SUPERVISOR->value,
        ]);
    }

    /**
     * SENIOR_COLLECTOR sees delegations where either party's supervisor is them.
     * Everyone else sees only delegations they are personally 'from' or 'to' on.
     */
    protected function applyRoleScope(Builder $query, $user): Builder
    {
        if ($user->role_id === Roles::SENIOR_COLLECTOR->value) {
            return $query->where(function (Builder $q) use ($user) {
                $q->whereHas(
                    'fromUserWithTrashed',
                    fn(Builder $qu) => $qu->where('supervisor_id', $user->id)
                )->orWhereHas(
                        'toUserWithTrashed',
                        fn(Builder $qu) => $qu->where('supervisor_id', $user->id)
                    );
            });
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->where('from', $user->id)->orWhere('to', $user->id);
        });
    }
}