<?php

namespace App\Services\Dashboard\AssignedClaimsReport;

use App\Services\Dashboard\StatusReport\StatusReportChannels;

/**
 * Builds assigned-claims report detail rows and summary totals.
 *
 * Entity modes:
 * - role (default): internal, external, legal channels
 * - user: one row per assignee user
 *
 * When entity=role and entity_ids is provided, only listed channels are returned.
 * Detail rows are ordered by assigned_amount descending, and capped at the top 10.
 */
class AssignedClaimsReportDetails
{
    private const DETAILS_LIMIT = 10;

    public function __construct(
        private readonly AssignedClaimsReportQuery $query = new AssignedClaimsReportQuery()
    ) {}

    /**
     * @param  array{from: string, to: string, entity: string, entity_ids: array}  $filters
     * @return list<array<string, mixed>>
     */
    public function buildDetails(array $filters): array
    {
        $details = match ($filters['entity'] ?? 'role') {
            'user' => $this->getUsers($filters),
            default => $this->getSelectedChannels($filters),
        };

        return array_slice($this->orderByAssignedAmountDesc($details), 0, self::DETAILS_LIMIT);
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return array{total_amount: float, total_count: int}
     */
    public function buildSummary(array $details): array
    {
        $totalAmount = 0.0;
        $totalCount = 0;

        foreach ($details as $row) {
            $totalAmount += (float) ($row['assigned_amount'] ?? 0);
            $totalCount += (int) ($row['assigned_count'] ?? 0);
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'total_count' => $totalCount,
        ];
    }

    /**
     * @param  array{from: string, to: string, entity_ids?: array}  $filters
     * @return list<array<string, mixed>>
     */
    private function getSelectedChannels(array $filters): array
    {
        $channels = [
            'internal' => fn () => $this->getInternal($filters),
            'external' => fn () => $this->getExternal($filters),
            'legal' => fn () => $this->getLegal($filters),
        ];

        $selected = $filters['entity_ids'] ?? [];

        if ($selected === []) {
            return array_map(fn (callable $builder) => $builder(), array_values($channels));
        }

        $details = [];

        foreach ($channels as $name => $builder) {
            if (in_array($name, $selected, true)) {
                $details[] = $builder();
            }
        }

        return $details;
    }

    /**
     * @param  array{from: string, to: string}  $filters
     * @return array<string, mixed>
     */
    private function getInternal(array $filters): array
    {
        return $this->channelRow(1, 'internal', StatusReportChannels::internal(), $filters);
    }

    /**
     * @param  array{from: string, to: string}  $filters
     * @return array<string, mixed>
     */
    private function getExternal(array $filters): array
    {
        return $this->channelRow(2, 'external', StatusReportChannels::external(), $filters);
    }

    /**
     * @param  array{from: string, to: string}  $filters
     * @return array<string, mixed>
     */
    private function getLegal(array $filters): array
    {
        return $this->channelRow(3, 'legal', StatusReportChannels::legal(), $filters);
    }

    /**
     * @param  array{from: string, to: string, entity_ids?: array}  $filters
     * @return list<array<string, mixed>>
     */
    private function getUsers(array $filters): array
    {
        return $this->query->aggregateForUsers($filters);
    }

    /**
     * @param  list<int>  $roleIds
     * @param  array{from: string, to: string}  $filters
     * @return array<string, mixed>
     */
    private function channelRow(int $id, string $name, array $roleIds, array $filters): array
    {
        return [
            'id' => $id,
            'name' => $name,
            ...$this->query->aggregateForRoles($roleIds, $filters),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $details
     * @return list<array<string, mixed>>
     */
    private function orderByAssignedAmountDesc(array $details): array
    {
        usort($details, function (array $a, array $b): int {
            $compared = ((float) ($b['assigned_amount'] ?? 0)) <=> ((float) ($a['assigned_amount'] ?? 0));

            if ($compared !== 0) {
                return $compared;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return array_values($details);
    }
}
