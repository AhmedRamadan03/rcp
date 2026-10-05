<?php

namespace App\Console\Commands;

use App\Enums\ClaimStatus;
use App\Models\ClaimStatusHistory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WrongDefaultOnPaymentClaims
{
    /**
     * @return list<string>
     */
    public static function finishedStatuses(): array
    {
        return [
            ClaimStatus::CLOSED->value,
            ClaimStatus::REJECTED->value,
            ClaimStatus::MANUAL_COLLECTED->value,
            ClaimStatus::MANUAL_COLLECTED_FOR_INSTALLMENT->value,
            ClaimStatus::BANK_COLLECTED->value,
            ClaimStatus::CREDITED->value,
            ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value,
            ClaimStatus::DONE->value,
        ];
    }

    /**
     * @return list<string>
     */
    public static function closedStatuses(): array
    {
        return [
            ClaimStatus::CLOSED->value,
            ClaimStatus::REJECTED->value,
            ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value,
        ];
    }

    public static function aggregatedQuery(): Builder
    {
        return ClaimStatusHistory::query()
            ->select('claim_id')
            ->selectRaw('COUNT(*) as conversion_count')
            ->selectRaw('MAX(id) as last_history_id')
            ->where('current_status', ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value)
            ->whereIn('previous_status', self::finishedStatuses())
            ->groupBy('claim_id')
            ->orderBy('claim_id');
    }

    /**
     * @return Collection<int, object{claim_id: int, previous_status: string, conversion_count: int, conversion_date: string|null}>
     */
    public static function rows(): Collection
    {
        $aggregates = self::aggregatedQuery()->get();

        $histories = ClaimStatusHistory::query()
            ->whereIn('id', $aggregates->pluck('last_history_id'))
            ->get()
            ->keyBy('id');

        return $aggregates->map(function ($row) use ($histories) {
            $history = $histories[$row->last_history_id];

            return (object) [
                'claim_id' => (int) $row->claim_id,
                'previous_status' => $history->previous_status,
                'conversion_count' => (int) $row->conversion_count,
                'conversion_date' => $history->created_at?->format('Y-m-d H:i:s'),
            ];
        });
    }
}
