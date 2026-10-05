<?php

namespace App\Services\Dashboard\StatusReport;

/**
 * Builds status-report detail rows and rolls them into a summary.
 *
 * Entity modes:
 * - role (default): returns internal, external, legal channels
 * - user: one row per assignee user
 *
 * When entity=role and entity_ids is provided, values are channel names
 * (internal | external | legal). Only the listed channels are returned.
 *
 * Detail rows are ordered by total amount (cleansed + collected + outstanding) descending,
 * and capped at the top 10.
 */
class StatusReportDetails
{
    private const DETAILS_LIMIT = 10;

    public function __construct(
        private readonly StatusReportQuery $query = new StatusReportQuery()
    ) {}

    /**
     * @param  array{from: string, to: string, entity: string, entity_ids: array, statuses: array}  $filters
     * @return list<array<string, mixed>>
     */
    public function buildDetails(array $filters): array
    {
        $details = match ($filters['entity'] ?? 'role') {
            'user' => $this->getUsers($filters),
            default => $this->getSelectedChannels($filters),
        };

        return array_slice($this->orderByTotalAmountDesc($details), 0, self::DETAILS_LIMIT);
    }

    /**
     * @param  array{from: string, to: string, entity_ids?: array, statuses?: array}  $filters
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
     * Internal channel detail row (id=1).
     *
     * @param  array{from: string, to: string, entity_ids?: array, statuses?: array}  $filters
     * @return array<string, mixed>
     */
    public function getInternal(array $filters): array
    {
        return $this->channelRow(1, 'internal', StatusReportChannels::internal(), $filters);
    }

    /**
     * External channel detail row (id=2).
     *
     * @param  array{from: string, to: string, entity_ids?: array, statuses?: array}  $filters
     * @return array<string, mixed>
     */
    public function getExternal(array $filters): array
    {
        return $this->channelRow(2, 'external', StatusReportChannels::external(), $filters);
    }

    /**
     * Legal channel detail row (id=3).
     *
     * @param  array{from: string, to: string, entity_ids?: array, statuses?: array}  $filters
     * @return array<string, mixed>
     */
    public function getLegal(array $filters): array
    {
        return $this->channelRow(3, 'legal', StatusReportChannels::legal(), $filters);
    }

    /**
     * User-mode detail rows.
     *
     * @param  array{from: string, to: string, entity_ids?: array, statuses?: array}  $filters
     * @return list<array<string, mixed>>
     */
    public function getUsers(array $filters): array
    {
        return $this->query->aggregateForUsers($filters);
    }

    /**
     * Roll detail rows into summary totals.
     *
     * @param  list<array<string, mixed>>  $details
     * @return array{
     *     total_amount: float,
     *     total_count: int,
     *     total_cleansed_amount: float,
     *     total_cleansed_count: int,
     *     total_collected_amount: float,
     *     total_collected_count: int,
     *     total_outstanding_amount: float,
     *     total_outstanding_count: int
     * }
     */
    public function buildSummary(array $details): array
    {
        $summary = [
            'total_amount' => 0.0,
            'total_count' => 0,
            'total_cleansed_amount' => 0.0,
            'total_cleansed_count' => 0,
            'total_collected_amount' => 0.0,
            'total_collected_count' => 0,
            'total_outstanding_amount' => 0.0,
            'total_outstanding_count' => 0,
        ];

        foreach ($details as $row) {
            $summary['total_cleansed_amount'] += (float) ($row['cleansed_amount'] ?? 0);
            $summary['total_cleansed_count'] += (int) ($row['cleansed_count'] ?? 0);
            $summary['total_collected_amount'] += (float) ($row['collected_amount'] ?? 0);
            $summary['total_collected_count'] += (int) ($row['collected_count'] ?? 0);
            $summary['total_outstanding_amount'] += (float) ($row['outstanding_amount'] ?? 0);
            $summary['total_outstanding_count'] += (int) ($row['outstanding_count'] ?? 0);
        }

        $summary['total_amount'] = round(
            $summary['total_cleansed_amount']
            + $summary['total_collected_amount']
            + $summary['total_outstanding_amount'],
            2
        );

        $summary['total_count'] = $summary['total_cleansed_count']
            + $summary['total_collected_count']
            + $summary['total_outstanding_count'];

        $summary['total_cleansed_amount'] = round($summary['total_cleansed_amount'], 2);
        $summary['total_collected_amount'] = round($summary['total_collected_amount'], 2);
        $summary['total_outstanding_amount'] = round($summary['total_outstanding_amount'], 2);

        return $summary;
    }

    /**
     * @param  list<int>  $roleIds
     * @param  array{entity_ids?: array, from?: string, to?: string, statuses?: array}  $filters
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
    private function orderByTotalAmountDesc(array $details): array
    {
        usort($details, function (array $a, array $b): int {
            $totalA = (float) ($a['cleansed_amount'] ?? 0)
                + (float) ($a['collected_amount'] ?? 0)
                + (float) ($a['outstanding_amount'] ?? 0);
            $totalB = (float) ($b['cleansed_amount'] ?? 0)
                + (float) ($b['collected_amount'] ?? 0)
                + (float) ($b['outstanding_amount'] ?? 0);

            $compared = $totalB <=> $totalA;

            if ($compared !== 0) {
                return $compared;
            }

            return ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0));
        });

        return array_values($details);
    }
}
