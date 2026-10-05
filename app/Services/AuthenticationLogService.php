<?php

namespace App\Services;

use App\Enums\Roles;
use App\Models\AuthenticationLog;
use App\Models\Claim;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AuthenticationLogService
{
    public function list(array $filters = [], ?int $perPage = 15)
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

        return AuthenticationLog::query()
            ->with('authenticatable')
            ->when(
                !$this->canViewAll($user),
                fn(Builder $query) => $this->applyRoleScope($query, $user)
            )
            ->when(isset($filters['ids']), function (Builder $query) use ($filters) {
                $query->whereIn('id', $filters['ids']);
            })
            ->when(isset($filters['name']), function (Builder $query) use ($filters) {
                $query->whereHas(
                    'authenticatable',
                    fn(Builder $q) => $q->where('name', 'ILIKE', "%{$filters['name']}%")
                );
            })
            ->when(isset($filters['email']), function (Builder $query) use ($filters) {
                $query->whereHas(
                    'authenticatable',
                    fn(Builder $q) => $q->where('email', 'ILIKE', "%{$filters['email']}%")
                );
            })
            ->when(isset($filters['ip_address']), function (Builder $query) use ($filters) {
                $query->where('ip_address', 'ILIKE', "%{$filters['ip_address']}%");
            })
            ->when(isset($filters['login_successful']), function (Builder $query) use ($filters) {
                $query->where('login_successful', $filters['login_successful']);
            })
            ->when(isset($filters['login_at_from']), function (Builder $query) use ($filters) {
                $query->whereDate('login_at', '>=', $filters['login_at_from']);
            })
            ->when(isset($filters['login_at_to']), function (Builder $query) use ($filters) {
                $query->whereDate('login_at', '<=', $filters['login_at_to']);
            })
            ->when(isset($filters['logout_at_from']), function (Builder $query) use ($filters) {
                $query->whereDate('logout_at', '>=', $filters['logout_at_from']);
            })
            ->when(isset($filters['logout_at_to']), function (Builder $query) use ($filters) {
                $query->whereDate('logout_at', '<=', $filters['logout_at_to']);
            })
            ->orderBy('login_at', 'desc');
    }

    /**
     * SUPER_ADMIN and SUPERVISOR see every log, unscoped. So does anyone
     * explicitly granted the 'showReports' ability on Claim, matching the
     * original controller's permission check.
     */
    protected function canViewAll(User $user): bool
    {
        if (in_array($user->role_id, [Roles::SUPER_ADMIN->value, Roles::SUPERVISOR->value])) {
            return true;
        }

        return $user->can('showReports', Claim::class);
    }

    /**
     * SENIOR_COLLECTOR sees logs for themself and their subordinates.
     * Everyone else sees only their own logs.
     */
    protected function applyRoleScope(Builder $query, User $user): Builder
    {
        if ($user->role_id === Roles::SENIOR_COLLECTOR->value) {
            $subordinateIds = $user->subordinates()->pluck('id')->toArray();
            $userIds = array_merge([$user->id], $subordinateIds);

            return $query->whereIn('authenticatable_id', $userIds);
        }

        return $query->where('authenticatable_id', $user->id);
    }
}