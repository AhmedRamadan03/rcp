<?php

namespace App\Services\Dashboard;

use App\Services\Dashboard\AssignedClaimsReport\AssignedClaimsReportDetails;
use App\Services\Dashboard\AssignedClaimsReport\AssignedClaimsReportFilters;
use Illuminate\Http\Request;

/**
 * Assigned claims report for dashboard channel / assignee comparisons.
 *
 * Compares assignment volume across channels when entity=role:
 * - internal (collectors + senior collectors)
 * - external (external office)
 * - legal (legal department)
 *
 * Or per assignee user when entity=user.
 *
 * Filters:
 * - from, to — COALESCE(intimation_date, accident_date) range (default: current year 01-01 → 12-31)
 * - entity — role | user (default: role)
 * - entity_ids — channel names when entity=role; user IDs when entity=user
 *
 * Response shape:
 * {
 *   "details": [
 *     {
 *       "id": 1,
 *       "name": "internal",
 *       "assigned_amount": 2296683.00,
 *       "assigned_count": 128
 *     }
 *   ],
 *   "summary": {
 *     "total_amount": 10000000.00,
 *     "total_count": 50
 *   }
 * }
 */
class AssignedClaimsReportService
{
    public function __construct(
        private readonly AssignedClaimsReportFilters $filters = new AssignedClaimsReportFilters(),
        private readonly AssignedClaimsReportDetails $details = new AssignedClaimsReportDetails()
    ) {}

    /**
     * @return array{details: list<array<string, mixed>>, summary: array{total_amount: float, total_count: int}}
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
