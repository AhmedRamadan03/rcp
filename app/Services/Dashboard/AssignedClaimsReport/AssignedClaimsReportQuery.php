<?php

namespace App\Services\Dashboard\AssignedClaimsReport;

use App\Services\Dashboard\StatusReport\StatusReportChannels;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Aggregates assigned claims by channel roles or assignee users.
 *
 * Starts from claims, joins the latest active non-deleted assigning per claim,
 * then the assignee user — so each claim is counted once. Soft-deleted claims,
 * assignings, and payments are excluded. Amount prefers payments.amount, then
 * claims.amount (same as StatusReportQuery). Date filter uses
 * COALESCE(intimation_date, accident_date).
 */
class AssignedClaimsReportQuery
{
    /**
     * @param  list<int>  $roleIds
     * @param  array{from: string, to: string}  $filters
     * @return array{assigned_count: int, assigned_amount: float}
     */
    public function aggregateForRoles(array $roleIds, array $filters): array
    {
        $roleIds = $this->normalizeIds($roleIds);

        if ($roleIds === []) {
            return $this->emptyMetrics();
        }

        $query = $this->baseQuery($filters)
            ->whereIn('users.role_id', $roleIds);

        return $this->aggregate($query);
    }

    /**
     * @param  array{from: string, to: string, entity_ids?: array}  $filters
     * @return list<array{
     *     id: int,
     *     name: string,
     *     assigned_count: int,
     *     assigned_amount: float
     * }>
     */
    public function aggregateForUsers(array $filters): array
    {
        $query = $this->baseQuery($filters);

        $entityIds = $this->normalizeIds($filters['entity_ids'] ?? []);

        if ($entityIds !== []) {
            $query->whereIn('users.id', $entityIds);
        }

        $claimAmount = $this->claimAmountExpression();

        $rows = $query
            ->select([
                'users.id',
                'users.name',
            ])
            ->selectRaw('COUNT(claims.id) as assigned_count')
            ->selectRaw("SUM({$claimAmount}) as assigned_amount")
            ->groupBy('users.id', 'users.name')
            ->orderBy('users.id')
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'name' => $row->name,
                ...$this->normalizeMetrics($row),
            ];
        })->sort(function (array $a, array $b): int {
            $compared = $b['assigned_amount'] <=> $a['assigned_amount'];

            return $compared !== 0 ? $compared : ($a['id'] <=> $b['id']);
        })->take(10)->values()->all();
    }


    /**
     * Distinct claim IDs matching the same scoping as the aggregation methods,
     * for export purposes.
     *
     * @param  array{from: string, to: string, entity?: string, entity_ids?: array}  $filters
     * @return list<int>
     */
    public function claimIds(array $filters): array
    {
        $query = $this->baseQuery($filters);

        $entity = $filters['entity'] ?? 'role';
        $entityIds = $filters['entity_ids'] ?? [];

        if ($entity === 'user') {
            $userIds = $this->normalizeIds($entityIds);
            if ($userIds !== []) {
                $query->whereIn('users.id', $userIds);
            }
        } elseif ($entityIds !== []) {
            $roleIds = collect($entityIds)
                ->flatMap(fn($channel) => match ($channel) {
                    'internal' => StatusReportChannels::internal(),
                    'external' => StatusReportChannels::external(),
                    'legal' => StatusReportChannels::legal(),
                    default => [],
                })
                ->unique()->values()->all();

            if ($roleIds !== []) {
                $query->whereIn('users.role_id', $roleIds);
            }
        }

        return $query->select('claims.id')->distinct()->pluck('id')->all();
    }

    private function aggregate(Builder $query): array
    {
        $claimAmount = $this->claimAmountExpression();

        $row = $query
            ->selectRaw('COUNT(claims.id) as assigned_count')
            ->selectRaw("SUM({$claimAmount}) as assigned_amount")
            ->first();

        return $this->normalizeMetrics($row);
    }

    /**
     * @param  array{from: string, to: string}  $filters
     */
    private function baseQuery(array $filters): Builder
    {
        $latestAssignings = DB::table('assignings')
            ->select('claim_id')
            ->selectRaw('MAX(id) as id')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->groupBy('claim_id');

        return DB::table('claims')
            ->joinSub($latestAssignings, 'latest_assignings', function ($join): void {
                $join->on('claims.id', '=', 'latest_assignings.claim_id');
            })
            ->join('assignings', 'assignings.id', '=', 'latest_assignings.id')
            ->join('users', 'users.id', '=', 'assignings.to')
            ->leftJoin('payments', function ($join) {
                $join->on('payments.claim_id', '=', 'claims.id')
                    ->whereNull('payments.deleted_at');
            })
            ->whereNull('claims.deleted_at')
            ->whereRaw(
                'DATE(COALESCE(claims.intimation_date, claims.accident_date)) >= ?',
                [$filters['from']]
            )
            ->whereRaw(
                'DATE(COALESCE(claims.intimation_date, claims.accident_date)) <= ?',
                [$filters['to']]
            );
    }

    private function claimAmountExpression(): string
    {
        return 'COALESCE(payments.amount, claims.amount, 0)';
    }

    /**
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_map('intval', array_filter($ids, fn ($id) => is_numeric($id))));
    }

    /**
     * @return array{assigned_count: int, assigned_amount: float}
     */
    private function normalizeMetrics(?object $row): array
    {
        return [
            'assigned_count' => (int) ($row->assigned_count ?? 0),
            'assigned_amount' => round((float) ($row->assigned_amount ?? 0), 2),
        ];
    }

    /**
     * @return array{assigned_count: int, assigned_amount: float}
     */
    private function emptyMetrics(): array
    {
        return $this->normalizeMetrics(null);
    }
}
