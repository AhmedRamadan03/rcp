<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\Roles;
use App\Models\Claim;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DefaultOnPaymentService
{
    public function list(array $filters = [], ?int $perPage = 10)
    {
        return $this->buildQuery($filters)->paginate($perPage);
    }

    public function export(array $filters = [], ?int $userId = null): Builder
    {
        return $this->buildQuery($filters, $userId);
    }

    protected function buildQuery(array $filters = [], ?int $userId = null): Builder
    {
        $userId = $userId ?? auth()->id();
        $user = User::findOrFail($userId);

        return Claim::query()
            ->with('assignee.toUser')
            ->whereIn('status', [
                ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value,
                ClaimStatus::DEFAULT_IN_PAYMENT->value,
            ])
            ->when(
                !$this->canViewAll($user),
                fn(Builder $query) => $this->applyRoleScope($query, $user)
            )
            ->when(isset($filters['ids']), function (Builder $query) use ($filters) {
                $query->whereIn('id', $filters['ids']);
            })
            //claim id filter
            ->when(isset($filters['claim_id']), function (Builder $query) use ($filters) {
                $query->where('id', $filters['claim_id']);
            })

            ->when(filled($filters['claim_status'] ?? null), function (Builder $query) use ($filters) {
                $query->whereIn('status', $filters['claim_status']);
            })
            ->when(isset($filters['assigned_to']), function (Builder $query) use ($filters) {
                $query->whereHas(
                    'assignee.toUser',
                    fn(Builder $q) => $q->where('name', 'ILIKE', "%{$filters['assigned_to']}%")
                );
            })
            ->when(isset($filters['claim_number']), function (Builder $query) use ($filters) {
                $query->where('claim_number', 'ILIKE', "%{$filters['claim_number']}%");
            })
            ->when(isset($filters['claim_amount']), function (Builder $query) use ($filters) {
                $query->where('amount', $filters['claim_amount']);
            })
            ->when(
                isset($filters['accident_date_from']) && isset($filters['accident_date_to']),
                fn(Builder $query) => $query->whereBetween('accident_date', [
                    $filters['accident_date_from'],
                    $filters['accident_date_to'],
                ])
            )
            ->when(
                isset($filters['intimation_date_from']) && isset($filters['intimation_date_to']),
                fn(Builder $query) => $query->whereBetween('intimation_date', [
                    $filters['intimation_date_from'],
                    $filters['intimation_date_to'],
                ])
            )
            ->when(
                isset($filters['insertion_date_from']) && isset($filters['insertion_date_to']),
                fn(Builder $query) => $query->whereBetween('created_at', [
                    $filters['insertion_date_from'],
                    $filters['insertion_date_to'],
                ])
            )
            ->when(isset($filters['search']), function (Builder $query) use ($filters) {
                $this->applySearch($query, $filters['search']);
            })
            ->orderByDesc('id');
    }

    /**
     * Global search across assigned-to name, claim number, and claim amount.
     * OR'd together — a match on any one field is enough.
     */
    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function (Builder $q) use ($search) {
            $q->orWhereHas(
                'assignee.toUser',
                fn(Builder $qu) => $qu->where('name', 'ILIKE', "%{$search}%")
            );

            $q->orWhere('claim_number', 'ILIKE', "%{$search}%")
                ->orWhere('amount', 'ILIKE', "%{$search}%")
                ->orWhere('id', 'ILIKE', "%{$search}%");
        });
    }

    protected function isPrivileged(User $user): bool
    {
        return in_array($user->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value]);
    }
    /**
     * SUPER_ADMIN and SUPERVISOR see every claim, unscoped. So does anyone
     * (other than SENIOR_COLLECTOR, who is always scoped) explicitly
     * granted the 'showReports' ability on Claim.
     */
    protected function canViewAll(User $user): bool
    {
        if ($this->isPrivileged($user)) {
            return true;
        }

        if ($user->role_id === Roles::SENIOR_COLLECTOR->value) {
            return false;
        }

        return $user->can('showReports', Claim::class);
    }
    /**
     * SENIOR_COLLECTOR sees claims assigned to themself or their subordinates.
     * Everyone else sees only claims assigned to themself.
     */
    protected function applyRoleScope(Builder $query, User $user): Builder
    {
        $collectorIds = $user->role_id === Roles::SENIOR_COLLECTOR->value
            ? array_merge([$user->id], $user->subordinates()->pluck('id')->toArray())
            : [$user->id];

        return $query->whereHas(
            'assignees',
            fn(Builder $q) => $q->whereIn('to', $collectorIds)->where('is_active', 1)
        );
    }
}