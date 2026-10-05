<?php

namespace App\Services\Dashboard\StatusReport;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Builds and runs status-report aggregations against claims, assignings, users, and payments.
 *
 * Bucket rules (cleansed first — counts are mutually exclusive):
 * 1. cleansed    — claim status closed or rejected (excluded from collected / outstanding)
 * 2. collected   — payments.paid_amount > 0
 * 3. outstanding — everything else
 *
 * Amounts:
 * - cleansed    → SUM(COALESCE(payments.amount, claims.amount, 0))
 * - collected   → SUM(payments.paid_amount)
 * - outstanding → SUM over non-cleansed rows of max(gross - paid_amount, 0)
 *                 where gross = COALESCE(payments.amount, claims.amount, 0)
 *
 * Date filter uses COALESCE(claims.intimation_date, claims.accident_date).
 * Only the latest active non-deleted assigning per claim is used (same as AssignedClaimsReportQuery).
 * Soft-deleted claims/payments are excluded.
 * Status and ID inputs are allowlisted / cast to prevent SQL injection.
 */
class StatusReportQuery
{
    private const ALLOWED_STATUSES = ['cleansed', 'collected', 'outstanding'];

    /**
     * Aggregate cleansed / collected / outstanding metrics for the given role IDs.
     *
     * @param  list<int>  $roleIds
     * @param  array{from: string, to: string, entity?: string, entity_ids?: array, statuses?: array}  $filters
     * @return array{
     *     cleansed_count: int,
     *     cleansed_amount: float,
     *     collected_count: int,
     *     collected_amount: float,
     *     outstanding_count: int,
     *     outstanding_amount: float
     * }
     */
    public function aggregateForRoles(array $roleIds, array $filters): array
    {
        $roleIds = $this->normalizeIds($roleIds);

        if ($roleIds === []) {
            return $this->emptyMetrics();
        }

        $query = $this->baseQuery($filters)
            ->whereIn('users.role_id', $roleIds);

        return $this->aggregate($query, $filters);
    }

    /**
     * Aggregate metrics grouped by assignee user.
     *
     * @param  array{from: string, to: string, entity?: string, entity_ids?: array, statuses?: array}  $filters
     * @return list<array{
     *     id: int,
     *     name: string,
     *     cleansed_count: int,
     *     cleansed_amount: float,
     *     collected_count: int,
     *     collected_amount: float,
     *     outstanding_count: int,
     *     outstanding_amount: float
     * }>
     */
    public function aggregateForUsers(array $filters): array
    {
        $query = $this->baseQuery($filters);

        $entityIds = $this->normalizeIds($filters['entity_ids'] ?? []);

        if ($entityIds !== []) {
            $query->whereIn('users.id', $entityIds);
        }

        $bucket = $this->bucketExpression();
        $claimAmount = $this->claimAmountExpression();

        $this->applyStatusFilter($query, $filters, $bucket);

        $rows = $query
            ->select([
                'users.id',
                'users.name',
            ])
            ->selectRaw($this->metricsSelectRaw($bucket, $claimAmount))
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
            $totalA = $a['cleansed_amount'] + $a['collected_amount'] + $a['outstanding_amount'];
            $totalB = $b['cleansed_amount'] + $b['collected_amount'] + $b['outstanding_amount'];

            $compared = $totalB <=> $totalA;

            return $compared !== 0 ? $compared : ($a['id'] <=> $b['id']);
        })->take(10)->values()->all();
    }


    /**
     * Distinct claim IDs matching the same scoping as the aggregation methods,
     * for export purposes.
     *
     * @param  array{from: string, to: string, entity?: string, entity_ids?: array, statuses?: array}  $filters
     * @return list<int>
     */
    public function claimIds(array $filters): array
    {
        $bucket = $this->bucketExpression();
        $query = $this->baseQuery($filters);

        $entity = $filters['entity'] ?? 'role';
        $entityIds = $filters['entity_ids'] ?? [];

        if ($entity === 'user') {
            $userIds = $this->normalizeIds($entityIds);

            if ($userIds === []) {
                $userIds = collect($this->aggregateForUsers($filters))
                    ->pluck('id')
                    ->all();
            }

            if ($userIds === []) {
                return [];
            }

            $query->whereIn('users.id', $userIds);
        } else {
            $channels = $entityIds !== [] ? $entityIds : ['internal', 'external', 'legal'];
            $roleIds = collect($channels)
                ->flatMap(fn($channel) => match ($channel) {
                    'internal' => StatusReportChannels::internal(),
                    'external' => StatusReportChannels::external(),
                    'legal' => StatusReportChannels::legal(),
                    default => [],
                })
                ->unique()->values()->all();

            if ($roleIds === []) {
                return [];
            }

            $query->whereIn('users.role_id', $roleIds);
        }

        $this->applyStatusFilter($query, $filters, $bucket);

        return $query->select('claims.id')->distinct()->pluck('id')->all();
    }


    /**
     * @param  array{from: string, to: string, statuses?: array}  $filters
     * @return array{
     *     cleansed_count: int,
     *     cleansed_amount: float,
     *     collected_count: int,
     *     collected_amount: float,
     *     outstanding_count: int,
     *     outstanding_amount: float
     * }
     */
    private function aggregate(Builder $query, array $filters): array
    {
        $bucket = $this->bucketExpression();
        $claimAmount = $this->claimAmountExpression();

        $this->applyStatusFilter($query, $filters, $bucket);

        $row = $query
            ->selectRaw($this->metricsSelectRaw($bucket, $claimAmount))
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

        $query = DB::table('claims')
            ->joinSub($latestAssignings, 'latest_assignings', function ($join): void {
                $join->on('claims.id', '=', 'latest_assignings.claim_id');
            })
            ->join('assignings', 'assignings.id', '=', 'latest_assignings.id')
            ->join('users', 'users.id', '=', 'assignings.to')
            ->leftJoin('payments', function ($join) {
                $join->on('payments.claim_id', '=', 'claims.id')
                    ->whereNull('payments.deleted_at');
            })
            ->whereNull('claims.deleted_at');

        $this->applyDateFilter($query, $filters);

        return $query;
    }

    /**
     * Filter by COALESCE(claims.intimation_date, claims.accident_date).
     *
     * @param  array{from: string, to: string}  $filters
     */
    private function applyDateFilter(Builder $query, array $filters): void
    {
        $query->whereRaw(
            'DATE(COALESCE(claims.intimation_date, claims.accident_date)) >= ?',
            [$filters['from']]
        )->whereRaw(
            'DATE(COALESCE(claims.intimation_date, claims.accident_date)) <= ?',
            [$filters['to']]
        );
    }

    /**
     * @param  array{statuses?: array}  $filters
     */
    private function applyStatusFilter(Builder $query, array $filters, string $bucket): void
    {
        $statuses = array_values(array_filter(
            $filters['statuses'] ?? [],
            fn ($status) => is_string($status) && in_array($status, self::ALLOWED_STATUSES, true)
        ));

        if ($statuses === []) {
            return;
        }

        $placeholders = implode(', ', array_fill(0, count($statuses), '?'));
        $query->whereRaw("({$bucket}) IN ({$placeholders})", $statuses);
    }

    private function bucketExpression(): string
    {
        // Static SQL only. Status literals match ClaimStatus::CLOSED / REJECTED — not request input.
        return "CASE
            WHEN claims.status IN ('closed', 'rejected') THEN 'cleansed'
            WHEN COALESCE(payments.paid_amount, 0) > 0 THEN 'collected'
            ELSE 'outstanding'
        END";
    }

    /**
     * @return list<int>
     */
    private function normalizeIds(array $ids): array
    {
        return array_values(array_map('intval', array_filter($ids, fn ($id) => is_numeric($id))));
    }

    private function claimAmountExpression(): string
    {
        return 'COALESCE(payments.amount, claims.amount, 0)';
    }

    private function metricsSelectRaw(string $bucket, string $claimAmount): string
    {
        return "
            SUM(CASE WHEN ({$bucket}) = 'cleansed' THEN 1 ELSE 0 END) as cleansed_count,
            SUM(CASE WHEN ({$bucket}) = 'cleansed' THEN ({$claimAmount}) ELSE 0 END) as cleansed_amount,
            SUM(CASE WHEN ({$bucket}) = 'collected' THEN 1 ELSE 0 END) as collected_count,
            SUM(CASE WHEN ({$bucket}) = 'collected' THEN COALESCE(payments.paid_amount, 0) ELSE 0 END) as collected_amount,
            SUM(CASE WHEN ({$bucket}) = 'outstanding' THEN 1 ELSE 0 END) as outstanding_count,
            SUM(CASE
                WHEN ({$bucket}) = 'cleansed' THEN 0
                WHEN ({$claimAmount}) - COALESCE(payments.paid_amount, 0) > 0
                    THEN ({$claimAmount}) - COALESCE(payments.paid_amount, 0)
                ELSE 0
            END) as outstanding_amount
        ";
    }

    /**
     * @return array{
     *     cleansed_count: int,
     *     cleansed_amount: float,
     *     collected_count: int,
     *     collected_amount: float,
     *     outstanding_count: int,
     *     outstanding_amount: float
     * }
     */
    private function normalizeMetrics(?object $row): array
    {
        return [
            'cleansed_count' => (int) ($row->cleansed_count ?? 0),
            'cleansed_amount' => $this->toFloat($row->cleansed_amount ?? 0),
            'collected_count' => (int) ($row->collected_count ?? 0),
            'collected_amount' => $this->toFloat($row->collected_amount ?? 0),
            'outstanding_count' => (int) ($row->outstanding_count ?? 0),
            'outstanding_amount' => $this->toFloat($row->outstanding_amount ?? 0),
        ];
    }

    private function toFloat(mixed $value): float
    {
        return round((float) $value, 2);
    }

    /**
     * @return array{
     *     cleansed_count: int,
     *     cleansed_amount: float,
     *     collected_count: int,
     *     collected_amount: float,
     *     outstanding_count: int,
     *     outstanding_amount: float
     * }
     */
    private function emptyMetrics(): array
    {
        return $this->normalizeMetrics(null);
    }
}
