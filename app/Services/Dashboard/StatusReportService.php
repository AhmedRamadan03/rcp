<?php

namespace App\Services\Dashboard;

use App\Services\Dashboard\StatusReport\StatusReportDetails;
use App\Services\Dashboard\StatusReport\StatusReportFilters;
use Illuminate\Http\Request;

/**
 * Status report orchestrator for dashboard channel / assignee comparisons.
 *
 * Compares performance across channels when entity=role:
 * - internal (collectors + senior collectors)
 * - external (external office)
 * - legal (legal department)
 *
 * Or per assignee user when entity=user.
 *
 * Each detail row includes three buckets:
 * 1. cleansed   — closed / cancelled (rejected) claims
 * 2. collected  — claims with payments.paid_amount > 0 (takes priority over cleansed)
 * 3. outstanding — all other claims with no paid amount
 *
 * Filters:
 * - from, to — COALESCE(intimation_date, accident_date) range (default: current year 01-01 → 12-31)
 * - entity — role | user (default: role)
 * - entity_ids — channel names (internal|external|legal) when entity=role; only those channels are returned.
 *               user IDs when entity=user (default: [])
 * - status — cleansed | collect | outstanding (default: all)
 *
 * Response shape:
 * {
 *   "details": [
 *     {
 *       "id": 1,
 *       "name": "internal",
 *       "cleansed_amount": 10000,
 *       "cleansed_count": 50,
 *       "collected_amount": 50000,
 *       "collected_count": 200,
 *       "outstanding_amount": 65000,
 *       "outstanding_count": 250
 *     }
 *   ],
 *   "summary": {
 *     "total_amount": 125000,
 *     "total_count": 500,
 *     "total_cleansed_amount": 10000,
 *     "total_cleansed_count": 50,
 *     "total_collected_amount": 50000,
 *     "total_collected_count": 200,
 *     "total_outstanding_amount": 65000,
 *     "total_outstanding_count": 250
 *   }
 * }
 */
class StatusReportService
{
    public function __construct(
        private readonly StatusReportFilters $filters = new StatusReportFilters(),
        private readonly StatusReportDetails $details = new StatusReportDetails()
    ) {}

    /**
     * Build the status report payload for the given request.
     *
     * @return array{details: list<array<string, mixed>>, summary: array<string, float|int>}
     */
    public function build(Request $request): array
    {
        $resolved = $this->filters->resolve($request);
        $details = $this->details->buildDetails($resolved);

        return [
            'details' => $details,
            'summary' => $this->details->buildSummary($details),
        ];
    }
}
